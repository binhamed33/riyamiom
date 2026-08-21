<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Services\Automation\AutomationEngine;
use Illuminate\Console\Command;

class RunAutomation extends Command
{
    protected $signature = 'mudawala:automation {--force : تجاهل مفتاح التفعيل}';

    protected $description = 'تشغيل قواعد الأتمتة المجدولة وتذكيرات القوالب المستحقة';

    public function handle(AutomationEngine $engine): int
    {
        if (!AutomationEngine::enabled() && !$this->option('force')) {
            $this->info('الأتمتة معطّلة (فعّلها من مركز الأتمتة أو لوحة المطور).');

            return self::SUCCESS;
        }

        // ترقية سلسة: من كان يعمل بالقواعد المدمجة القديمة يحصل عليها
        // كقواعد قابلة للتحرير عند أول تشغيل بعد التحديث.
        if (Automation::count() === 0) {
            $seeded = AutomationEngine::seedDefaults();
            if ($seeded > 0) {
                $this->info("أُنشئت {$seeded} قواعد جاهزة (مطابقة لسلوك الأتمتة السابق).");
            }
        }

        $stats = $engine->runScheduled();

        $this->info(sprintf(
            'الأتمتة: %d تنفيذ، %d تخطٍّ، %d فشل، %d تذكير.',
            $stats['executed'],
            $stats['skipped'],
            $stats['failed'],
            $stats['reminders'],
        ));

        return self::SUCCESS;
    }
}
