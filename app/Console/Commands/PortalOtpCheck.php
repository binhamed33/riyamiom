<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\WhatsAppMessage;
use App\Services\ClientPortal\PortalOtp;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Console\Command;

/**
 * «الرمزُ لا يصل» — لماذا؟
 *
 * ═══ العطل الذي يشخّصه ═══
 *
 * صفحةُ الدخول تردّ ردّاً واحداً مهما جرى («إن كان الرقم مسجَّلاً وصلك
 * الرمز») كي لا يعرف غريبٌ أيُّ الأرقام لموكّلي المكتب. فإذا لم يصل الرمز
 * لم يعرف المكتبُ نفسُه السبب: أرقمٌ غيرُ مسجَّل؟ أم مسجَّلٌ لموكّلَين؟ أم
 * واتساب رفض الإرسال؟ وهذا الأمرُ يجيب — قراءةً فقط: لا يُرسل رمزاً ولا
 * رسالة، ولا يغيّر شيئاً.
 */
class PortalOtpCheck extends Command
{
    protected $signature = 'portal:otp-check {phone : رقمُ الهاتف كما يكتبه الموكّل في صفحة الدخول}';

    protected $description = 'لماذا لا يصل رمزُ دخول البوابة إلى هذا الرقم؟ (قراءة فقط — لا يُرسل شيئاً)';

    public function handle(): int
    {
        $raw = (string) $this->argument('phone');
        $ok = true;

        $this->newLine();
        $this->line('<options=bold>رمزُ دخول البوابة — فحصُ رقم</>');
        $this->line(str_repeat('─', 46));

        // ١) البوابة
        if (ClientPortal::enabled()) {
            $this->line('البوابة: <fg=green>مفعّلة</>');
        } else {
            $this->line('البوابة: <fg=red>معطَّلة</> — فعّلها من الإعدادات ← بوابة العملاء.');
            $ok = false;
        }

        // ٢) واتساب — يُسأل الجسرُ نفسُه لا الذاكرة
        $state = 'close';

        try {
            $state = WhatsAppSettings::usingEvolution()
                ? (string) WhatsAppManager::provider()?->connectionState()
                : (WhatsAppManager::isConnected() ? 'open' : 'close');
        } catch (\Throwable) {
            // يُقال «غير موصول» بسببه أدناه
        }

        if (WhatsAppSettings::isDisconnected()) {
            $this->line('واتساب: <fg=red>مفصولٌ بيد المكتب</> — أعد ربطَه من الإعدادات.');
            $ok = false;
        } elseif ($state === 'open') {
            $this->line('واتساب: <fg=green>موصول</>');
        } else {
            $this->line("واتساب: <fg=red>غير موصول</> (الحالة: {$state}) — الرمزُ لا يُرسل حتى يُربط الرقم.");
            $ok = false;
        }

        // ٣) الرقم: يجب أن يطابق موكّلاً واحداً بالضبط
        $normalized = Client::normalizePhone($raw);

        if (mb_strlen($normalized) < 8) {
            $this->line('الرقم: <fg=red>أقصرُ من ثمانية أرقام</> — الصفحةُ ترفضه قبل أيّ بحث.');

            return self::FAILURE;
        }

        $matches = Client::countByPhone($raw);
        $client = Client::findByPhone($raw);

        if ($matches === 0) {
            $this->line('الرقم: <fg=red>غيرُ مسجَّلٍ لأيّ موكّل</> — لا يُرسل رمز.');
            $this->line('  ← افتح ملفَّ الموكّل وتأكّد أنّ هذا الرقمَ نفسَه مكتوبٌ في خانة الهاتف، ثم احفظ.');
            $ok = false;
        } elseif ($matches > 1) {
            $this->line("الرقم: <fg=red>مسجَّلٌ لـ{$matches} موكّلين</> — لا يُرسل رمزٌ لرقمٍ مشترك (قد يدخل أحدُهما ملفَّ الآخر).");
            $this->line('  ← اجعل لكلّ موكّلٍ رقمَه، أو ادمج السجلَّين المكرَّرين.');
            $ok = false;
        } else {
            $to = PortalOtp::destination($client, $raw);
            $masked = strlen($to) > 4 ? '+' . substr($to, 0, 3) . str_repeat('•', max(0, strlen($to) - 7)) . substr($to, -4) : $to;
            $this->line("الرقم: <fg=green>موكّلٌ واحد</> (#{$client->id}) — يُرسل الرمزُ إلى واتساب {$masked}");

            $own = \App\Models\WhatsAppContact::normalizeWaId((string) WhatsAppSettings::displayPhone());

            if ($own !== '' && $own === $to) {
                $this->line('  <fg=red>وهذا رقمُ واتساب المكتب نفسُه</> — الرسالةُ منه إليه تقع في «أنت» بلا تنبيه. جرّب برقمٍ آخر.');
                $ok = false;
            } elseif (!\App\Models\WhatsAppContact::isSendable($to)) {
                $this->line('  <fg=red>والرقمُ في ملفّ الموكّل ناقص</> — صحّحه في صفحة العملاء.');
                $ok = false;
            }
        }

        // ٤) آخرُ ما جرى لرموز البوابة — الحالةُ والسببُ وحدهما، بلا رقمٍ ولا رمز
        $recent = WhatsAppMessage::query()
            ->where('direction', WhatsAppMessage::OUT)
            ->where('body', PortalOtp::LEDGER_BODY)
            ->latest('id')->limit(5)->get(['status', 'error_title', 'created_at']);

        $this->newLine();
        $this->line('آخرُ رموزٍ أُرسلت من البوابة:');

        if ($recent->isEmpty()) {
            $this->line('  <fg=gray>لا شيء — لم يُرسَل أيُّ رمزٍ بعد (كلُّ الطلبات كانت لأرقامٍ لا تطابق موكّلاً واحداً).</>');
        }

        foreach ($recent as $row) {
            // «queued» هنا ليست انتظاراً: الرمزُ يُرسَل في الطلب نفسِه بلا طابور، فصفٌّ
            // بقي عليها علِق إرسالُه (عطلٌ قبل أن يُكتب سببُه)
            $label = match ((string) $row->status) {
                WhatsAppMessage::STATUS_FAILED => '<fg=red>أخفق</> — ' . ($row->error_title ?: 'بلا سبب'),
                WhatsAppMessage::STATUS_QUEUED => '<fg=red>لم يُرسَل</> — علِق قبل أن يخرج إلى واتساب',
                WhatsAppMessage::STATUS_SENT => '<fg=green>خرج إلى واتساب</>',
                'delivered' => '<fg=green>وصل هاتفَ الموكّل</>',
                'read' => '<fg=green>قرأه الموكّل</>',
                default => (string) $row->status,
            };

            $this->line('  ' . $row->created_at?->format('m-d H:i') . '  ' . $label);
        }

        $this->newLine();
        $this->line($ok
            ? '<fg=green>كلُّ شيءٍ جاهز لهذا الرقم.</> إن لم يصل الرمز فانظر «آخر رموز» أعلاه: «أخفق» يحمل سببَ واتساب.'
            : '<fg=yellow>أصلح ما بالأحمر أعلاه ثم اطلب الرمز من صفحة الدخول.</>');
        $this->newLine();

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
