<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseTimelineAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_case_update_audit_carries_case_id_and_shows_old_new_in_timeline(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create(['status' => 'active']);

        $this->actingAs($admin)->put(route('cases.update', $case), [
            'case_number' => $case->case_number ?: 'ق/1/2026',
            'title' => $case->title,
            'description' => $case->description ?: 'وصف القضية',
            'court' => $case->court ?: 'الابتدائية',
            'opponent' => $case->opponent ?: 'الخصم',
            'status' => 'closed',
            'priority' => $case->priority ?: 'medium',
            'client_id' => $case->client_id,
            'lawyer_id' => $case->lawyer_id,
        ])->assertSessionDoesntHaveErrors();

        $log = AuditLog::where('model_type', LegalCase::class)
            ->where('action', AuditLog::ACTION_UPDATE)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($case->id, $log->case_id);

        $page = $this->actingAs($admin)->get(route('cases.show', $case));
        $page->assertOk()->assertSee('عدّل القضية', false)->assertSee('مغلقة', false);
    }

    public function test_task_audit_derives_case_id_from_payload(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();

        $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'مهمة مرتبطة',
            'assigned_to' => $admin->id,
            'case_id' => $case->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $log = AuditLog::where('model_type', Task::class)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($case->id, $log->case_id);
    }

    public function test_document_delete_appears_in_case_timeline(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();
        $document = Document::factory()->create([
            'case_id' => $case->id,
            'title' => 'مذكرة الدفاع',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('documents.destroy', $document));

        $log = AuditLog::where('model_type', Document::class)
            ->where('action', AuditLog::ACTION_DELETE)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($case->id, $log->case_id);

        $this->actingAs($admin)->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('حذف المستند', false)
            ->assertSee('مذكرة الدفاع', false);
    }

    public function test_no_routes_exist_to_edit_audit_logs(): void
    {
        // سجل التدقيق للقراءة فقط: لا يوجد أي مسار تعديل أو حذف له
        $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri());

        $this->assertFalse($routes->contains(fn ($uri) => str_contains($uri, 'audit-log/')));
    }
}
