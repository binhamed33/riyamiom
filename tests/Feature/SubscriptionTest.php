<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function developer(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function clearSubscription(): void
    {
        Setting::where('group', 'subscription')->delete();
    }

    /* ------------------------------- activate ------------------------------- */

    public function test_activate_starts_a_period_from_now()
    {
        $this->clearSubscription();

        $result = $this->service()->activate(3);

        $this->assertSame(SubscriptionService::STATUS_ACTIVE, $this->service()->status());
        $this->assertTrue($result['end']->equalTo($result['start']->copy()->addMonthsNoOverflow(3)));
        $this->assertSame(3, $this->service()->info()['duration_months']);
    }

    public function test_activate_over_a_live_subscription_requires_confirmation()
    {
        $this->service()->activate(6);
        $endBefore = $this->service()->info()['end_at'];

        $response = $this->actingAs($this->developer())
            ->post(route('developer.subscription.activate'), ['duration' => 1]);

        $response->assertSessionHas('error');
        $this->assertTrue($this->service()->info()['end_at']->equalTo($endBefore));
    }

    public function test_confirmed_activation_replaces_the_period()
    {
        $this->service()->activate(6);
        $endBefore = $this->service()->info()['end_at'];

        $this->actingAs($this->developer())
            ->post(route('developer.subscription.activate'), ['duration' => 1, 'confirm' => '1'])
            ->assertSessionHas('success');

        $this->assertFalse($this->service()->info()['end_at']->equalTo($endBefore));
    }

    /* -------------------------------- extend -------------------------------- */

    public function test_extend_adds_to_the_remaining_time_instead_of_resetting()
    {
        $this->clearSubscription();
        $this->service()->activate(3);
        $endBefore = $this->service()->info()['end_at']->copy();

        $this->service()->extend(2);

        $this->assertTrue(
            $this->service()->info()['end_at']->equalTo($endBefore->copy()->addMonthsNoOverflow(2)),
            'extend must add onto the existing end date, not restart from now'
        );
    }

    public function test_extend_preserves_the_original_start_date()
    {
        $this->clearSubscription();
        $this->service()->activate(3);
        $startBefore = $this->service()->info()['start_at']->copy();

        $this->service()->extend(2);

        $this->assertTrue($this->service()->info()['start_at']->equalTo($startBefore));
    }

    public function test_duration_describes_the_whole_period_after_an_extension()
    {
        $this->clearSubscription();
        $this->service()->activate(3);

        $this->service()->extend(2);

        $this->assertSame(5, $this->service()->info()['duration_months']);
    }

    public function test_extend_on_an_expired_subscription_runs_from_now()
    {
        $this->clearSubscription();
        $this->service()->activate(1);
        Setting::set('subscription_start_at', now()->subMonths(2), 'subscription');
        Setting::set('subscription_end_at', now()->subMonth(), 'subscription');
        $this->assertSame(SubscriptionService::STATUS_EXPIRED, $this->service()->status());

        $result = $this->service()->extend(1);

        $this->assertLessThan(120, abs($result['end']->diffInSeconds(now()->addMonthsNoOverflow(1))));
        $this->assertSame(SubscriptionService::STATUS_ACTIVE, $this->service()->status());
    }

    public function test_extend_requires_a_duration_or_a_custom_end_date()
    {
        $this->service()->activate(3);
        $endBefore = $this->service()->info()['end_at'];

        $this->actingAs($this->developer())
            ->post(route('developer.subscription.extend'), [])
            ->assertSessionHas('error');

        $this->assertTrue($this->service()->info()['end_at']->equalTo($endBefore));
    }

    public function test_extend_is_refused_when_there_is_no_subscription()
    {
        $this->clearSubscription();

        $this->actingAs($this->developer())
            ->post(route('developer.subscription.extend'), ['duration' => 1])
            ->assertSessionHas('error');

        $this->assertSame(SubscriptionService::STATUS_NONE, $this->service()->status());
    }

    /* -------------------------------- expire -------------------------------- */

    public function test_expire_moves_the_end_date_into_the_past()
    {
        $this->service()->activate(12);

        $this->service()->expire();

        $this->assertTrue($this->service()->info()['end_at']->lessThan(now()));
        $this->assertSame(SubscriptionService::STATUS_EXPIRED, $this->service()->status());
    }

    public function test_expire_actually_locks_out_non_developers()
    {
        // Writing only the status would leave end_at in the future, and status()
        // recomputes from server time — the system would stay open.
        $this->service()->activate(12);
        $this->service()->expire();

        $this->assertFalse($this->service()->isAllowed($this->admin()));
        $this->assertTrue($this->service()->isAllowed($this->developer()));
    }

    public function test_expire_is_refused_when_nothing_is_running()
    {
        $this->clearSubscription();

        $this->actingAs($this->developer())
            ->post(route('developer.subscription.expire'))
            ->assertSessionHas('error');
    }

    /* -------------------------- suspend / reactivate ------------------------- */

    public function test_suspend_blocks_access_but_keeps_the_period()
    {
        $this->service()->activate(6);
        $endKept = $this->service()->info()['end_at']->copy();

        $this->service()->suspend();

        $this->assertSame(SubscriptionService::STATUS_SUSPENDED, $this->service()->status());
        $this->assertTrue($this->service()->info()['end_at']->equalTo($endKept));
        $this->assertFalse($this->service()->isAllowed($this->admin()));
    }

    public function test_reactivate_restores_the_original_period()
    {
        $this->service()->activate(6);
        $endKept = $this->service()->info()['end_at']->copy();
        $this->service()->suspend();

        $this->service()->reactivate();

        $this->assertSame(SubscriptionService::STATUS_ACTIVE, $this->service()->status());
        $this->assertTrue($this->service()->info()['end_at']->equalTo($endKept));
    }

    /* ----------------------------- custom periods ---------------------------- */

    public function test_activate_accepts_a_custom_end_date()
    {
        $this->clearSubscription();
        $target = now()->addDays(50);

        $this->actingAs($this->developer())
            ->post(route('developer.subscription.activate'), ['custom_end' => $target->format('Y-m-d')])
            ->assertSessionHas('success');

        $this->assertSame($target->format('Y-m-d'), $this->service()->info()['end_at']->format('Y-m-d'));
    }

    public function test_a_custom_end_date_runs_to_the_end_of_that_day()
    {
        $this->clearSubscription();

        $this->actingAs($this->developer())
            ->post(route('developer.subscription.activate'), [
                'custom_end' => now()->addDays(20)->format('Y-m-d'),
            ]);

        $this->assertSame('23:59:59', $this->service()->info()['end_at']->format('H:i:s'));
    }

    public function test_a_custom_period_has_no_month_duration()
    {
        $this->clearSubscription();

        $this->service()->activate(0, now()->addDays(45)->endOfDay());

        $info = $this->service()->info();
        $this->assertNull($info['duration_months']);
        $this->assertTrue($info['is_custom_period']);
        $this->assertSame('مدة مخصصة', SubscriptionService::durationLabel($info['duration_months']));
    }

    public function test_a_custom_end_date_in_the_past_is_rejected()
    {
        $this->clearSubscription();

        $this->actingAs($this->developer())
            ->post(route('developer.subscription.activate'), [
                'custom_end' => now()->subDays(3)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('custom_end');

        $this->assertSame(SubscriptionService::STATUS_NONE, $this->service()->status());
    }

    /* --------------------------------- status -------------------------------- */

    public function test_a_subscription_ending_within_a_week_is_expiring_soon()
    {
        $this->service()->activate(1);
        Setting::set('subscription_end_at', now()->addDays(3), 'subscription');

        $this->assertSame(SubscriptionService::STATUS_EXPIRING, $this->service()->status());
        $this->assertTrue($this->service()->isAllowed($this->admin()));
    }

    public function test_status_follows_server_time_not_the_stored_value()
    {
        $this->service()->activate(12);
        Setting::set('subscription_status', SubscriptionService::STATUS_ACTIVE, 'subscription');
        Setting::set('subscription_end_at', now()->subDay(), 'subscription');

        $this->assertSame(SubscriptionService::STATUS_EXPIRED, $this->service()->status());
    }

    public function test_no_configuration_means_no_subscription()
    {
        $this->clearSubscription();

        $this->assertSame(SubscriptionService::STATUS_NONE, $this->service()->status());
        $this->assertFalse($this->service()->isAllowed($this->admin()));
        $this->assertFalse($this->service()->isAllowed(null));
    }

    /* ---------------------------------- gate --------------------------------- */

    public function test_expired_subscription_redirects_a_normal_user_to_the_expired_page()
    {
        $this->service()->activate(12);
        $this->service()->expire();

        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertRedirect(route('subscription.expired'));
    }

    public function test_a_developer_is_never_locked_out()
    {
        $this->service()->activate(12);
        $this->service()->expire();

        $this->actingAs($this->developer())->get('/dashboard')->assertStatus(200);
    }

    public function test_the_expired_page_is_reachable_while_locked_out()
    {
        $this->service()->activate(12);
        $this->service()->expire();

        $this->actingAs($this->admin())->get(route('subscription.expired'))->assertStatus(200);
    }

    public function test_a_json_request_is_refused_with_403_rather_than_a_redirect()
    {
        $this->service()->activate(12);
        $this->service()->expire();

        $this->actingAs($this->admin())
            ->getJson('/dashboard')
            ->assertStatus(403);
    }

    public function test_only_developers_reach_the_subscription_settings()
    {
        // An office admin must not be able to license their own installation.
        // Denial arrives as a dashboard redirect because the global exception
        // handler converts the middleware's abort(403) into one.
        $this->actingAs($this->admin())
            ->get(route('developer.subscription.config'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->developer())
            ->get(route('developer.subscription.config'))
            ->assertStatus(200);
    }
}
