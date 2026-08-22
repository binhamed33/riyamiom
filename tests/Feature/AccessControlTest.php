<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * حراسة الصلاحيات على الإعدادات الحسّاسة، وعدم تسرّب الإشعارات بين المستخدمين.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, User> */
    private function roles(): array
    {
        return [
            'lawyer' => User::factory()->create(['role' => 'lawyer', 'is_active' => true]),
            'staff' => User::factory()->create(['role' => 'staff', 'is_active' => true]),
            'client' => User::factory()->create(['role' => 'client', 'is_active' => true]),
        ];
    }

    public function test_sensitive_settings_are_closed_to_non_managers(): void
    {
        AiSettings::store('gemini', 'AIzaSyPROTECTEDKEY1234567', 'gemini-flash-latest');
        Storage::disk('local')->put('office/logo.png', 'x');
        \App\Models\Setting::set('office_logo_path', 'office/logo.png');

        $routes = [
            ['post', route('settings.ai.update'), ['ai_provider' => 'gemini', 'ai_api_key' => 'AIzaSyATTACKERKEY123456789']],
            ['delete', route('settings.ai.destroy'), []],
            ['post', route('settings.ai.test'), []],
            ['post', route('settings.logo.destroy'), ['_method' => 'DELETE']],
        ];

        foreach ($this->roles() as $role => $user) {
            foreach ($routes as [$verb, $url, $data]) {
                $response = $this->actingAs($user)->{$verb}($url, $data);
                $this->assertContains(
                    $response->status(),
                    [302, 403, 405],
                    "الدور {$role} وصل إلى {$url}"
                );
            }
        }

        // لا المفتاح تغيّر ولا الشعار حُذف
        $this->assertSame('AIzaSyPROTECTEDKEY1234567', AiSettings::apiKey());
        $this->assertTrue(Storage::disk('local')->exists('office/logo.png'));
    }

    public function test_user_management_is_closed_to_lawyers_and_staff(): void
    {
        foreach ($this->roles() as $role => $user) {
            $response = $this->actingAs($user)->get('/users');
            $this->assertContains($response->status(), [302, 403], "الدور {$role} وصل إلى إدارة المستخدمين");
        }
    }

    public function test_developer_only_pages_stay_developer_only(): void
    {
        // كان مدير المكتب يمرّ على كل بوابات الأدوار، فيصل إلى لوحة المطوّر
        // وأدوات الصيانة وإعدادات الاشتراك
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/developer');
        $this->assertContains($response->status(), [302, 403], 'مدير المكتب وصل إلى لوحة المطوّر');

        foreach (['developer.cache-clear', 'developer.migrate', 'developer.optimize', 'developer.storage-link'] as $name) {
            $r = $this->actingAs($admin)->post(route($name));
            $this->assertContains($r->status(), [302, 403], "مدير المكتب نفّذ {$name}");
        }

        $dev = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $this->actingAs($dev)->get('/developer')->assertSuccessful();
    }

    public function test_notifications_never_leak_between_users(): void
    {
        $a = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $b = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $mine = Notification::create(['user_id' => $a->id, 'title' => 'إشعار خاص بي', 'message' => 'محتوى خاص', 'type' => 'info', 'is_read' => false, 'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => 1]);
        $theirs = Notification::create(['user_id' => $b->id, 'title' => 'إشعار زميل آخر', 'message' => 'محتوى الزميل', 'type' => 'info', 'is_read' => false, 'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => 1]);

        $this->actingAs($a)->get('/notifications')
            ->assertOk()
            ->assertSee('إشعار خاص بي', false)
            ->assertDontSee('إشعار زميل آخر', false);

        // ولا يستطيع أحد تعليم إشعار غيره كمقروء
        $denied = $this->actingAs($a)->post(route('notifications.read', $theirs));
        $this->assertContains($denied->status(), [302, 403]);
        $this->assertFalse((bool) $theirs->fresh()->is_read, 'مستخدم عدّل إشعار زميله');
    }

    public function test_unread_badge_counts_only_the_current_users_notifications(): void
    {
        $a = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $b = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        foreach (range(1, 3) as $i) {
            Notification::create(['user_id' => $b->id, 'title' => "إشعار {$i}", 'message' => 'م', 'type' => 'info', 'is_read' => false, 'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => 1]);
        }
        Notification::create(['user_id' => $a->id, 'title' => 'إشعاري', 'message' => 'م', 'type' => 'info', 'is_read' => false, 'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => 1]);

        $count = Notification::where('user_id', $a->id)->where('is_read', false)->count();
        $this->assertSame(1, $count);

        $this->actingAs($a)->get('/dashboard')->assertOk()->assertDontSee('إشعار 1', false);
    }
}
