<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\User;
use App\Support\AttendanceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * مسحةُ السقف تُرى في فحص الصحّة — فلا يُسكتها قفلٌ عالق بصمت.
 */
class AttendanceSweepHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ساعةٌ مجمَّدة: الحزمةُ كانت خضراءَ بعد السادسة مساءً فقط — أوقاتُ «16:00» المزروعة
        // تقع في المستقبل صباحاً أو داخل نافذة الخمول عصراً
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-16 21:00:00', 'Asia/Muscat'));
    }

    private function record(): HrAttendance
    {
        $user = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        return HrAttendance::create([
            'user_id' => $user->id, 'work_date' => HrAttendance::today(),
            'check_in_at' => now()->subHour(), 'status' => 'present', 'source' => 'auto_login',
        ]);
    }

    /** المسحةُ تختم، والفحصُ يقرأ الختم. */
    public function test_the_sweep_stamps_and_health_reads_it(): void
    {
        $this->record();
        Cache::forget(AttendanceGuard::SWEEP_STAMP);

        Artisan::call('office:health');
        $this->assertStringContainsString('مسحةُ السقف لم تعمل بعد', Artisan::output());

        $this->artisan('hr:close-attendance --cap')->assertSuccessful();
        $this->assertNotNull(Cache::get(AttendanceGuard::SWEEP_STAMP));

        Artisan::call('office:health');
        $this->assertStringContainsString('مسحةُ السقف تعمل', Artisan::output());
    }

    /** ومسحةٌ قديمة تُقال بساعاتها، والمنسيُّ من أمسِ يُعدّ. */
    public function test_a_stale_sweep_and_forgotten_records_are_reported(): void
    {
        $record = $this->record();
        $record->update(['work_date' => now()->subDays(2)->toDateString(), 'check_in_at' => now()->subDays(2)->setTime(8, 0)]);
        Cache::put(AttendanceGuard::SWEEP_STAMP, now()->subHours(9)->toDateTimeString(), now()->addDay());

        Artisan::call('office:health');
        $out = Artisan::output();

        $this->assertStringContainsString('آخرُ مسحةِ سقفٍ قبل 9 ساعة', $out);
        $this->assertStringContainsString('بلا انصراف', $out);
    }
}
