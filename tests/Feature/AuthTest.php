<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_valid_login_redirects_to_dashboard()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'developer',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_invalid_login_returns_error()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('login_error');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login()
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_validation_email_required()
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_login_validation_password_required()
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_login_validation_email_must_be_valid()
    {
        $response = $this->post('/login', [
            'email' => 'not-email',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guest_redirected_to_login_when_accessing_dashboard()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_when_accessing_cases()
    {
        $this->get('/cases')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_when_accessing_clients()
    {
        $this->get('/clients')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_when_accessing_tasks()
    {
        $this->get('/tasks')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_when_accessing_sessions()
    {
        $this->get('/sessions')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_when_accessing_documents()
    {
        $this->get('/documents')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_when_accessing_users()
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_login_attempts_throttle_with_middleware()
    {
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'email' => 'throttle-test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'throttle-test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('login_error');
        $this->assertGuest();
    }

    public function test_root_redirects_to_dashboard_when_authenticated()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/');

        $response->assertRedirect('/dashboard');
    }
}
