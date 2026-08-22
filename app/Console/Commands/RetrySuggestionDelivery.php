<?php

namespace App\Console\Commands;

use App\Models\Suggestion;
use App\Services\PanelReporter;
use Illuminate\Console\Command;

/**
 * إعادة تسليم الاقتراحات العالقة.
 *
 * شبكة الأمان الأخيرة: لو تعطّل الطابور، أو كان المكتب غير مربوط يوم
 * كُتب الاقتراح ثم رُبط، أو أخفقت كل المحاولات — يلتقطها هذا الأمر.
 * فلا اقتراح يضيع بصمت.
 */
class RetrySuggestionDelivery extends Command
{
    protected $signature = 'suggestions:retry-delivery {--limit=50 : أقصى عدد في المرة}';

    protected $description = 'إعادة إرسال الاقتراحات التي لم تصل لوحة مُداوَلة بعد';

    public function handle(): int
    {
        if (!PanelReporter::configured()) {
            $this->line('هذا المكتب غير مربوط بلوحة مُداوَلة — لا شيء يُرسَل.');

            return self::SUCCESS;
        }

        // «skipped» تدخل هنا عمداً: كُتبت يوم لم يكن المكتب مربوطاً،
        // وقد رُبط الآن فتستحقّ محاولة.
        $stuck = Suggestion::whereIn('delivery_state', ['pending', 'failed', 'skipped'])
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('لا اقتراحات عالقة.');

            return self::SUCCESS;
        }

        // تُسلَّم هنا مباشرةً لا بإعادة جدولتها.
        //
        // هذا هو جوهر «شبكة الأمان»: أشيع سبب لتعلّق الاقتراحات هو أن
        // الطابور بلا عامل يشغّله (QUEUE_CONNECTION=database ولا
        // queue:work). فإعادة الجدولة إلى الطابور نفسه لا تصلح شيئاً —
        // تضيف مهمّة أخرى إلى طابور لا أحد يقرؤه، وتبقى الاقتراحات
        // «قيد الإرسال» إلى الأبد بينما يبدو أن هناك محاولات تجري.
        //
        // الأمر الدوري يعمل في الخلفية أصلاً، فنداء HTTP فيه لا يُبطئ أحداً.
        $sent = 0;
        $failed = 0;

        foreach ($stuck as $suggestion) {
            $suggestion->increment('delivery_attempts');

            if (PanelReporter::sendSuggestion($suggestion)) {
                $suggestion->forceFill([
                    'delivery_state' => 'sent',
                    'delivered_at' => now(),
                    'delivery_error' => null,
                ])->save();
                $sent++;

                continue;
            }

            $suggestion->forceFill([
                'delivery_state' => 'failed',
                'delivery_error' => 'تعذّر الوصول إلى اللوحة — سيُعاد في الدورة القادمة',
            ])->save();
            $failed++;
        }

        $this->info('سُلِّم ' . $sent . ' اقتراحاً' . ($failed ? '، وتعذّر ' . $failed : '') . '.');

        if ($failed > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
