<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_to_login()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_developer_can_access_dashboard()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas(['totalCases', 'totalClients', 'totalTasks', 'totalDocuments', 'totalSessions']);
    }

    public function test_admin_can_access_dashboard()
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_lawyer_can_access_dashboard()
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $response = $this->actingAs($lawyer)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_staff_can_access_dashboard()
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $response = $this->actingAs($staff)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_client_can_access_dashboard()
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_dashboard_shows_zero_stats_when_no_data()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('totalCases', 0);
        $response->assertViewHas('totalClients', 0);
        $response->assertViewHas('totalTasks', 0);
        $response->assertViewHas('totalDocuments', 0);
    }
}
