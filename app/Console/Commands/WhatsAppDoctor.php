<?php

namespace App\Console\Commands;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\WhatsAppSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تشخيص واتساب من الخادم — يجيب «أين انقطعت السلسلة» بدل التخمين.
 *
 * ═══ العطل الذي يمنعه ═══
 *
 * حين يقول المحامي «الرسائل لا تصل»، فالسلسلة أربع حلقات: ربطٌ عند
 * ‏Meta، ثمّ ويبهوك يصل الخادم، ثمّ دفترُ أحداثٍ يُقيَّد فيه، ثمّ طابورٌ
 * يعالجه. وانقطاعُ أيٍّ منها يبدو للمستخدم عطلاً واحداً بلا اسم — فيُعاد
 * الرمزُ ويُفصَل الرقمُ ويُعاد ربطُه، والعلّةُ في الطابور من الأصل.
 *
 * يقرأ هذا الأمرُ الحلقاتِ الأربع من السجلّات المحفوظة ويقول أيّها
 * ساكنة، ثمّ — إن طُلب --probe — يسأل Meta سؤالاً حيّاً واحداً.
 *
 * ولا يطبع الرمز ولا جزءاً منه: تُعرض بصمتُه المقنَّعة وحدها.
 */
class WhatsAppDoctor extends Command
{
    protected $signature = 'whatsapp:doctor {--probe : سؤالٌ حيٌّ واحد إلى Meta يكشف ردَّها الفعليّ}';

    protected $description = 'تشخيص واتساب: الربط والويبهوك ورسائل اليوم ودفتر الأحداث والطابور، وفحصٌ حيّ';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>تشخيص واتساب الأعمال</>');
        $this->line(str_repeat('─', 52));

        // ── ١) الربط ────────────────────────────────────────────
        $snapshot = WhatsAppSettings::snapshot();

        $this->row('الربط', $snapshot['connected']
            ? '<fg=green>مربوط</>'
            : '<fg=red>غير مربوط</>');

        if (! $snapshot['connected']) {
            $this->newLine();
            $this->line('اربط الرقم من: الإعدادات ← واتساب الأعمال.');
            $this->line('يلزم: رمز الوصول الدائم + معرّف الرقم (Phone number ID) من Meta.');

            // ولا يعود بفشل: مكتبٌ لم يربط رقمه بعد ليس مكتباً معطَّلاً،
            // وهذا الأمرُ يُشغَّل من سكربتات الصيانة كما يُشغَّل باليد.
            return self::SUCCESS;
        }

        $this->row('معرّف الرقم', $snapshot['phone_number_id']);
        $this->row('معرّف حساب الأعمال', $snapshot['waba_id']);
        $this->row('الرقم الظاهر', $snapshot['display_phone']);
        $this->row('اسم النشاط', $snapshot['business_name']);
        // بصمةُ الرمز لا الرمز: أربعةُ محارف يتعرّف بها المدير على أيّ
        // رمزٍ لصق، ولا تكفي أحداً ليرسل بها رسالةً واحدة.
        $this->row('بصمة الرمز', $snapshot['token_hint']);
        $this->row('رُبط في', $snapshot['connected_at']);
        $this->row('آخر مزامنة', $snapshot['last_sync_at']);
        $this->row('عنوان الويبهوك', $snapshot['webhook_url']);

        // آخرُ إشعارٍ وصل من Meta هو الدليلُ الوحيد على أنّ الويبهوك
        // مضبوطٌ عندهم فعلاً: ربطٌ صحيحُ الرمز بلا ويبهوكٍ يعني إرسالاً
        // يعمل واستقبالاً لا يصل — وهو أشدّ الأعطال التباساً.
        $this->row('آخر إشعار وارد', $snapshot['last_webhook_at']
            ? $snapshot['last_webhook_at']
            : '<fg=yellow>لم يصل قطّ</>');

