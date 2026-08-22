<?php

namespace App\Console\Commands;

use App\Models\Suggestion;
use App\Services\PanelReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * لماذا لا تصل الاقتراحات؟
 *
 * كل حالة تعلّق لها سبب واحد محدَّد، والحالة المخزّنة تقوله:
 *   pending  → المهمّة لم تُنفَّذ قط: طابور بلا عامل يشغّله.
 *   skipped  → المكتب غير مربوط باللوحة أصلاً.
 *   failed   → مربوط والنداء يخفق: عنوان أو رمز أو شبكة.
 *
 * يفحص السلسلة كلها ويقول أين انقطعت وما الأمر الذي يصلحها — بدل
 * تخمين يطول.
 */
class SuggestionsDoctor extends Command
{
    protected $signature = 'suggestions:doctor {--fix : محاولة تسليم المعلّق فوراً}';

    protected $description = 'تشخيص وصول الاقتراحات إلى لوحة مُداوَلة';

    public function handle(): int
    {
        $this->line('');
        $this->components->info('فحص وصول الاقتراحات');

        // ١) الحالات
        $counts = Suggestion::selectRaw('delivery_state, count(*) as c')
            ->groupBy('delivery_state')
            ->pluck('c', 'delivery_state')
            ->toArray();

        $total = array_sum($counts);
        $this->line('');
        $this->line('  <options=bold>الاقتراحات المحفوظة</> : ' . $total);

        foreach (['sent' => 'وصلت اللوحة', 'pending' => 'قيد الإرسال', 'skipped' => 'بلا وجهة', 'failed' => 'أخفق تسليمها'] as $k => $label) {
            if (($counts[$k] ?? 0) > 0) {
                $this->line('      ' . str_pad($label, 16) . ' : ' . $counts[$k]);
            }
        }

        // ٢) الربط
        $this->line('');
        $configured = PanelReporter::configured();
        $this->line('  <options=bold>الربط باللوحة</>');

        if (!$configured) {
            $this->line('  <fg=red>✗</> غير مربوط — PANEL_INGEST_URL أو PANEL_INGEST_TOKEN غير مضبوط في .env');
            $this->line('      على خادم اللوحة: php artisan panel:office-token <نطاق-هذا-المكتب>');
            $this->line('      ثم ضع السطرين في .env هنا، ثم: php artisan config:clear');

            return self::FAILURE;
        }

        $this->line('  <fg=green>✓</> مضبوط — ' . config('panel.ingest_url'));

        // ٣) هل تردّ اللوحة فعلاً؟
        try {
            $res = Http::timeout(8)
                ->withHeaders(['X-Mudawala-Token' => config('panel.ingest_token')])
                ->acceptJson()
                ->post(rtrim((string) config('panel.ingest_url'), '/') . '/ingest/heartbeat', []);

            if ($res->successful()) {
                $this->line('  <fg=green>✓</> اللوحة تردّ والرمز مقبول');
            } elseif ($res->status() === 401) {
                $this->line('  <fg=red>✗</> اللوحة تردّ لكنها ترفض الرمز (401) — الرمز لمكتب آخر أو دُوِّر');
                $this->line('      أعد إصداره: php artisan panel:office-token <نطاق-هذا-المكتب> على خادم اللوحة');

                return self::FAILURE;
            } else {
                $this->line('  <fg=red>✗</> اللوحة ردّت ' . $res->status());

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->line('  <fg=red>✗</> تعذّر الوصول إلى اللوحة: ' . $e->getMessage());
            $this->line('      تحقّق أن PANEL_INGEST_URL يُفتح من هذا الخادم');

            return self::FAILURE;
        }

        // ٤) الطابور — السبب الأشيع
        $this->line('');
        $this->line('  <options=bold>الطابور</>');
        $driver = config('queue.default');
        $this->line('      المشغّل : ' . $driver);

        $stuckPending = $counts['pending'] ?? 0;

        if ($driver === 'sync') {
            $this->line('  <fg=green>✓</> متزامن — يُسلَّم الاقتراح في الطلب نفسه، لا حاجة إلى عامل');
        } else {
            $queued = 0;

            try {
                $queued = DB::table('jobs')->count();
            } catch (\Throwable) {
                // مشغّل غير قاعدة البيانات — لا جدول jobs
            }

            $this->line('      في الانتظار : ' . $queued);

            if ($stuckPending > 0) {
                $this->line('  <fg=red>✗</> ' . $stuckPending . ' اقتراحاً «قيد الإرسال» ومهمّته لم تُنفَّذ قط.');
                $this->line('      السبب الأرجح: لا عامل طابور يعمل (queue:work).');
                $this->line('      أسهل حلّ: ضع QUEUE_CONNECTION=sync في .env ثم config:clear');
                $this->line('      أو شغّل عاملاً دائماً: php artisan queue:work --daemon');
            } else {
                $this->line('  <fg=green>✓</> لا اقتراح عالق');
            }
        }

        // ٥) قناة العودة — هل يصل ردّ المطوّر إلى الموظّف؟
        $this->line('');
        $this->line('<options=bold>قناة العودة (ردّ المطوّر إلى الموظّف)</>');

        $withReply = Suggestion::whereNotNull('developer_reply')->count();
        $unread = Suggestion::whereNotNull('developer_reply')->where('reply_read', false)->count();

        $this->line('      ردود وصلت المكتب : ' . $withReply . ' (غير مقروء: ' . $unread . ')');

        if (\App\Services\PanelReporter::configured()) {
            $this->line('  <fg=green>✓</> تُجلب من اللوحة كل ربع ساعة (suggestions:sync-replies)');

            if ($this->option('fix')) {
                $this->call('suggestions:sync-replies');
            }
        } else {
            $this->line('  <fg=yellow>!</> المكتب غير مربوط — لا ردّ يُجلب');
        }

        // ٦) العلاج
        $stuck = Suggestion::whereIn('delivery_state', ['pending', 'failed', 'skipped'])->count();

        if ($stuck > 0) {
            $this->line('');

            if ($this->option('fix')) {
                $this->line('  تسليم ' . $stuck . ' اقتراحاً عالقاً الآن…');
                $this->call('suggestions:retry-delivery');
            } else {
                $this->line('  <fg=yellow>!</> ' . $stuck . ' اقتراحاً عالقاً — لتسليمها فوراً:');
                $this->line('      php artisan suggestions:doctor --fix');
            }
        }

        $this->line('');

        return self::SUCCESS;
    }
}
