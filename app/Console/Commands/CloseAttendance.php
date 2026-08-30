<?php

namespace App\Console\Commands;

use App\Support\AttendanceGuard;
use Illuminate\Console\Command;

/**
 * يُقفل سجلّات الحضور التي بقيت مفتوحة في آخر اليوم.
 *
 * الحضور يُسجَّل تلقائياً عند الدخول، والانصراف لا يُسجَّل إلا بضغط
 * «تسجيل خروج». والموظّف يُغلق المتصفّح ويمضي — وهو الغالب — فيبقى
 * السجلّ بلا انصرافٍ ولا دقائق، وسِجلُّ الشهر أعمدةٌ فارغة.
 *
 * يعمل مجدولاً آخر الليل، ويصحّ تشغيله يدوياً لأيّ يومٍ فات.
 */
class CloseAttendance extends Command
{
    protected $signature = 'hr:close-attendance
                            {--date= : اليوم المراد إقفاله (Y-m-d) — اليوم افتراضاً}';

    protected $description = 'إقفال سجلّات الحضور المفتوحة بوقت آخر نشاطٍ معروف للموظّف';

    public function handle(): int
    {
        $date = $this->option('date');

        try {
            $day = $date ? \Carbon\Carbon::parse($date) : now();
        } catch (\Throwable) {
            $this->error('تاريخ غير صالح: ' . $date);

            return self::FAILURE;
        }

        $closed = AttendanceGuard::closeStaleRecords($day);

        $this->info($closed > 0
            ? "أُقفل {$closed} سجلّ حضورٍ حتى {$day->toDateString()}."
            : 'لا سجلّات مفتوحة.');

        return self::SUCCESS;
    }
}
