<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_authenticated_user()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('لوحة التحكم');
    }

    public function test_dashboard_renders_for_client_role()
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_dashboard_requires_auth()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_shows_today_brief_for_team()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('ما يحتاج انتباهك اليوم');
        $response->assertSee('عرض كل شيء');
    }
}