<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        $user = User::factory()->create(['role' => 'developer']);
        $user->is_active = true;
        $user->save();
        return $user;
    }

    public function test_guest_redirected_to_login_on_index()
    {
        $this->get('/clients')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_create()
    {
        $this->get('/clients/create')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_store()
    {
        $this->post('/clients', [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_show()
    {
        $client = Client::factory()->create();
        $this->get("/clients/{$client->id}")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_edit()
    {
        $client = Client::factory()->create();
        $this->get("/clients/{$client->id}/edit")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_update()
    {
        $client = Client::factory()->create();
        $this->put("/clients/{$client->id}", [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_destroy()
    {
        $client = Client::factory()->create();
        $this->delete("/clients/{$client->id}")->assertRedirect('/login');
    }

    public function test_developer_can_view_clients_index()
    {
        $developer = $this->developer();
        Client::factory()->count(3)->create();

        $response = $this->actingAs($developer)->get('/clients');

        $response->assertStatus(200);
        $response->assertViewHas('clients');
        $this->assertCount(3, $response->viewData('clients'));
    }

    public function test_developer_can_create_client()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/clients', [
            'name' => 'Test Client',
            'type' => 'individual',
            'phone' => '123456789',
            'email' => 'client@example.com',
            'address' => '123 Test St',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('clients', ['name' => 'Test Client']);
    }

    public function test_developer_can_view_client()
    {
        $developer = $this->developer();
        $client = Client::factory()->create(['name' => 'View Test']);

        $response = $this->actingAs($developer)->get("/clients/{$client->id}");

        $response->assertStatus(200);
        $response->assertViewHas('client');
    }

    public function test_developer_can_edit_client()
    {
        $developer = $this->developer();
        $client = Client::factory()->create();

        $response = $this->actingAs($developer)->get("/clients/{$client->id}/edit");

        $response->assertStatus(200);
        $response->assertViewHas('client');
    }

    public function test_developer_can_update_client()
    {
        $developer = $this->developer();
        $client = Client::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($developer)->put("/clients/{$client->id}", [
            'name' => 'Updated Name',
            'type' => 'company',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('Updated Name', $client->fresh()->name);
    }

    public function test_developer_can_delete_client()
    {
        $developer = $this->developer();
        $client = Client::factory()->create();

        $response = $this->actingAs($developer)->delete("/clients/{$client->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted($client);
    }

    public function test_client_validation_name_required()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/clients', [
            'name' => '',
            'type' => 'individual',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_client_validation_type_must_be_individual_or_company()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/clients', [
            'name' => 'Test',
            'type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_client_validation_type_individual_passes()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/clients', [
            'name' => 'Test',
            'type' => 'individual',
        ]);

        $response->assertSessionDoesntHaveErrors('type');
    }

    public function test_client_validation_type_company_passes()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/clients', [
            'name' => 'Test',
            'type' => 'company',
        ]);

        $response->assertSessionDoesntHaveErrors('type');
    }

    public function test_client_validation_email_must_be_valid_email()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/clients', [
            'name' => 'Test',
            'type' => 'individual',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_can_search_clients_by_name()
    {
        $developer = $this->developer();
        Client::factory()->create(['name' => 'Alpha Client']);
        Client::factory()->create(['name' => 'Beta Client']);

        $response = $this->actingAs($developer)->get('/clients?search=Alpha');

        $response->assertStatus(200);
        $clients = $response->viewData('clients');
        $this->assertCount(1, $clients);
        $this->assertEquals('Alpha Client', $clients->first()->name);
    }

    public function test_search_returns_all_when_no_search_term()
    {
        $developer = $this->developer();
        Client::factory()->count(2)->create();

        $response = $this->actingAs($developer)->get('/clients');

        $response->assertStatus(200);
        $this->assertCount(2, $response->viewData('clients'));
    }

    public function test_can_view_trashed_clients()
    {
        $developer = $this->developer();
        $client = Client::factory()->create();
        $client->delete();

        $response = $this->actingAs($developer)->get('/clients/trashed');

        $response->assertStatus(200);
        $response->assertViewHas('clients');
        $this->assertCount(1, $response->viewData('clients'));
        $this->assertTrue($response->viewData('clients')->first()->trashed());
    }

    public function test_can_restore_trashed_client()
    {
        $developer = $this->developer();
        $client = Client::factory()->create();
        $client->delete();

        $response = $this->actingAs($developer)->post("/clients/{$client->id}/restore");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertFalse($client->fresh()->trashed());
    }

    public function test_ajax_store_creates_client_with_individual_type()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/clients/ajax', [
            'name' => 'AJAX Client',
            'phone' => '555-0100',
            'email' => 'ajax@example.com',
        ]);

        $response->assertJson([
            'name' => 'AJAX Client',
        ]);
        $this->assertDatabaseHas('clients', ['name' => 'AJAX Client']);
    }

    public function test_ajax_store_requires_name()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/clients/ajax', []);

        $response->assertSessionHasErrors('name');
    }

    public function test_lawyer_can_access_clients_index()
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        Client::factory()->count(2)->create();

        $response = $this->actingAs($lawyer)->get('/clients');

        $response->assertStatus(200);
        $this->assertCount(2, $response->viewData('clients'));
    }

    public function test_staff_can_access_clients_index()
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        Client::factory()->create();

        $response = $this->actingAs($staff)->get('/clients');

        $response->assertStatus(200);
    }

    public function test_client_role_cannot_access_clients_index()
    {
        $clientUser = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($clientUser)->get('/clients');

        $response->assertStatus(403);
    }
}
