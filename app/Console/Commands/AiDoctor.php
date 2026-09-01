<?php

namespace App\Console\Commands;

use App\Services\GeminiService;
use App\Support\AiHealth;
use App\Support\AiSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * تشخيص الذكاء الاصطناعي من الخادم — يجيب «وش السبب» بدل التخمين.
 *
 * المحامي يرى رسالةً مهذّبة واحدة مهما كان العطل، وهذا صوابٌ له —
 * لكنه يترك صاحب النظام يخمّن: مفتاحٌ منتهي الحصّة؟ نموذجٌ متقاعد؟
 * الخادم لا يبلغ Google أصلاً؟ هذا الأمر يفصل بينها من السجلّات
 * المحفوظة، ثم — إن طُلب — بطلبٍ حيٍّ واحد يكشف ردَّ المزوّد الفعليّ.
 *
 * لا يطبع المفتاح ولا جزءاً منه.
 */
class AiDoctor extends Command
{
    protected $signature = 'ai:doctor {--probe : طلبٌ حيٌّ واحد إلى المزوّد يكشف ردّه الفعليّ}';

    protected $description = 'تشخيص المساعد: المفتاح، وأخطاء اليوم بأنواعها، والأسئلة المؤجَّلة، وفحصٌ حيّ';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>تشخيص الذكاء الاصطناعي</>');
        $this->line(str_repeat('─', 52));

        // ── ١) الإعداد ──────────────────────────────────────────
        $configured = AiSettings::isConfigured();
        $this->line('المفتاح:  ' . ($configured ? '<fg=green>مضبوط</>' : '<fg=red>غير مضبوط</>'));
        $this->line('النموذج:  ' . AiSettings::model());

        if (!$configured) {
            $this->warn('اضبطه من: الإعدادات ← الذكاء الاصطناعي.');

            return self::FAILURE;
        }

        // ── ٢) آخر نجاحٍ وآخر خطأ ───────────────────────────────
        $snap = AiHealth::snapshot();
        $this->line('آخر نجاح: ' . ($snap['last_success_at'] ?? '—'));
        $err = $snap['last_error'] ?? null;
        if ($err) {
            $this->line('آخر خطأ:  <fg=yellow>' . ($err['type'] ?? '؟') . '</> في ' . ($err['at'] ?? '؟'));

            if (!empty($err['message'])) {
                $this->line('قال المزوّد: <fg=yellow>' . $err['message'] . '</>');
            }
        }

        // ── ٣) أخطاء اليوم بأنواعها — النوع يقول السبب ─────────
        //    http_429 = حصّة المفتاح · http_503 = ازدحام المزوّد
        //    http_400/403 = مفتاح أو صلاحيّة · exhausted = نفدت المحاولات
        $today = DB::table('ai_requests')
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw("status, coalesce(error_type,'') as t, count(*) as n")
            ->groupBy('status', 't')->orderByDesc('n')->get();

        $this->newLine();
        $this->line('<options=bold>طلبات اليوم</>');
        if ($today->isEmpty()) {
            $this->line('  لا طلبات اليوم.');
        }
        foreach ($today as $row) {
            $label = $row->status === 'ok' ? '<fg=green>ناجح</>' : '<fg=red>' . ($row->t ?: 'خطأ') . '</>';
            $this->line('  ' . str_pad((string) $row->n, 5) . $label);
        }

        // ── ٤) الأسئلة المؤجَّلة في الطابور ─────────────────────
        $queued = DB::table('jobs')->where('payload', 'like', '%AnswerAssistantQuestion%')->count();
        $failed = DB::table('failed_jobs')->where('payload', 'like', '%AnswerAssistantQuestion%')->count();
        $this->newLine();
        $this->line('أسئلة تنتظر جواباً في الطابور: ' . $queued);
        if ($failed > 0) {
            $this->line('<fg=red>أسئلة استُنفدت محاولاتها: ' . $failed . '</> — php artisan queue:retry all');
        }

        // ── ٥) فحصٌ حيّ عند الطلب — يستهلك طلباً واحداً من الحصّة ──
        if ($this->option('probe')) {
            $this->newLine();
            $this->line('<options=bold>فحص حيّ…</>');
            AiSettings::interactive();
            $service = new GeminiService();
            $t0 = microtime(true);
            try {
                $reply = $service->chat(
                    [['role' => 'user', 'content' => 'قل: جاهز']],
                    'أجب بكلمة واحدة.'
                );
                $ms = (int) ((microtime(true) - $t0) * 1000);
                $this->line($reply
                    ? "<fg=green>المزوّد يردّ</> ({$ms} م.ث): " . mb_substr(trim($reply), 0, 40)
                    : '<fg=red>ردٌّ فارغ</> — ' . ($service->getLastError() ?: 'بلا تفسير'));
            } catch (\Throwable $e) {
                $this->line('<fg=red>فشل:</> ' . ($service->getLastError() ?: $e->getMessage()));
            }
        } else {
            $this->newLine();
            $this->line('للفحص الحيّ (يستهلك طلباً من الحصّة): php artisan ai:doctor --probe');
        }

        return self::SUCCESS;
    }
}
