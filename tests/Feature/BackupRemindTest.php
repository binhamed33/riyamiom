<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Support\BackupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * التذكير الشهري بالنسخة الاحتياطية يصل مديرَ المكتب — بالحالة الصحيحة.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * مديرُ مكتبٍ لا يعرف أين نسخته ولا متى أُخذت يكتشف ذلك يومَ
 * الكارثة. والتذكيرُ الذي يطمئن دائماً — حتى والنسخةُ متوقفةٌ منذ
 * أسبوع — أخطر من لا تذكير: يبني ثقةً على خراب.
 */
class BackupRemindTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_a_fresh_backup_sends_a_calm_reminder_to_admins_only(): void
    {
        $admin1 = $this->admin();
        $admin2 = $this->admin();
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $gone = User::factory()->create(['role' => 'admin', 'is_active' => false]);

        Setting::set(BackupStatus::KEY_LAST_OK_AT, now()->subHours(5)->toIso8601String(), 'backup');

        $this->artisan('backup:remind')->assertSuccessful();

        foreach ([$admin1, $admin2] as $admin) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $admin->id,
                'title_key' => 'app.notif_backup_remind_title',
                'type' => 'info',
            ]);
        }

        $this->assertSame(0, Notification::whereIn('user_id', [$lawyer->id, $gone->id])->count(),
            'التذكير وصل غيرَ مديري المكتب');
    }

    /** نسخة متأخرة أكثر من يومين → التذكير ينقلب تحذيراً. */
    public function test_a_stale_backup_turns_the_reminder_into_a_warning(): void
    {
        $admin = $this->admin();
        Setting::set(BackupStatus::KEY_LAST_OK_AT, now()->subDays(5)->toIso8601String(), 'backup');

        $this->artisan('backup:remind')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title_key' => 'app.notif_backup_stale_title',
            'type' => 'warning',
        ]);
    }

    /** ولا نسخةَ ناجحةً قطّ → تحذير أيضاً، لا صمت ولا طمأنة كاذبة. */
    public function test_no_successful_backup_ever_is_a_warning_not_silence(): void
    {
        $admin = $this->admin();

        $this->artisan('backup:remind')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title_key' => 'app.notif_backup_stale_title',
            'type' => 'warning',
        ]);
    }

    /** نصّ التذكير يذكر التاريخ الحقيقي — لا قالباً فارغاً. */
    public function test_the_reminder_carries_the_real_date_and_size(): void
    {
        $admin = $this->admin();
        $at = now()->subHours(3);
        Setting::set(BackupStatus::KEY_LAST_OK_AT, $at->toIso8601String(), 'backup');

        $this->artisan('backup:remind')->assertSuccessful();

        $notification = Notification::where('user_id', $admin->id)->firstOrFail();
        $this->assertStringContainsString(
            $at->timezone('Asia/Muscat')->format('Y-m-d'),
            (string) $notification->message,
            'التاريخ الحقيقي غائب عن نصّ التذكير'
        );
    }
}
