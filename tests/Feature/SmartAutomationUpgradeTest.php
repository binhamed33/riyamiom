<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\CaseTemplate;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use App\Support\AutomationAdvisor;
use App\Support\Revisions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ترقية القوالب الذكية والأتمتة: المكتبة، والنسخ، والاقتراحات،
 * ومشغّل تغيّر الحالة — كلها سلوك خادم حقيقي لا أزرار.
 */
class SmartAutomationUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function case(array $attrs = []): LegalCase
    {
        $client = Client::create(['name' => 'موكّل', 'phone' => '91234567', 'type' => 'individual']);

        return LegalCase::create(array_merge([
            'client_id' => $client->id,
            'case_number' => 'T-' . fake()->unique()->numberBetween(1, 99999),
            'title' => 'قضية اختبار',
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => LegalCase::STATUS_ACTIVE ?? 'active',
            'priority' => 'medium',
        ], $attrs));
    }

    // ── §7: القواعد الجاهزة صارت عشراً ──────────────────────────

    public function test_the_ready_pack_seeds_ten_rules_idempotently(): void
    {
        $this->assertSame(10, AutomationEngine::seedDefaults());
        $this->assertSame(0, AutomationEngine::seedDefaults(), 'الإعادة لا تكرّر');
        $this->assertSame(10, Automation::count());
    }

    // ── §14: مشغّل تغيّر حالة القضية ─────────────────────────────

    public function test_changing_a_case_status_fires_its_automations(): void
    {
        $admin = $this->admin();
        \App\Models\Setting::set('automation_enabled', '1', 'automation');
        AutomationEngine::seedByName('خطوات إغلاق القضية', $admin->id);
        $case = $this->case(['lawyer_id' => $admin->id]);

        $this->actingAs($admin)->put("/cases/{$case->id}", [
            'client_id' => $case->client_id,
            'case_number' => $case->case_number,
            'title' => $case->title,
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => 'closed',
            'priority' => 'medium',
        ]);

        $this->assertSame('closed', $case->fresh()->status);
        $this->assertTrue(
            Task::where('title', 'like', '%إغلاق ملف%')->exists(),
            'قاعدة الإغلاق لم تنشئ مهمة الإغلاق'
        );
    }

    public function test_the_closing_rule_ignores_other_status_changes(): void
    {
        $admin = $this->admin();
        \App\Models\Setting::set('automation_enabled', '1', 'automation');
        AutomationEngine::seedByName('خطوات إغلاق القضية', $admin->id);
        $case = $this->case(['lawyer_id' => $admin->id, 'status' => 'active']);

        $this->actingAs($admin)->put("/cases/{$case->id}", [
            'client_id' => $case->client_id,
            'case_number' => $case->case_number,
            'title' => $case->title,
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $this->assertFalse(Task::where('title', 'like', '%إغلاق ملف%')->exists());
    }

    // ── §3: مكتبة القوالب ────────────────────────────────────────

    public function test_the_template_library_seeds_six_and_respects_office_edits(): void
    {
        $this->assertSame(6, CaseTemplate::seedDefaults());

        $t = CaseTemplate::where('name', 'قضية عمالية')->first();
        $t->update(['description' => 'عدّلها المكتب']);

        $this->assertSame(0, CaseTemplate::seedDefaults(), 'الإعادة لا تكتب فوق تعديل المكتب');
        $this->assertSame('عدّلها المكتب', $t->fresh()->description);
    }

    public function test_a_library_template_actually_prepares_a_case(): void
    {
        CaseTemplate::seedDefaults();
        $admin = $this->admin();
        $case = $this->case(['lawyer_id' => $admin->id]);

        $created = CaseTemplate::where('name', 'قضية تنفيذ')->first()->applyTo($case, $admin->id);

        $this->assertGreaterThanOrEqual(3, $created['tasks']);
        $this->assertGreaterThanOrEqual(4, $created['checklist']);
        $this->assertGreaterThanOrEqual(6, $created['folders']);
    }

    // ── §27: النسخ والاستعادة ────────────────────────────────────

    public function test_editing_an_automation_keeps_a_version_and_restore_works(): void
    {
        $admin = $this->admin();
        AutomationEngine::seedByName('تحضير الجلسات القادمة', $admin->id);
        $rule = Automation::first();
        $originalName = $rule->name;

        $this->actingAs($admin)->put("/automations/{$rule->id}", [
            'name' => 'اسم معدَّل',
            'trigger' => $rule->trigger,
            'conditions' => collect($rule->conditions)->map(fn ($c) => ['field' => $c['field'], 'operator' => $c['operator'], 'value' => (string) $c['value']])->all(),
            'actions' => $rule->actions,
        ]);

        $this->assertSame('اسم معدَّل', $rule->fresh()->name);
        $revisions = Revisions::for($rule);
        $this->assertCount(1, $revisions);
        $this->assertSame($originalName, $revisions->first()->payload['name']);

        $this->actingAs($admin)->post("/automations/{$rule->id}/versions/1/restore");

        $this->assertSame($originalName, $rule->fresh()->name);
        $this->assertCount(2, Revisions::for($rule), 'الاستعادة نفسها تحفظ لقطة قبلها');
    }

    public function test_a_staff_member_cannot_touch_versions(): void
    {
        $admin = $this->admin();
        AutomationEngine::seedByName('تحضير الجلسات القادمة', $admin->id);
        $rule = Automation::first();
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($staff)->get("/automations/{$rule->id}/versions")->assertRedirect();
        $this->actingAs($staff)->post("/automations/{$rule->id}/versions/1/restore")->assertRedirect();
        $this->assertNotSame('غُيّر', $rule->fresh()->name);
    }

    // ── §28: نسخ قاعدة ───────────────────────────────────────────

    public function test_duplicating_an_automation_yields_a_disabled_copy(): void
    {
        $admin = $this->admin();
        AutomationEngine::seedByName('تحضير الجلسات القادمة', $admin->id);
        $rule = Automation::first();

        $this->actingAs($admin)->post("/automations/{$rule->id}/duplicate");

        $copy = Automation::where('name', $rule->name . ' (نسخة)')->first();
        $this->assertNotNull($copy);
        $this->assertFalse($copy->is_active, 'النسخة تولد معطَّلة');
        $this->assertSame(0, $copy->runs_count);
    }

    // ── §12: الاقتراحات من الاستخدام ─────────────────────────────

    public function test_overdue_tasks_produce_a_suggestion_and_accepting_creates_the_rule(): void
    {
        $admin = $this->admin();
        $case = $this->case(['lawyer_id' => $admin->id]);
        Task::create(['title' => 'م١', 'case_id' => $case->id, 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'pending', 'priority' => 'high', 'due_date' => now()->subDays(3)]);
        Task::create(['title' => 'م٢', 'case_id' => $case->id, 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'pending', 'priority' => 'high', 'due_date' => now()->subDays(5)]);

        $keys = array_column(AutomationAdvisor::suggestions(), 'key');
        $this->assertContains('overdue_task_nudge', $keys);

        $this->actingAs($admin)->post('/automations/suggestions/accept', ['key' => 'overdue_task_nudge'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Automation::where('name', 'تنبيه المهام المتأخرة')->where('is_active', true)->exists());
        $this->assertNotContains('overdue_task_nudge', array_column(AutomationAdvisor::suggestions(), 'key'),
            'قاعدة قائمة لا تُقترح مجدداً');
    }

    public function test_dismissing_a_suggestion_silences_it(): void
    {
        $admin = $this->admin();
        $case = $this->case(['lawyer_id' => $admin->id]);
        Task::create(['title' => 'م١', 'case_id' => $case->id, 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'pending', 'priority' => 'high', 'due_date' => now()->subDays(3)]);
        Task::create(['title' => 'م٢', 'case_id' => $case->id, 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'pending', 'priority' => 'high', 'due_date' => now()->subDays(5)]);

        $this->actingAs($admin)->post('/automations/suggestions/dismiss', ['key' => 'overdue_task_nudge']);

        $this->assertNotContains('overdue_task_nudge', array_column(AutomationAdvisor::suggestions(), 'key'));
        $this->assertSame(0, Automation::count(), 'التجاهل لا ينشئ شيئاً');
    }

    // ── §2/§11: مولد المسودات لا يتاح بلا إعداد ولا لغير المخوَّل ──

    public function test_the_ai_draft_endpoint_fails_clearly_when_unconfigured(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/automations/ai-draft', ['prompt' => 'إذا كانت الجلسة غداً أنشئ مهمة للمحامي']);

        $response->assertStatus(422);
        $this->assertNotSame('', (string) $response->json('error'), 'الرسالة تشرح لا ترمز');
        $this->assertSame(0, Automation::count(), 'لا يُحفظ شيء من المولد أبداً');
    }

    public function test_a_staff_member_cannot_reach_the_generators(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($staff)->postJson('/automations/ai-draft', ['prompt' => 'أي شيء عشرة أحرف'])
            ->assertStatus(403);
        $this->actingAs($staff)->postJson('/case-templates/ai-draft', ['prompt' => 'أي شيء عشرة أحرف'])
            ->assertStatus(403);
    }

    // ── §29: التصفية من الخادم ───────────────────────────────────

    public function test_the_index_filters_by_state_and_search(): void
    {
        $admin = $this->admin();
        AutomationEngine::seedDefaults($admin->id);
        Automation::where('name', 'تذكير جلسة الغد')->update(['is_active' => false]);

        $html = $this->actingAs($admin)->get('/automations?state=disabled')->getContent();
        $this->assertStringContainsString('تذكير جلسة الغد', $html);
        $this->assertStringNotContainsString('توثيق تغيّر حالة القضية', $html);

        $html = $this->actingAs($admin)->get('/automations?q=' . urlencode('الراكدة'))->getContent();
        $this->assertStringContainsString('تنبيه القضايا الراكدة', $html);
        $this->assertStringNotContainsString('متابعة ما بعد الجلسة', $html);
    }
}
