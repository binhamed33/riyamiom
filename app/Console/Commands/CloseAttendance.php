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
                            {--date= : اليوم المراد إقفاله (Y-m-d) — اليوم افتراضاً}
                            {--force : الإقفال ولو كان الخيار معطَّلاً في الإعدادات}
                            {--cap : إقفالُ ما تجاوز سقفَ المناوبة وحده}';

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

        // ═══ السقفُ أوّلاً، وهو لا يحتاج إذناً ═══
        //
        // حدٌّ معلومٌ مقدَّماً لا تخمينٌ من آخر نقرة: من مضى على حضوره
        // ثماني ساعاتٍ بلا انصراف يُقفل سجلُّه على «حضورٌ + ثماني».
        // ولولاه لبقي مفتوحاً أياماً وظهر صاحبُه «حاضراً» إلى الأبد.
        $capped = AttendanceGuard::closeOvertimeRecords();

        if ($capped > 0) {
            $this->info("أُقفل {$capped} سجلّاً بلغ سقفَ المناوبة ("
                . AttendanceGuard::capHours() . ' ساعات).');
        }

        if ($this->option('cap')) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! AttendanceGuard::autoCloseEnabled()) {
            $this->info('الإقفال التلقائي معطَّل: الانصراف يُسجَّل بزرّ الخروج وحده. (--force للتجاوز)');

            return self::SUCCESS;
        }

        $closed = AttendanceGuard::closeStaleRecords($day, force: true);

        $this->info($closed > 0
            ? "أُقفل {$closed} سجلّ حضورٍ حتى {$day->toDateString()}."
            : 'لا سجلّات مفتوحة.');

        return self::SUCCESS;
    }
}
