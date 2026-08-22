<?php

namespace App\Console\Commands;

use App\Jobs\DeliverSuggestionJob;
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

        foreach ($stuck as $suggestion) {
            DeliverSuggestionJob::dispatch($suggestion->id);
        }

        $this->info('أُعيدت جدولة ' . $stuck->count() . ' اقتراحاً للتسليم.');

        return self::SUCCESS;
    }
}
