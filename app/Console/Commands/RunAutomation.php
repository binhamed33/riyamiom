<?php

namespace App\Console\Commands;

use App\Services\AutomationService;
use Illuminate\Console\Command;

class RunAutomation extends Command
{
    protected $signature = 'mudawala:automation {--force : تجاهل مفتاح التفعيل}';

    protected $description = 'تشغيل قواعد الأتمتة: مهام تحضير الجلسات، مهام المتابعة، وتنبيهات القضايا الراكدة';

    public function handle(AutomationService $automation): int
    {
        if (!AutomationService::enabled() && !$this->option('force')) {
            $this->info('الأتمتة معطّلة (فعّلها من لوحة المطور).');

            return self::SUCCESS;
        }

        $result = $automation->run();

        $this->info(sprintf(
            'الأتمتة: %d مهمة تحضير، %d مهمة متابعة، %d تنبيه ركود.',
            $result['prep_tasks'],
            $result['followup_tasks'],
            $result['stale_notices'],
        ));

        return self::SUCCESS;
    }
}
