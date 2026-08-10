<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_activity_requires_auth()
    {
        $case = LegalCase::factory()->create();

        $this->post('/cases/' . $case->id . '/activities', [
            'type' => 'note',
            'title' => 'ملاحظة',
        ])->assertRedirect('/login');
    }

    public function test_store_activity_creates_record()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create();

        $response = $this->actingAs($developer)->post('/cases/' . $case->id . '/activities', [
            'type' => 'call',
            'title' => 'مكالمة مع الموكل',
            'content' => 'تم الاتفاق على التأجيل',
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertDatabaseHas('case_activities', [
            'case_id' => $case->id,
            'user_id' => $developer->id,
            'type' => 'call',
            'title' => 'مكالمة مع الموكل',
        ]);
    }

    public function test_store_activity_validation()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create();

        $response = $this->actingAs($developer)->post('/cases/' . $case->id . '/activities', [
            'type' => 'note',
            'title' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('title');
    }

    public function test_client_cannot_store_activity()
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $case = LegalCase::factory()->create();

        $response = $this->actingAs($client)->post('/cases/' . $case->id . '/activities', [
            'type' => 'note',
            'title' => 'محاولة',
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [302, 403]), 'Expected 302 or 403, got ' . $response->getStatusCode());
    }

    public function test_timeline_merges_all_kinds()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create();

        CaseActivity::create([
            'case_id' => $case->id,
            'user_id' => $developer->id,
            'type' => 'note',
            'title' => 'ملاحظة أولى',
        ]);
        \App\Models\Session::factory()->create(['case_id' => $case->id, 'location' => 'محكمة الرستاق']);
        \App\Models\Task::factory()->create(['case_id' => $case->id, 'title' => 'مهمة الرد', 'assigned_to' => $developer->id]);
        \App\Models\Document::factory()->create(['case_id' => $case->id, 'title' => 'عقد الوكالة']);

        $response = $this->actingAs($developer)->get('/cases/' . $case->id . '/timeline');

        $response->assertStatus(200);
        $kinds = array_column($response->json('events'), 'kind');
        $this->assertContains('activity', $kinds);
        $this->assertContains('session', $kinds);
        $this->assertContains('task', $kinds);
        $this->assertContains('document', $kinds);
    }
}