        if (filled($snapshot['error'])) {
            $this->row('آخر خطأ', '<fg=yellow>' . $snapshot['error'] . '</>');
        }

        if ($snapshot['needs_attention']) {
            $this->newLine();
            $this->line('<fg=yellow>الربط يبدو حيّاً وفيه ما يستحقّ النظر — راجع السطور أعلاه.</>');
        }

        // ── ٢) المحادثات ───────────────────────────────────────
        $this->newLine();
        $this->line('<options=bold>المحادثات</>');

        if (! Schema::hasTable('whatsapp_conversations')) {
            $this->line('  جداول واتساب غير مهاجَرة — php artisan migrate');

            return self::SUCCESS;
        }

        $byStatus = WhatsAppConversation::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $open = (int) ($byStatus[WhatsAppConversation::STATUS_OPEN] ?? 0);
        $closed = (int) ($byStatus[WhatsAppConversation::STATUS_CLOSED] ?? 0);

        $this->count('مفتوحة', $open);
        $this->count('مغلقة', $closed);
        $this->count('فيها غير مقروء', WhatsAppConversation::where('unread_count', '>', 0)->count());
        $this->count('محوَّلة إلى موظّف', WhatsAppConversation::whereNotNull('handoff_at')->count());

        if ($open + $closed === 0) {
            $this->line('  لا محادثات بعد.');
        }

        // ── ٣) رسائل اليوم بحالتها ─────────────────────────────
        //    queued باقيةٌ منذ ساعات = طابورٌ لا يعمل، لا عطلٌ عند Meta.
        $this->newLine();
        $this->line('<options=bold>رسائل اليوم</>');

        $today = WhatsAppMessage::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('direction, status, count(*) as n')
            ->groupBy('direction', 'status')
            ->orderByDesc('n')
            ->get();

        if ($today->isEmpty()) {
            $this->line('  لا رسائل اليوم.');
        }

        foreach ($today as $row) {
            $direction = $row->direction === WhatsAppMessage::IN ? 'واردة' : 'صادرة';
            $status = $this->statusLabel((string) $row->status);
            $colour = match ((string) $row->status) {
                WhatsAppMessage::STATUS_FAILED => 'red',
                WhatsAppMessage::STATUS_QUEUED => 'yellow',
                default => 'green',
            };

            $this->line('  ' . $this->pad((string) $row->n, 6)
                . $this->pad($direction, 8) . '<fg=' . $colour . '>' . $status . '</>');
        }

        // الراكدةُ في الانتظار: أُنشئت ولم تُرسَل رغم مضيّ ربع ساعة
        $stuck = WhatsAppMessage::where('status', WhatsAppMessage::STATUS_QUEUED)
            ->where('created_at', '<', now()->subMinutes(15))
            ->count();

        if ($stuck > 0) {
            $this->line('  <fg=red>' . $stuck . ' رسالة عالقة في الانتظار منذ أكثر من ربع ساعة</>'
                . ' — العاملُ لا يصرّف الطابور.');
        }

        // ── ٤) دفتر أحداث الويبهوك ─────────────────────────────
        $this->newLine();
        $this->line('<options=bold>دفتر أحداث الويبهوك</>');

        $unprocessed = WhatsAppWebhookEvent::whereNull('processed_at')->count();
        $overdue = WhatsAppWebhookEvent::whereNull('processed_at')
            ->where('created_at', '<', now()->subMinutes(5))
            ->count();
        $errored = WhatsAppWebhookEvent::whereNull('processed_at')
            ->whereNotNull('error')
            ->count();

        $this->count('محفوظة كلّها', WhatsAppWebhookEvent::count());
        $this->count('لم تُعالَج', $unprocessed);
        $this->count('مضى عليها أكثر من ٥ دقائق', $overdue);
        $this->count('سجّلت خطأً', $errored);

        if ($overdue > 0) {
            $this->line('  <fg=yellow>شبكةُ الأمان تلتقطها: php artisan whatsapp:sweep</>');
        }

