<?php

namespace App\Console\Commands;

use App\Services\StaffDiscordService;
use Illuminate\Console\Command;

class DiscordStaffStatus extends Command
{
    protected $signature = 'discord:staff-status';

    protected $description = 'تحديث لوحة تواجد الموظفين في Discord وإغلاق الجلسات المعلقة القديمة';

    public function handle(StaffDiscordService $service): int
    {
        $service->closeStaleSessions();
        $service->updateStatus();

        $this->info('تم تحديث حالة موظفين ديسكورد');

        return 0;
    }
}