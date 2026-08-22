<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseTest extends TestCase
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

    private function client(): Client
    {
        return Client::factory()->create();
    }

    private function caseData(array $overrides = []): array
    {
        $client = $this->client();
        $lawyer = $this->lawyer();
        return array_merge([
            'case_number' => 'CASE-' . uniqid(),
            'title' => 'Test Case',
            'description' => 'Case description here',
            'type' => 'civil',
            'court' => 'High Court',
            'opponent' => 'Opponent Name',
            'status' => 'active',
            'priority' => 'medium',
            'opened_at' => '2024-01-15',
            'next_date' => '2024-06-15',
            'client_id' => $client->id,
            'lawyer_id' => $lawyer->id,
        ], $overrides);
    }

    public function test_guest_redirected_to_login_on_index()
    {
        $this->get('/cases')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_create()
    {
        $this->get('/cases/create')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_store()
    {
        $this->post('/cases', [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_show()
    {
        $case = LegalCase::factory()->create();
        $this->get("/cases/{$case->id}")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_edit()
    {
        $case = LegalCase::factory()->create();
        $this->get("/cases/{$case->id}/edit")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_update()
    {
        $case = LegalCase::factory()->create();
        $this->put("/cases/{$case->id}", [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_destroy()
    {
        $case = LegalCase::factory()->create();
        $this->delete("/cases/{$case->id}")->assertRedirect('/login');
    }

    public function test_developer_can_view_cases_index()
    {
        $developer = $this->developer();
        LegalCase::factory()->count(2)->create();

        $response = $this->actingAs($developer)->get('/cases');

        $response->assertStatus(200);
        $response->assertViewHas('cases');
        $this->assertCount(2, $response->viewData('cases'));
    }

    public function test_developer_can_create_case()
    {
        $developer = $this->developer();
        $data = $this->caseData();

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('cases', ['case_number' => $data['case_number']]);
    }

    public function test_developer_can_view_case()
    {
        $developer = $this->developer();
        $case = LegalCase::factory()->create();

        $response = $this->actingAs($developer)->get("/cases/{$case->id}");

        $response->assertStatus(200);
        $response->assertViewHas('case');
    }

    public function test_developer_can_edit_case()
    {
        $developer = $this->developer();
        $case = LegalCase::factory()->create();

        $response = $this->actingAs($developer)->get("/cases/{$case->id}/edit");

        $response->assertStatus(200);
        $response->assertViewHas('case');
    }

    public function test_developer_can_update_case()
    {
        $developer = $this->developer();
        $case = LegalCase::factory()->create(['title' => 'Original Title']);

        $response = $this->actingAs($developer)->put("/cases/{$case->id}", $this->caseData([
            'title' => 'Updated Title',
            'case_number' => $case->case_number,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('Updated Title', $case->fresh()->title);
    }

    public function test_developer_can_delete_case()
    {
        $developer = $this->developer();
        $case = LegalCase::factory()->create();

        $response = $this->actingAs($developer)->delete("/cases/{$case->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted($case);
    }

    public function test_lawyer_can_only_see_own_cases_in_index()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = $this->client();

        $case1 = LegalCase::factory()->create(['lawyer_id' => $lawyer1->id, 'client_id' => $client->id]);
        $case2 = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);

        $response = $this->actingAs($lawyer1)->get('/cases');

        $response->assertStatus(200);
        $cases = $response->viewData('cases');
        $this->assertCount(1, $cases);
        $this->assertEquals($case1->id, $cases->first()->id);
    }

    public function test_lawyer_cannot_view_other_lawyer_case()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = $this->client();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);

        $response = $this->actingAs($lawyer1)->get("/cases/{$case->id}");

        $response->assertStatus(403);
    }

    public function test_lawyer_cannot_edit_other_lawyer_case()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = $this->client();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);

        $response = $this->actingAs($lawyer1)->get("/cases/{$case->id}/edit");

        $response->assertStatus(403);
    }

    public function test_lawyer_cannot_update_other_lawyer_case()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = $this->client();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);

        $response = $this->actingAs($lawyer1)->put("/cases/{$case->id}", $this->caseData([
            'case_number' => $case->case_number,
        ]));

        $response->assertStatus(403);
    }

    public function test_lawyer_cannot_delete_other_lawyer_case()
    {
        $lawyer1 = $this->lawyer();
        $lawyer2 = $this->lawyer();
        $client = $this->client();
        $case = LegalCase::factory()->create(['lawyer_id' => $lawyer2->id, 'client_id' => $client->id]);

        $response = $this->actingAs($lawyer1)->delete("/cases/{$case->id}");

        $response->assertStatus(403);
    }

    /** رقم القضية مطلوب: هو مفتاحها لدى المحكمة ولا يُخترع نيابةً عن المكتب. */
    public function test_a_case_cannot_be_opened_without_a_number()
    {
        $developer = $this->developer();
        $data = $this->caseData(['case_number' => '']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasErrors('case_number');
    }

    public function test_case_validation_case_number_unique()
    {
        $developer = $this->developer();
        $existing = LegalCase::factory()->create();
        $data = $this->caseData(['case_number' => $existing->case_number]);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasErrors('case_number');
    }

    /**
     * العنوان اختياري بتصميم النظام: مكتب يفتح قضية على عجل يعرف رقمها
     * ولا يملك عنواناً بعد. حين يُترك، يصير الرقم عنواناً بدل أن يُمنع
     * المستخدم من المتابعة.
     */
    public function test_a_case_without_a_title_takes_its_number_as_one()
    {
        $developer = $this->developer();
        $data = $this->caseData(['title' => '']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cases', [
            'case_number' => $data['case_number'],
            'title' => $data['case_number'],
        ]);
    }

    public function test_case_validation_description_required()
    {
        $developer = $this->developer();
        $data = $this->caseData(['description' => '']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasErrors('description');
    }

    /** نوع القضية اختياري — يُملأ لاحقاً من صفحة القضية. */
    public function test_a_case_can_be_opened_before_its_type_is_known()
    {
        $developer = $this->developer();
        $data = $this->caseData(['type' => '', 'case_type' => '']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cases', ['case_number' => $data['case_number']]);
    }

    public function test_case_validation_court_required()
    {
        $developer = $this->developer();
        $data = $this->caseData(['court' => '']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasErrors('court');
    }

    public function test_case_validation_status_must_be_valid()
    {
        $developer = $this->developer();
        $data = $this->caseData(['status' => 'invalid_status']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasErrors('status');
    }

    public function test_case_validation_priority_must_be_valid()
    {
        $developer = $this->developer();
        $data = $this->caseData(['priority' => 'invalid']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasErrors('priority');
    }

    /** تاريخ الفتح يُملأ باليوم حين لا يُذكر — لا يُمنع فتح القضية لأجله. */
    public function test_a_case_without_an_opening_date_opens_today()
    {
        $developer = $this->developer();
        $data = $this->caseData(['opened_at' => '']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasNoErrors();

        $case = LegalCase::where('case_number', $data['case_number'])->firstOrFail();
        $this->assertSame(now()->toDateString(), $case->opened_at->toDateString());
    }

    public function test_case_validation_client_id_required()
    {
        $developer = $this->developer();
        $data = $this->caseData(['client_id' => '']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_case_validation_client_id_must_exist()
    {
        $developer = $this->developer();
        $data = $this->caseData(['client_id' => 99999]);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasErrors('client_id');
    }

    /**
     * تاريخ الجلسة القادمة ليس حقلاً يُكتب في نموذج القضية — يُشتقّ من
     * الجلسات المسجَّلة. فلا يُقبل من جسم الطلب ولو أُرسل.
     */
    public function test_the_next_hearing_date_is_not_taken_from_the_case_form()
    {
        $developer = $this->developer();
        $data = $this->caseData(['next_date' => '2024-01-15']);

        $response = $this->actingAs($developer)->post('/cases', $data);

        $response->assertSessionHasNoErrors();

        $case = LegalCase::where('case_number', $data['case_number'])->firstOrFail();
        $this->assertNull($case->next_date);
    }

    public function test_can_search_cases_by_case_number()
    {
        $developer = $this->developer();
        $case = LegalCase::factory()->create(['case_number' => 'UNIQUE-123']);
        LegalCase::factory()->create(['case_number' => 'OTHER-456']);

        $response = $this->actingAs($developer)->get('/cases?search=UNIQUE-123');

        $response->assertStatus(200);
        $cases = $response->viewData('cases');
        $this->assertCount(1, $cases);
        $this->assertEquals($case->id, $cases->first()->id);
    }

    public function test_can_search_cases_by_title()
    {
        $developer = $this->developer();
        $case = LegalCase::factory()->create(['title' => 'Specific Title Case']);
        LegalCase::factory()->create(['title' => 'Another Title']);

        $response = $this->actingAs($developer)->get('/cases?search=Specific');

        $response->assertStatus(200);
        $cases = $response->viewData('cases');
        $this->assertCount(1, $cases);
        $this->assertEquals($case->id, $cases->first()->id);
    }

    public function test_auto_detect_overdue_marks_active_cases_with_past_next_date()
    {
        $developer = $this->developer();
        $overdueCase = LegalCase::factory()->create([
            'status' => 'active',
            'next_date' => now()->subDay(),
        ]);
        $activeCase = LegalCase::factory()->create([
            'status' => 'active',
            'next_date' => now()->addDay(),
        ]);

        $response = $this->actingAs($developer)->post('/cases/detect-overdue');

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('overdue', $overdueCase->fresh()->status);
        $this->assertEquals('active', $activeCase->fresh()->status);
    }

    public function test_auto_detect_overdue_does_not_change_non_active_cases()
    {
        $developer = $this->developer();
        $pendingCase = LegalCase::factory()->create([
            'status' => 'pending',
            'next_date' => now()->subDay(),
        ]);

        $this->actingAs($developer)->post('/cases/detect-overdue');

        $this->assertEquals('pending', $pendingCase->fresh()->status);
    }

    public function test_summarize_endpoint_returns_json()
    {
        $developer = $this->developer();
        $client = $this->client();
        $lawyer = $this->lawyer();
        $case = LegalCase::factory()->create([
            'client_id' => $client->id,
            'lawyer_id' => $lawyer->id,
        ]);

        $response = $this->actingAs($developer)->get("/cases/{$case->id}/summarize");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id', 'case_number', 'title', 'status', 'priority',
            'client', 'lawyer', 'opened_at', 'next_date',
            'sessions', 'tasks', 'documents',
        ]);
    }

    public function test_staff_can_access_cases()
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        LegalCase::factory()->count(2)->create();

        $response = $this->actingAs($staff)->get('/cases');

        $response->assertStatus(200);
        $this->assertCount(2, $response->viewData('cases'));
    }

    public function test_client_role_cannot_access_cases_index()
    {
        $clientUser = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($clientUser)->get('/cases');

        // المنع يردّ إلى لوحة المتابعة برسالة «غير مصرح لك بالوصول»،
        // لا برمز 403 عارٍ. نفحص المنع نفسه لا رمزه.
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_can_filter_cases_by_status()
    {
        $developer = $this->developer();
        LegalCase::factory()->create(['status' => 'active']);
        LegalCase::factory()->create(['status' => 'closed']);

        $response = $this->actingAs($developer)->get('/cases?status=active');

        $response->assertStatus(200);
        $cases = $response->viewData('cases');
        $this->assertCount(1, $cases);
        $this->assertEquals('active', $cases->first()->status);
    }

    public function test_can_filter_cases_by_priority()
    {
        $developer = $this->developer();
        LegalCase::factory()->create(['priority' => 'high']);
        LegalCase::factory()->create(['priority' => 'low']);

        $response = $this->actingAs($developer)->get('/cases?priority=high');

        $response->assertStatus(200);
        $cases = $response->viewData('cases');
        $this->assertCount(1, $cases);
        $this->assertEquals('high', $cases->first()->priority);
    }

    public function test_can_view_trashed_cases()
    {
        $developer = $this->developer();
        $case = LegalCase::factory()->create();
        $case->delete();

        $response = $this->actingAs($developer)->get('/cases/trashed');

        $response->assertStatus(200);
        $response->assertViewHas('cases');
        $this->assertCount(1, $response->viewData('cases'));
    }

    public function test_can_restore_trashed_case()
    {
        $developer = $this->developer();
        $case = LegalCase::factory()->create();
        $case->delete();

        $response = $this->actingAs($developer)->post("/cases/{$case->id}/restore");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertFalse($case->fresh()->trashed());
    }
}
