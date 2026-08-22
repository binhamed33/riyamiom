<?php

namespace App\Console\Commands;

use App\Services\PanelReporter;
use Illuminate\Console\Command;

/**
 * نبضة إلى لوحة مُداوَلة.
 * تخرج فوراً إن لم يكن هذا المكتب مربوطاً باللوحة — مكتب مستقل لا
 * يتصل بشيء ولا يتأخر بسببها.
 */
class PanelHeartbeat extends Command
{
    protected $signature = 'panel:heartbeat';

    protected $description = 'إبلاغ لوحة مُداوَلة بحالة هذا المكتب (إن كان مربوطاً بها)';

    public function handle(): int
    {
        if (!PanelReporter::configured()) {
            $this->line('غير مربوط بلوحة — لا شيء يُرسل.');

            return self::SUCCESS;
        }

        $ok = PanelReporter::heartbeat();
        $this->line($ok ? 'وصلت النبضة.' : 'تعذّر إرسال النبضة — سيُعاد في الموعد التالي.');

        // فشل الإبلاغ ليس فشل المكتب — لا نُرجع خطأ يُقلق المجدول
        return self::SUCCESS;
    }
}
