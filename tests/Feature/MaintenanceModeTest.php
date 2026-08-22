<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * وضع الصيانة: يوقف الخدمة عن مستخدمي المكتب ويُبقي لوحة الإدارة متاحة،
 * وهو حالة مستقلة عن انتهاء الاشتراك ولا يمسّ أي بيانات.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function putUnderMaintenance(): void
    {
        Setting::set('subscription_status', 'maintenance', 'subscription');
        Setting::set('subscription_start_at', now()->subDays(3)->toDateTimeString(), 'subscription');
        Setting::set('subscription_end_at', now()->addMonths(6)->toDateTimeString(), 'subscription');
    }

    private function activate(): void
    {
        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subDays(3)->toDateTimeString(), 'subscription');
        Setting::set('subscription_end_at', now()->addMonths(6)->toDateTimeString(), 'subscription');
    }

    public function test_maintenance_is_its_own_state_not_an_expiry(): void
    {
        $this->putUnderMaintenance();

        $service = app(SubscriptionService::class);
        $this->assertSame(SubscriptionService::STATUS_MAINTENANCE, $service->status());
        $this->assertFalse($service->isAllowed(User::factory()->create(['role' => 'lawyer', 'is_active' => true])));
    }

    public function test_office_users_land_on_the_maintenance_page_not_the_expiry_page(): void
    {
        $this->putUnderMaintenance();

        foreach (['lawyer', 'staff', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($user)->get('/cases')->assertRedirect(route('maintenance.page'));
        }
    }

    public function test_the_maintenance_page_says_data_is_safe_and_claims_no_finish_time(): void
    {
        $this->putUnderMaintenance();
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $page = $this->actingAs($user)->withSession(['locale' => 'ar'])->get(route('maintenance.page'));

        $page->assertStatus(503);   // الحالة الصحيحة لمحركات البحث والمراقبة
        $page->assertSee('نعمل حالياً على تحسين مُداوَلة', false);
        $page->assertSee('بيانات مكتبك محفوظة', false);
        // لا ادّعاء بوقت انتهاء
        $page->assertDontSee('خلال ساعة', false);
        $page->assertDontSee('بعد ساعتين', false);
    }

    public function test_the_developer_keeps_working_during_maintenance(): void
    {
        $this->putUnderMaintenance();
        $dev = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $this->actingAs($dev)->get('/developer')->assertSuccessful();
        $this->actingAs($dev)->get('/cases')->assertSuccessful();
    }

    public function test_an_optional_note_is_shown_when_set(): void
    {
        $this->putUnderMaintenance();
        Setting::set('maintenance_note', 'نُحدّث مركز الأتمتة — العودة خلال اليوم بإذن الله.');

        $user = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($user)->withSession(['locale' => 'ar'])->get(route('maintenance.page'))
            ->assertSee('نُحدّث مركز الأتمتة', false);
    }

    public function test_leaving_maintenance_restores_access_with_the_same_dates(): void
    {
        $this->putUnderMaintenance();
        $endBefore = Setting::get('subscription_end_at');

        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->actingAs($user)->get('/cases')->assertRedirect(route('maintenance.page'));

        $this->activate();

        // نفس تاريخ الانتهاء — الصيانة لم تستهلك أياماً من الاشتراك
        $this->assertSame($endBefore, Setting::get('subscription_end_at'));
        $this->actingAs($user)->get('/cases')->assertSuccessful();
    }

    public function test_an_expired_office_still_goes_to_the_expiry_page(): void
    {
        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonths(3)->toDateTimeString(), 'subscription');
        Setting::set('subscription_end_at', now()->subDay()->toDateTimeString(), 'subscription');

        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($user)->get('/cases')->assertRedirect(route('subscription.expired'));
    }
}
