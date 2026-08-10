<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NaturalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_requires_auth()
    {
        $response = $this->postJson('/nl/actions/parse', ['message' => 'اتصلت بأحمد غداً']);

        $this->assertFalse((bool) $response->json('ok'));
    }

    public function test_parse_returns_extracted_actions()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->postJson('/nl/actions/parse', [
            'message' => 'اتصلت بأحمد وقال سيرسل صورة البطاقة غداً',
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertNotEmpty($response->json('actions'));
        $types = array_column($response->json('actions'), 'type');
        $this->assertContains('call', $types);
        $this->assertContains('task', $types);
    }

    public function test_confirm_creates_activity_for_call()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create(['status' => 'active']);

        $response = $this->actingAs($developer)->postJson('/nl/actions/confirm', [
            'case_id' => $case->id,
            'actions' => [
                ['type' => 'call', 'title' => 'اتصال مكتوب — أحمد', 'content' => 'تم الاتصال', 'due_date' => null],
            ],
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertDatabaseHas('case_activities', [
            'case_id' => $case->id,
            'type' => 'call',
            'title' => 'اتصال مكتوب — أحمد',
        ]);
    }

    public function test_confirm_creates_task_with_due_date()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create(['status' => 'active']);

        $response = $this->actingAs($developer)->postJson('/nl/actions/confirm', [
            'case_id' => $case->id,
            'actions' => [
                ['type' => 'task', 'title' => 'متابعة — صورة البطاقة', 'content' => 'بانتظار الصورة', 'due_date' => now()->addDay()->toDateString()],
            ],
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertDatabaseHas('tasks', [
            'case_id' => $case->id,
            'title' => 'متابعة — صورة البطاقة',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('case_activities', [
            'case_id' => $case->id,
            'type' => 'task',
        ]);
    }

    public function test_confirm_requires_case_for_activities()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->postJson('/nl/actions/confirm', [
            'actions' => [
                ['type' => 'note', 'title' => 'ملاحظة', 'content' => 'بدون قضية', 'due_date' => null],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_confirm_does_not_create_unselected_actions()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create(['status' => 'active']);

        $response = $this->actingAs($developer)->postJson('/nl/actions/confirm', [
            'case_id' => $case->id,
            'actions' => [
                ['type' => 'note', 'title' => 'ملاحظة مؤكدة', 'content' => null, 'due_date' => null],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('case_activities', ['case_id' => $case->id, 'title' => 'ملاحظة مؤكدة']);
        $this->assertSame(1, CaseActivity::where('case_id', $case->id)->count());
    }
}