<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function lawyer(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function createCase(array $overrides = []): LegalCase
    {
        $client = Client::factory()->create();
        $lawyer = $this->lawyer();
        return LegalCase::factory()->create(array_merge([
            'client_id' => $client->id,
            'lawyer_id' => $lawyer->id,
        ], $overrides));
    }

    private function sessionData(array $overrides = []): array
    {
        $case = $this->createCase();
        return array_merge([
            'case_id' => $case->id,
            'date' => now()->addDay()->format('Y-m-d H:i:s'),
            'location' => 'Courtroom 3',
            'status' => 'upcoming',
            'notes' => 'Bring all documents',
        ], $overrides);
    }

    public function test_guest_redirected_to_login_on_index()
    {
        $this->get('/sessions')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_create()
    {
        $this->get('/sessions/create')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_store()
    {
        $this->post('/sessions', [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_show()
    {
        $session = Session::factory()->create();
        $this->get("/sessions/{$session->id}")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_edit()
    {
        $session = Session::factory()->create();
        $this->get("/sessions/{$session->id}/edit")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_update()
    {
        $session = Session::factory()->create();
        $this->put("/sessions/{$session->id}", [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_destroy()
    {
        $session = Session::factory()->create();
        $this->delete("/sessions/{$session->id}")->assertRedirect('/login');
    }

    public function test_developer_can_view_sessions_index()
    {
        $developer = $this->developer();
        Session::factory()->count(2)->create();

        $response = $this->actingAs($developer)->get('/sessions');

        $response->assertStatus(200);
        $response->assertViewHas('sessions');
        $this->assertCount(2, $response->viewData('sessions'));
    }

    public function test_developer_can_create_session()
    {
        $developer = $this->developer();
        $data = $this->sessionData();

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('court_sessions', ['location' => 'Courtroom 3']);
    }

    public function test_developer_can_view_session()
    {
        $developer = $this->developer();
        $session = Session::factory()->create();

        $response = $this->actingAs($developer)->get("/sessions/{$session->id}");

        $response->assertStatus(200);
        $response->assertViewHas('session');
    }

    public function test_developer_can_edit_session()
    {
        $developer = $this->developer();
        $session = Session::factory()->create();

        $response = $this->actingAs($developer)->get("/sessions/{$session->id}/edit");

        $response->assertStatus(200);
        $response->assertViewHas('session');
    }

    public function test_developer_can_update_session()
    {
        $developer = $this->developer();
        $session = Session::factory()->create(['location' => 'Old Location']);

        $response = $this->actingAs($developer)->put("/sessions/{$session->id}", $this->sessionData([
            'location' => 'New Location',
            'case_id' => $session->case_id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('New Location', $session->fresh()->location);
    }

    public function test_developer_can_delete_session()
    {
        $developer = $this->developer();
        $session = Session::factory()->create();

        $response = $this->actingAs($developer)->delete("/sessions/{$session->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted($session);
    }

    public function test_session_validation_case_id_required()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['case_id' => '']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionHasErrors('case_id');
    }

    public function test_session_validation_case_id_must_exist()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['case_id' => 99999]);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionHasErrors('case_id');
    }

    public function test_session_validation_date_required()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['date' => '']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionHasErrors('date');
    }

    public function test_session_validation_location_required()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['location' => '']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionHasErrors('location');
    }

    public function test_session_validation_status_required()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['status' => '']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionHasErrors('status');
    }

    public function test_session_validation_status_must_be_valid()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['status' => 'invalid_status']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionHasErrors('status');
    }

    public function test_session_validation_status_upcoming_passes()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['status' => 'upcoming']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionDoesntHaveErrors('status');
    }

    public function test_session_validation_status_completed_passes()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['status' => 'completed']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionDoesntHaveErrors('status');
    }

    public function test_session_validation_status_postponed_passes()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['status' => 'postponed']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionDoesntHaveErrors('status');
    }

    public function test_session_validation_status_cancelled_passes()
    {
        $developer = $this->developer();
        $data = $this->sessionData(['status' => 'cancelled']);

        $response = $this->actingAs($developer)->post('/sessions', $data);

        $response->assertSessionDoesntHaveErrors('status');
    }

    public function test_lawyer_can_view_own_case_sessions()
    {
        $lawyer = $this->lawyer();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer->id, 'client_id' => $client->id]);
        $session = Session::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($lawyer)->get("/sessions/{$session->id}");

        $response->assertStatus(200);
    }

    public function test_lawyer_cannot_view_other_lawyer_case_session()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);
        $session = Session::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($lawyer1)->get("/sessions/{$session->id}");

        $response->assertStatus(403);
    }

    public function test_lawyer_cannot_edit_other_lawyer_case_session()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);
        $session = Session::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($lawyer1)->get("/sessions/{$session->id}/edit");

        $response->assertStatus(403);
    }

    public function test_lawyer_cannot_update_other_lawyer_case_session()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);
        $session = Session::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($lawyer1)->put("/sessions/{$session->id}", [
            'case_id' => $case->id,
            'date' => now()->addDay()->format('Y-m-d H:i:s'),
            'location' => 'Test',
            'status' => 'upcoming',
        ]);

        $response->assertStatus(403);
    }

    public function test_lawyer_cannot_delete_other_lawyer_case_session()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);
        $session = Session::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($lawyer1)->delete("/sessions/{$session->id}");

        $response->assertStatus(403);
    }

    public function test_can_filter_sessions_by_status()
    {
        $developer = $this->developer();
        Session::factory()->create(['status' => 'upcoming']);
        Session::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($developer)->get('/sessions?status=upcoming');

        $response->assertStatus(200);
        $sessions = $response->viewData('sessions');
        $this->assertCount(1, $sessions);
        $this->assertEquals('upcoming', $sessions->first()->status);
    }

    public function test_today_filter_shows_only_upcoming_sessions_for_today()
    {
        $developer = $this->developer();
        $todaySession = Session::factory()->create([
            'date' => now()->format('Y-m-d H:i:s'),
            'status' => 'upcoming',
        ]);
        $futureSession = Session::factory()->create([
            'date' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => 'upcoming',
        ]);
        $completedToday = Session::factory()->create([
            'date' => now()->format('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($developer)->get('/sessions/today/list');

        $response->assertStatus(200);
        $sessions = $response->viewData('sessions');
        $this->assertCount(1, $sessions);
        $this->assertEquals($todaySession->id, $sessions->first()->id);
    }

    public function test_staff_can_access_sessions()
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        Session::factory()->create();

        $response = $this->actingAs($staff)->get('/sessions');

        $response->assertStatus(200);
    }

    public function test_client_role_cannot_access_sessions_index()
    {
        $clientUser = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($clientUser)->get('/sessions');

        // المنع يردّ إلى لوحة المتابعة برسالة «غير مصرح لك بالوصول»،
        // لا برمز 403 عارٍ. نفحص المنع نفسه لا رمزه.
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_creating_session_sends_notification_to_case_lawyer()
    {
        $developer = $this->developer();
        $lawyer = $this->lawyer();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer->id, 'client_id' => $client->id]);

        $this->actingAs($developer)->post('/sessions', [
            'case_id' => $case->id,
            'date' => now()->addDay()->format('Y-m-d H:i:s'),
            'location' => 'Courtroom 1',
            'status' => 'upcoming',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $lawyer->id,
        ]);
    }
}
