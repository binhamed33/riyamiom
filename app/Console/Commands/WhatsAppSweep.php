<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * شبكةُ أمان أحداث الويبهوك وتقليمُ دفترها.
 *
 * ═══ العطل الذي يمنعه الشقُّ الأول ═══
 *
 * مسارُ الاستقبال يقيّد الحدث في الدفتر ثمّ يدفع مهمّةَ معالجته إلى
 * الطابور ويردّ ٢٠٠ على Meta فوراً — وهذا صواب، فالمهلةُ عندهم ثوانٍ.
 * لكنّ الدفعَ إلى الطابور ليس تنفيذاً: لو كان العاملُ متوقّفاً لحظتَها
 * (تحديثُ خادم، أو أنّ العامل هو المجدوَل نفسه وقد تعثّر) بقي الحدثُ
 * مقيَّداً بلا معالجة إلى الأبد — ورسالةُ الموكّل لا تظهر في الخيط،
 * ولا يعلم أحدٌ أنّها وصلت أصلاً. Meta لن تعيدها: قد ردّت ٢٠٠.
 *
 * فما مضى عليه خمسُ دقائق ولم يُعالَج يُعاد دفعُه هنا. والمهمّة نفسها
 * تخرج صامتةً إن كان قد عولج (processed_at)، ودفترُ wamid يمنع رسالةً
 * مكرّرة — فإعادةُ الدفع بلا ضرر ولو كانت المهمّةُ الأولى في الطريق.
 *
 * ═══ ولماذا لا يُحذف إلا المعالَج ═══
 *
 * الحمولةُ الخام مراسلاتُ موكّلين. يُقلَّم منها ما عولج وقُيّد أثرُه في
 * جدول الرسائل وحده — أمّا ما لم يُعالَج فحذفُه فقدانُ رسالةٍ لم تُقرأ
 * قطّ. ولا تُمَسّ الرسائلُ ولا المحادثاتُ ولا جهاتُ الاتصال بحال.
 */
class WhatsAppSweep extends Command
{
    protected $signature = 'whatsapp:sweep';

    protected $description = 'إعادة معالجة أحداث واتساب العالقة وتقليم دفتر الأحداث المعالَجة';

    /** سقفُ ما يُعاد دفعُه في تشغيلٍ واحد — لا يُغرَق الطابور دفعةً واحدة. */
    private const REDISPATCH_CAP = 200;

    /** حجمُ دفعة الحذف — ولا يُقفل الجدول بحذفٍ واحد ضخم. */
    private const PRUNE_CHUNK = 1000;

    /** سقفُ الحذف في التشغيل الواحد — الباقي يُقلَّم في التشغيل التالي. */
    private const PRUNE_CAP = 20000;

    /**
     * الإفراجُ عمّا حان موعدُه، وإحياءُ ما أسقطته دورةُ التأجيل.
     *
     * ═══ لماذا تُحيا الفاشلة ═══
     *
     * نسخةٌ سابقة كانت تُعيد دفعَ المهمّة بمهلة، فإن لم تُحترم المهلةُ
     * دارت وأُعلنت الرسالةُ «فشلاً» وهي لم تُجرَّب قطّ — رسالةُ موكّلٍ
     * ضاعت لأنّ الساعة كانت الثالثة فجراً. وتلك الرسائلُ لا يزال
     * صاحبُها ينتظرها، فتُعاد إلى الانتظار بدل أن تُترك في «فشل»
     * أبديّ.
     *
     * والشرطُ ضيّق: نصُّ الخطأ بعينه، وبلا معرّف رسالةٍ عند المزوّد —
     * أي أنّها لم تُرسَل فعلاً. فلا يُعاد إرسالُ ما وصل.
     */
    protected function releaseHeld(): void
    {
        if (! Schema::hasColumn('whatsapp_messages', 'hold_until')) {
            return;
        }

        $due = \App\Models\WhatsAppMessage::query()
            ->where('status', \App\Models\WhatsAppMessage::STATUS_QUEUED)
            ->whereNotNull('hold_until')
            ->where('hold_until', '<=', now())
            ->orderBy('id')
            ->limit(self::REDISPATCH_CAP)
            ->pluck('id');

        foreach ($due as $id) {
            \App\Jobs\SendWhatsAppMessage::dispatch((int) $id);
        }

        if ($due->isNotEmpty()) {
            $this->line('  أُفرج عن ' . $due->count() . ' رسالة حان موعدها');
        }

        // إحياءُ ما أسقطته دورةُ التأجيل القديمة
        $revived = \App\Models\WhatsAppMessage::query()
            ->where('status', \App\Models\WhatsAppMessage::STATUS_FAILED)
            ->whereNull('wamid')
            ->where('error_title', 'like', '%ضمن حدود الأمان%')
            ->limit(self::REDISPATCH_CAP)
            ->get();

        foreach ($revived as $message) {
            $message->forceFill([
                'status' => \App\Models\WhatsAppMessage::STATUS_QUEUED,
                'hold_until' => null,
                'error_code' => null,
                'error_title' => null,
            ])->save();

            \App\Jobs\SendWhatsAppMessage::dispatch($message->id);
        }

        if ($revived->isNotEmpty()) {
            $this->line('  أُعيدت ' . $revived->count() . ' رسالة أسقطها عطلُ التأجيل');
        }
    }

