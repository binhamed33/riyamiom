<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function validUserData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Strong@123',
            'password_confirmation' => 'Strong@123',
            'role' => 'lawyer',
            'is_active' => true,
        ], $overrides);
    }

    public function test_guest_redirected_to_login_on_index()
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_create()
    {
        $this->get('/users/create')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_store()
    {
        $this->post('/users', [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_show()
    {
        $user = User::factory()->create();
        $this->get("/users/{$user->id}")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_edit()
    {
        $user = User::factory()->create();
        $this->get("/users/{$user->id}/edit")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_update()
    {
        $user = User::factory()->create();
        $this->put("/users/{$user->id}", [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_destroy()
    {
        $user = User::factory()->create();
        $this->delete("/users/{$user->id}")->assertRedirect('/login');
    }

    public function test_developer_can_view_users_index()
    {
        $developer = $this->developer();
        User::factory()->count(3)->create();

        $response = $this->actingAs($developer)->get('/users');

        $response->assertStatus(200);
        $response->assertViewHas('users');
        $this->assertCount(4, $response->viewData('users'));
    }

    public function test_developer_can_create_user()
    {
        $developer = $this->developer();
        $data = $this->validUserData();

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_developer_can_view_user()
    {
        $developer = $this->developer();
        $user = User::factory()->create();

        $response = $this->actingAs($developer)->get("/users/{$user->id}");

        $response->assertStatus(200);
        $response->assertViewHas('user');
    }

    public function test_developer_can_edit_user()
    {
        $developer = $this->developer();
        $user = User::factory()->create();

        $response = $this->actingAs($developer)->get("/users/{$user->id}/edit");

        $response->assertStatus(200);
        $response->assertViewHas('user');
    }

    public function test_developer_can_update_user()
    {
        $developer = $this->developer();
        $user = User::factory()->create(['name' => 'Old Name', 'role' => 'lawyer']);

        $response = $this->actingAs($developer)->put("/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'lawyer',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('Updated Name', $user->fresh()->name);
    }

    public function test_developer_can_delete_user()
    {
        $developer = $this->developer();
        $user = User::factory()->create(['role' => 'lawyer']);

        $response = $this->actingAs($developer)->delete("/users/{$user->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertModelMissing($user);
    }

    public function test_admin_can_view_users_index()
    {
        $admin = $this->admin();
        User::factory()->count(2)->create();

        $response = $this->actingAs($admin)->get('/users');

        $response->assertStatus(200);
        $this->assertCount(3, $response->viewData('users'));
    }

    public function test_lawyer_cannot_access_users_index()
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $response = $this->actingAs($lawyer)->get('/users');

        $response->assertStatus(403);
    }

    public function test_user_validation_name_required()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['name' => '']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('name');
    }

    public function test_user_validation_email_required()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['email' => '']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_validation_email_must_be_valid()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['email' => 'not-email']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_validation_email_unique()
    {
        $developer = $this->developer();
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $data = $this->validUserData(['email' => 'taken@example.com']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_validation_role_required()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['role' => '']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('role');
    }

    public function test_user_validation_role_must_be_valid()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['role' => 'superadmin']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('role');
    }

    public function test_user_validation_role_developer_passes()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['role' => 'developer']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionDoesntHaveErrors('role');
    }

    public function test_user_validation_role_admin_passes()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['role' => 'admin']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionDoesntHaveErrors('role');
    }

    public function test_user_validation_role_lawyer_passes()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['role' => 'lawyer']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionDoesntHaveErrors('role');
    }

    public function test_user_validation_role_staff_passes()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['role' => 'staff']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionDoesntHaveErrors('role');
    }

    public function test_user_validation_role_client_passes()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['role' => 'client']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionDoesntHaveErrors('role');
    }

    public function test_user_validation_password_required()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['password' => '', 'password_confirmation' => '']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_validation_password_must_be_confirmed()
    {
        $developer = $this->developer();
        $data = $this->validUserData(['password_confirmation' => 'different']);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_validation_password_must_be_strong()
    {
        $developer = $this->developer();
        $data = $this->validUserData([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_validation_password_requires_uppercase()
    {
        $developer = $this->developer();
        $data = $this->validUserData([
            'password' => 'weakpass@1',
            'password_confirmation' => 'weakpass@1',
        ]);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_validation_password_requires_lowercase()
    {
        $developer = $this->developer();
        $data = $this->validUserData([
            'password' => 'WEAKPASS@1',
            'password_confirmation' => 'WEAKPASS@1',
        ]);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_validation_password_requires_digit()
    {
        $developer = $this->developer();
        $data = $this->validUserData([
            'password' => 'Strong@Pass',
            'password_confirmation' => 'Strong@Pass',
        ]);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_validation_password_requires_special_character()
    {
        $developer = $this->developer();
        $data = $this->validUserData([
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ]);

        $response = $this->actingAs($developer)->post('/users', $data);

        $response->assertSessionHasErrors('password');
    }

    public function test_cannot_delete_own_account()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->delete("/users/{$developer->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $developer->id]);
    }

    public function test_cannot_delete_last_admin()
    {
        $developer = $this->developer();
        $admin = $this->admin();

        $response = $this->actingAs($developer)->delete("/users/{$admin->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_cannot_delete_last_developer()
    {
        $developer = $this->developer();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete("/users/{$developer->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $developer->id]);
    }

    public function test_can_delete_admin_when_multiple_admins_exist()
    {
        $developer = $this->developer();
        $admin1 = $this->admin();
        $admin2 = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($developer)->delete("/users/{$admin1->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertModelMissing($admin1);
    }

    public function test_can_delete_developer_when_multiple_developers_exist()
    {
        $admin = $this->admin();
        $dev1 = $this->developer();
        $dev2 = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($admin)->delete("/users/{$dev1->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertModelMissing($dev1);
    }

    public function test_user_password_is_hashed_when_created()
    {
        $developer = $this->developer();

        $this->actingAs($developer)->post('/users', $this->validUserData([
            'email' => 'hashcheck@example.com',
        ]));

        $user = User::where('email', 'hashcheck@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotEquals('Strong@123', $user->password);
        $this->assertTrue(Hash::check('Strong@123', $user->password));
    }

    public function test_can_search_users_by_name()
    {
        $developer = $this->developer();
        User::factory()->create(['name' => 'Searchable User']);
        User::factory()->create(['name' => 'Other User']);

        $response = $this->actingAs($developer)->get('/users?search=Searchable');

        $response->assertStatus(200);
        $users = $response->viewData('users');
        $this->assertCount(1, $users);
        $this->assertEquals('Searchable User', $users->first()->name);
    }

    public function test_can_search_users_by_email()
    {
        $developer = $this->developer();
        User::factory()->create(['email' => 'unique@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $response = $this->actingAs($developer)->get('/users?search=unique');

        $response->assertStatus(200);
        $users = $response->viewData('users');
        $this->assertCount(1, $users);
    }

    public function test_can_filter_users_by_role()
    {
        $developer = $this->developer();
        User::factory()->create(['role' => 'lawyer']);
        User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($developer)->get('/users?role=lawyer');

        $response->assertStatus(200);
        $users = $response->viewData('users');
        $this->assertCount(1, $users);
        $this->assertEquals('lawyer', $users->first()->role);
    }
}