        // ── ٥) الطابور ─────────────────────────────────────────
        //    مهامُّ واتساب كلُّها أسماؤها تحمل «WhatsApp»، فالبحث في
        //    الحمولة يكفي بلا معرفة صنفٍ بعينه.
        $this->newLine();
        $this->line('<options=bold>الطابور</>');

        if (Schema::hasTable('jobs')) {
            $this->count('مهامّ واتساب تنتظر', DB::table('jobs')->where('payload', 'like', '%WhatsApp%')->count());
        } else {
            $this->line('  جدول jobs غير موجود — الطابور ليس على قاعدة البيانات.');
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('payload', 'like', '%WhatsApp%')->count();
            $this->count('استُنفدت محاولاتها', $failed);

            if ($failed > 0) {
                $this->line('  <fg=red>راجع السبب ثم أعِدها: php artisan queue:retry all</>');
            }
        }

        // ── ٦) فحصٌ حيّ عند الطلب ──────────────────────────────
        if ($this->option('probe')) {
            $this->newLine();
            $this->line('<options=bold>فحص حيّ…</>');

            $provider = WhatsAppManager::provider();

            if (! $provider) {
                $this->line('<fg=red>لا مزوّد مضبوط</> — راجع config/whatsapp.php وإعدادات المكتب.');

                return self::SUCCESS;
            }

            $t0 = microtime(true);
            try {
                $result = $provider->testConnection();
                $ms = (int) ((microtime(true) - $t0) * 1000);

                if ((bool) ($result['ok'] ?? false)) {
                    $this->line("<fg=green>Meta تردّ</> ({$ms} م.ث): " . (string) ($result['message'] ?? ''));
                    $this->row('الرقم الظاهر', (string) ($result['display_phone_number'] ?? ''));
                    $this->row('الاسم المعتمَد', (string) ($result['verified_name'] ?? ''));
                    // تقييمُ الجودة هبوطُه إنذارٌ مبكّر بتقييد الإرسال —
                    // ويهبط بالبلاغات عن رسائل غير مرغوبة لا بعطلٍ تقنيّ.
                    $this->row('تقييم الجودة', (string) ($result['quality_rating'] ?? ''));
                } else {
                    $this->line('<fg=red>أخفق:</> ' . (string) ($result['message'] ?? 'بلا تفسير'));
                }
            } catch (\Throwable $e) {
                $this->line('<fg=red>أخفق:</> ' . ($provider->getLastError() ?: $e->getMessage()));
            }
        } else {
            $this->newLine();
            $this->line('للفحص الحيّ (طلبٌ واحد إلى Meta): php artisan whatsapp:doctor --probe');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    // ── عرض ──────────────────────────────────────────────────────

    private function row(string $label, ?string $value): void
    {
        $this->line('  ' . $this->pad($label, 22) . ': ' . (filled($value) ? $value : '—'));
    }

    private function count(string $label, int $n): void
    {
        $this->line('  ' . $this->pad($label, 26) . ': ' . $n);
    }

    /**
     * حشوٌ بعدد المحارف لا بعدد البايتات.
     *
     * ‏str_pad يعدّ البايتات، والحرف العربي ثلاثةُ بايتات في UTF-8 —
     * فتخرج الأعمدة متكسّرةً بمقدار ضعفَي طول كل عنوان.
     */
    private function pad(string $text, int $width): string
    {
        $length = mb_strlen($text);

        return $length >= $width ? $text . ' ' : $text . str_repeat(' ', $width - $length);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            WhatsAppMessage::STATUS_QUEUED => 'في الانتظار',
            WhatsAppMessage::STATUS_SENT => 'أُرسلت',
            WhatsAppMessage::STATUS_DELIVERED => 'سُلّمت',
            WhatsAppMessage::STATUS_READ => 'قُرئت',
            WhatsAppMessage::STATUS_FAILED => 'أخفقت',
            default => $status,
        };
    }
}
