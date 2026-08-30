<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * RoleMiddleware calls abort(403), but the global exception handler turns any
     * unhandled throwable into a dashboard redirect carrying the message — so a
     * denial reaches the browser as a redirect rather than a 403 status.
     */
    private function assertDenied(TestResponse $response): void
    {
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    /* ------------- the developer panel is off limits to the customer ------------ */

    public function test_admin_cannot_reach_the_developer_panel()
    {
        $this->assertDenied($this->actingAs($this->user('admin'))->get('/developer'));
    }

    public function test_admin_cannot_configure_the_subscription()
    {
        // The whole point of the gate is that the office cannot license itself.
        $this->assertDenied(
            $this->actingAs($this->user('admin'))->get(route('developer.subscription.config'))
        );

        $this->assertDenied(
            $this->actingAs($this->user('admin'))
                ->post(route('developer.subscription.activate'), ['duration' => 12])
        );
    }

    public function test_admin_cannot_run_developer_maintenance_actions()
    {
        $admin = $this->user('admin');

        $this->assertDenied($this->actingAs($admin)->post(route('developer.migrate')));
        $this->assertDenied($this->actingAs($admin)->post(route('developer.cache-clear')));
        $this->assertDenied($this->actingAs($admin)->post(route('developer.features.toggle')));
    }

    public function test_developer_still_reaches_the_developer_panel()
    {
        $this->actingAs($this->user('developer'))->get('/developer')->assertStatus(200);
    }

    /* ------------------ admin keeps everything meant for admin ------------------ */

    public function test_admin_still_reaches_admin_routes()
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/settings')->assertStatus(200);
        $this->actingAs($admin)->get('/users')->assertStatus(200);
        $this->actingAs($admin)->get('/audit-log')->assertStatus(200);
        $this->actingAs($admin)->get('/feasibility')->assertStatus(200);
    }

    public function test_admin_still_reaches_shared_team_routes()
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/cases')->assertStatus(200);
        $this->actingAs($admin)->get('/clients')->assertStatus(200);
        $this->actingAs($admin)->get('/tasks')->assertStatus(200);
    }

    /* ------------------------- other roles are unchanged ------------------------ */

    public function test_lawyer_cannot_reach_admin_only_routes()
    {
        $lawyer = $this->user('lawyer');

        $this->assertDenied($this->actingAs($lawyer)->get('/users'));
        $this->assertDenied($this->actingAs($lawyer)->get('/developer'));
    }

    public function test_lawyer_still_reaches_team_routes()
    {
        $this->actingAs($this->user('lawyer'))->get('/cases')->assertStatus(200);
    }

    public function test_client_cannot_reach_team_routes()
    {
        $this->assertDenied($this->actingAs($this->user('client'))->get('/cases'));
    }
}
