<?php

namespace App\Console\Commands;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\EvolutionProvider;
use App\Support\WhatsAppSettings;
use Illuminate\Console\Command;

/**
 * إصلاحُ واتساب المكتب في أمرٍ واحد — يُشغَّل على كلّ مكتب.
 *
 * ═══ لماذا أمرٌ للإصلاح لا للتشخيص ═══
 *
 * ‏whatsapp:doctor يقول أين انقطعت السلسلة، ثمّ يبقى القرارُ لمن يقرأ.
 * وحين تكون العلّةُ واحدةً معروفةً في كلّ المكاتب، فالقراءةُ عشرَ مرّاتٍ
 * ثمّ التصرّفُ يدوياً عشرَ مرّاتٍ ليست حلاً. هذا الأمر يُصلح:
 *
 * ١) نسخةً ميتةً عند الجسر: صفٌّ في قاعدته والذاكرةُ لا تعرفها —
 *    فـ«create» يقول «الاسم مستعمَل» و«connect» يقول «غير موجودة»،
 *    ويبقى المكتبُ بلا اقترانٍ أبداً. تُحذف وتُنشأ نظيفة.
 * ٢) اقتراناً ساقطاً واعتمادُه صالح: يُعاد فتحُه بلا رمزٍ ولا يدِ أحد.
 * ٣) عنوانَ استقبالٍ لم يُضبط: تصل الرسائلُ إلى الجسر ولا تصل المكتب.
 * ٤) رسائلَ ماتت بـ«انقطع الاتصال» وهو عطلٌ عابر: تُعاد إلى الطابور.
 *
 * وما لا يفعله: لا يفصل رقماً موصولاً، ولا يحذف نسخةً حيّة، ولا يمسح
 * اقتراناً فصله المكتبُ بنفسه — فصلُه قرارُه وحده.
 */
class WhatsAppRepair extends Command
{
    protected $signature = 'whatsapp:repair
        {--force-qr : يُعيد إنشاء النسخة ولو لم يقل الجسرُ إنّها مفقودة (يتطلّب مسحَ رمزٍ جديد)}
        {--requeue=25 : كم رسالةً ماتت بانقطاعٍ عابرٍ تُعاد إلى الطابور}';

    protected $description = 'يُصلح واتساب هذا المكتب: النسخة والاقتران وعنوان الاستقبال والرسائل الميتة بلا سبب';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>إصلاح واتساب — ' . (string) config('app.url') . '</>');
        $this->line(str_repeat('─', 52));

        if (!WhatsAppSettings::usingEvolution()) {
            $this->warn('هذا المكتب على مزوّد Meta لا على جسر واتساب ويب — لا شيء يُصلَح هنا.');

            return self::SUCCESS;
        }

        $provider = new EvolutionProvider();

        if (!$provider->isConfigured()) {
            $this->error('الجسر غير مضبوط في ملفّ بيئة المكتب (EVOLUTION_BASE_URL / EVOLUTION_API_KEY).');
            $this->line('  الربط: sudo bash scripts/install-evolution.sh link ' . base_path());

            return self::FAILURE;
        }

        // ═══ الفصلُ الصريحُ قرارُ المكتب — لا يُلتفّ عليه ═══
        if (WhatsAppSettings::isDisconnected() && !$this->option('force-qr')) {
            $this->warn('المكتب فصل رقمه بنفسه — لا يُعاد وصلُه إلا بقراره من الإعدادات.');

            return self::SUCCESS;
        }

        $state = $provider->connectionState();
        $this->line('الحالة عند الجسر: <options=bold>' . $state . '</>');

        if ($state === 'open') {
            WhatsAppSettings::setEvolutionState('open');
            $this->info('✓ الرقم موصول — لا اقترانَ يُصلَح.');

            // العنوانُ يُعاد ضبطُه دائماً: جسرٌ أُعيد تنصيبُه ينسى عناوينه
            $this->line($provider->registerWebhook('', '', [])
                ? '✓ عنوان الاستقبال مضبوط'
                : '✗ تعذّر ضبط عنوان الاستقبال — الرسائل الواردة قد لا تصل');

            $this->requeueDead();

            return self::SUCCESS;
        }

        // ═══ إحياءٌ بلا رمز أولاً ═══
        //
        // اعتمادٌ صالحٌ عند الجسر يُعيد الفتحَ فوراً. ولا يُمسح شيءٌ قبل
        // أن تُجرَّب هذه: مسحُ رمزٍ جديد كلفتُه على المكتب لا علينا.
        if (!$this->option('force-qr')) {
            $revived = $provider->reconnect();

            if ($revived === 'open') {
                $this->info('✓ أُعيد وصلُ الرقم تلقائياً — بلا مسحِ رمز.');
                $provider->registerWebhook('', '', []);
                $this->requeueDead();

                return self::SUCCESS;
            }
        }

        // ═══ الشفاءُ من الباب المسدود ═══
        //
        // ‏pair() يحذف النسخةَ الميتةَ وينشئ نظيفةً ويعيد الرمز. ونداؤه
        // هنا يعني أنّ المكتب سيجد رمزاً حاضراً حين يفتح الإعدادات بدل
        // رسالةِ خطأ لا مخرجَ منها.
        $result = $provider->pair();

        if ($result['qr'] !== null) {
            $this->info('✓ النسخة جاهزة ورمزُ الاقتران يُعرض الآن في الإعدادات.');
            $this->line('  يبقى على المكتب: الإعدادات ← واتساب الأعمال ← امسح الرمز بالهاتف.');
            $this->requeueDead();

            return self::SUCCESS;
        }

        if ($result['state'] === 'open') {
            $this->info('✓ الرقم موصول.');
            $this->requeueDead();

            return self::SUCCESS;
        }

        $this->error('✗ ' . ($result['message'] ?? 'لم يُصدر الجسرُ رمزاً.'));
        $this->line('  افحص الجسر نفسَه: sudo bash scripts/install-evolution.sh status');

        return self::FAILURE;
    }

    /**
     * الرسائلُ التي ماتت بعطلٍ عابر تعود إلى الطابور.
     *
     * «انقطع الاتصال» كان يُصنَّف دائماً فتُدفن رسالةُ الموكّل نهائياً
     * ويقرأ المحامي «أخفق» — والاتصالُ عاد بعد دقيقة. تُعاد هنا ما دام
     * الرقمُ موصولاً الآن.
     */
    private function requeueDead(): void
    {
        $cap = max(0, (int) $this->option('requeue'));

        if ($cap === 0) {
            return;
        }

        $ids = WhatsAppMessage::query()
            ->where('status', WhatsAppMessage::STATUS_FAILED)
            ->where('direction', WhatsAppMessage::OUT)
            ->where('created_at', '>=', now()->subDays(3))
            ->where(function ($q) {
                foreach (['Connection Closed', 'Connection Lost', 'not connected', 'انقطع'] as $needle) {
                    $q->orWhere('error_title', 'like', '%' . $needle . '%');
                }
            })
            ->orderByDesc('id')
            ->limit($cap)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        foreach ($ids as $id) {
            WhatsAppMessage::whereKey($id)->update([
                'status' => WhatsAppMessage::STATUS_QUEUED,
                'error_title' => null,
            ]);
            \App\Jobs\SendWhatsAppMessage::dispatch((int) $id);
        }

        $this->info('✓ أُعيدت ' . $ids->count() . ' رسالةً ماتت بانقطاعٍ عابر إلى الطابور.');
    }
}