    public function handle(): int
    {
        if (! Schema::hasTable('whatsapp_webhook_events')) {
            $this->line('جداول واتساب غير مهاجَرة — php artisan migrate');

            return self::SUCCESS;
        }

        // صفرٌ أو قيمةٌ سالبة في الإعداد تجعل عتبةَ التقليم «الآن»،
        // فيُمحى دفترُ اليوم كلُّه بما فيه ما لم يُعالَج بعد
        $retentionDays = max(1, (int) config('whatsapp.event_retention_days', 14));
        $retentionCutoff = now()->subDays($retentionDays);

        // ── ٠) الرسائلُ المحجوزة التي حان موعدها ──────────────
        //
        // الحجزُ لحدود الإيقاع (صمتٌ ليليّ أو سقفٌ بلغ) يُكتب في
        // الرسالة نفسها لا في الطابور. فهنا تُفرَج حين يحين وقتُها.
        $this->releaseHeld();

        // ── ١) إعادةُ دفع العالق ───────────────────────────────
        //
        // الترتيبُ بالأقدم أوّلاً: طابورٌ توقّف نصفَ ساعة يُصرَّف بترتيب
        // وصول الرسائل، فيقرأ الموظّف الخيطَ كما جرى لا معكوساً.
        $stale = WhatsAppWebhookEvent::query()
            ->whereNull('processed_at')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->where('created_at', '>=', $retentionCutoff)
            ->orderBy('id')
            ->limit(self::REDISPATCH_CAP)
            ->pluck('id');

        foreach ($stale as $id) {
            ProcessWhatsAppWebhook::dispatch((int) $id);
        }

        // ما تجاوز مدّةَ الحفظ ولم يُعالَج: لا يُعاد دفعُه ولا يُحذف.
        //
        // لا يُعاد دفعُه لأنّ حدثاً يخفق منذ أسبوعين لن يُصلحه تشغيلٌ
        // إضافي — ولولا هذا الحدّ لأُعيد دفعُه كلَّ خمس دقائق إلى
        // الأبد، فيبتلع الطابورَ عن رسائل اليوم. ولا يُحذف لأنّه
        // رسالةُ موكّلٍ لم تُقرأ قطّ؛ يُقال عددُها ليُنظر فيها.
        $stuck = WhatsAppWebhookEvent::query()
            ->whereNull('processed_at')
            ->where('created_at', '<', $retentionCutoff)
            ->count();

        // ── ٢) تقليمُ المعالَج ─────────────────────────────────
        $pruned = 0;

        do {
            $batch = WhatsAppWebhookEvent::query()
                ->whereNotNull('processed_at')
                ->where('created_at', '<', $retentionCutoff)
                ->limit(self::PRUNE_CHUNK)
                ->delete();

            $pruned += $batch;
        } while ($batch >= self::PRUNE_CHUNK && $pruned < self::PRUNE_CAP);

        // ── ٣) الحصيلة ────────────────────────────────────────
        $this->line('أُعيد دفعُ ' . $stale->count() . ' حدثاً عالقاً · '
            . 'قُلّم ' . $pruned . ' حدثاً معالَجاً أقدم من ' . $retentionDays . ' يوماً.');

        if ($stale->count() >= self::REDISPATCH_CAP) {
            $this->line('<fg=yellow>بلغ حدُّ التشغيل الواحد (' . self::REDISPATCH_CAP . ') — الباقي في التشغيل التالي.</>');
        }

        if ($stuck > 0) {
            $this->line('<fg=red>' . $stuck . ' حدثاً لم يُعالَج منذ أكثر من ' . $retentionDays . ' يوماً</>'
                . ' — محفوظٌ ولم يُحذف: php artisan whatsapp:doctor');
        }

        return self::SUCCESS;
    }
}
