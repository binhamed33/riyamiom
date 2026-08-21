<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function prepRule(array $overrides = []): Automation
    {
        return Automation::create(array_merge([
            'name' => 'تحضير الجلسات',
            'trigger' => 'session_approaching',
            'conditions' => [['field' => 'days_until_session', 'operator' => 'lte', 'value' => 3]],
            'actions' => [[
                'type' => 'create_task', 'title' => 'تحضير جلسة {date} — {case}',
                'priority' => 'high', 'assign' => 'case_lawyer', 'due_in_days' => 1,
            ]],
            'is_active' => true,
        ], $overrides));
    }

    public function test_manager_can_create_rule_and_lawyer_is_blocked_without_permission(): void
    {
        $admin = $this->admin();
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $payload = [
            'name' => 'قاعدة اختبار',
            'trigger' => 'session_approaching',
            'conditions' => [['field' => 'days_until_session', 'operator' => 'lte', 'value' => '3']],
            'actions' => [['type' => 'create_task', 'title' => 'استعد', 'priority' => 'high', 'assign' => 'case_lawyer', 'due_in_days' => '1']],
        ];

        // اصطلاح التطبيق: غير المصرح له يعاد للوحة التحكم برسالة خطأ (بدل 403 صريحة)
        $this->actingAs($lawyer)->get(route('automations.index'))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($lawyer)->post(route('automations.store'), $payload)
            ->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('automations', 0);

        $this->actingAs($admin)->post(route('automations.store'), $payload)->assertRedirect(route('automations.index'));
        $this->assertDatabaseHas('automations', ['name' => 'قاعدة اختبار', 'is_active' => true]);

        // منح المحامي الصلاحية صراحةً يفتح الوصول
        $lawyer->givePermission('automations.manage');
        $this->actingAs($lawyer)->get(route('automations.index'))->assertOk();
    }

    public function test_rule_rejects_unknown_trigger_and_action(): void
    {
        $this->actingAs($this->admin())->post(route('automations.store'), [
            'name' => 'خبيثة',
            'trigger' => 'evil_trigger',
            'actions' => [['type' => 'run_shell']],
        ])->assertSessionHasErrors(['trigger', 'actions.0.type']);
    }

    public function test_engine_creates_prep_task_and_is_idempotent(): void
    {
        Setting::set('automation_enabled', '1');
        $rule = $this->prepRule();

        $case = LegalCase::factory()->create();
        Session::factory()->create([
            'case_id' => $case->id,
            'date' => now()->addDays(2),
            'status' => 'upcoming',
        ]);

        $engine = new AutomationEngine();
        $first = $engine->runScheduled();
        $this->assertSame(1, $first['executed']);

        $this->assertDatabaseHas('tasks', ['case_id' => $case->id, 'assigned_to' => $case->lawyer_id]);
        $this->assertStringContainsString('تحضير جلسة', Task::where('case_id', $case->id)->first()->title);
        $this->assertDatabaseHas('automation_runs', [
            'automation_id' => $rule->id, 'status' => 'success', 'case_id' => $case->id,
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $case->lawyer_id]);

        // الجولة الثانية: لا تكرار (Idempotency عبر dedupe_key)
        $second = $engine->runScheduled();
        $this->assertSame(0, $second['executed']);
        $this->assertSame(1, Task::where('case_id', $case->id)->count());
    }

    public function test_conditions_filter_subjects(): void
    {
        $rule = $this->prepRule([
            'conditions' => [
                ['field' => 'days_until_session', 'operator' => 'lte', 'value' => 3],
                ['field' => 'case_status', 'operator' => 'equals', 'value' => 'closed'],
            ],
        ]);

        $case = LegalCase::factory()->create(['status' => 'active']);
        Session::factory()->create(['case_id' => $case->id, 'date' => now()->addDay(), 'status' => 'upcoming']);

        $this->assertSame(0, (new AutomationEngine())->dryRun($rule));

        $case->update(['status' => 'closed']);
        $this->assertSame(1, (new AutomationEngine())->dryRun($rule->fresh()));
    }

    public function test_inactive_rule_does_not_run_and_toggle_works(): void
    {
        Setting::set('automation_enabled', '1');
        $rule = $this->prepRule(['is_active' => false]);

        $case = LegalCase::factory()->create();
        Session::factory()->create(['case_id' => $case->id, 'date' => now()->addDay(), 'status' => 'upcoming']);

        $this->assertSame(0, (new AutomationEngine())->runScheduled()['executed']);
        $this->assertSame(0, Task::count());

        $this->actingAs($this->admin())->post(route('automations.toggle', $rule))->assertRedirect();
        $this->assertTrue($rule->fresh()->is_active);
    }

    public function test_failed_action_is_logged_not_silent(): void
    {
        // إجراء إشعار بلا أي مستخدم إداري نشط → فشل مسجَّل
        $rule = Automation::create([
            'name' => 'فشل متعمد',
            'trigger' => 'case_stale',
            'conditions' => [],
            'actions' => [['type' => 'notify', 'target' => 'manager', 'message' => 'x']],
            'is_active' => true,
        ]);

        $case = LegalCase::factory()->create(['status' => 'active']);
        LegalCase::where('id', $case->id)->update(['updated_at' => now()->subDays(30)]);
        User::query()->update(['is_active' => false]); // لا مديرين نشطين

        $result = (new AutomationEngine())->runRule($rule);

        $this->assertSame(1, $result['failed']);
        $this->assertDatabaseHas('automation_runs', ['automation_id' => $rule->id, 'status' => 'failed']);
        $this->assertNotNull(AutomationRun::where('status', 'failed')->first()->error);
    }

    public function test_case_created_event_trigger_fires(): void
    {
        Setting::set('automation_enabled', '1');
        Automation::create([
            'name' => 'استقبال قضية',
            'trigger' => 'case_created',
            'conditions' => [],
            'actions' => [['type' => 'create_task', 'title' => 'مراجعة أولية — {case}', 'assign' => 'case_lawyer', 'due_in_days' => 2]],
            'is_active' => true,
        ]);

        $case = LegalCase::factory()->create();
        AutomationEngine::fire('case_created', $case);

        $this->assertDatabaseHas('tasks', ['case_id' => $case->id]);
        $this->assertStringContainsString('مراجعة أولية', Task::first()->title);

        // إعادة النداء لا تكرر
        AutomationEngine::fire('case_created', $case);
        $this->assertSame(1, Task::count());
    }

    public function test_engine_disabled_blocks_event_triggers(): void
    {
        Setting::set('automation_enabled', '0');
        Automation::create([
            'name' => 'معطلة بالمفتاح', 'trigger' => 'case_created', 'conditions' => [],
            'actions' => [['type' => 'create_task', 'title' => 'x', 'assign' => 'case_lawyer']],
            'is_active' => true,
        ]);

        AutomationEngine::fire('case_created', LegalCase::factory()->create());
        $this->assertSame(0, Task::count());
    }

    public function test_seed_defaults_creates_three_editable_rules_once(): void
    {
        $this->actingAs($this->admin())->post(route('automations.seed'))->assertRedirect();
        $this->assertSame(3, Automation::count());

        $this->actingAs($this->admin())->post(route('automations.seed'))->assertRedirect();
        $this->assertSame(3, Automation::count());
    }

    public function test_due_reminders_notify_target_once(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();
        $reminder = \App\Models\CaseReminder::create([
            'case_id' => $case->id,
            'title' => 'متابعة موعد الجلسة',
            'remind_at' => now()->subHour(),
            'target' => 'manager',
        ]);

        $engine = new AutomationEngine();
        $this->assertSame(1, $engine->processDueReminders());
        $this->assertNotNull($reminder->fresh()->notified_at);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('automation_runs', ['trigger' => 'reminder', 'status' => 'success', 'case_id' => $case->id]);

        // لا إرسال مكرر
        $this->assertSame(0, $engine->processDueReminders());
    }

    public function test_runs_history_page_renders_with_records(): void
    {
        $rule = $this->prepRule();
        AutomationRun::create([
            'automation_id' => $rule->id, 'trigger' => $rule->trigger,
            'status' => 'success', 'summary' => 'مهمة: تجربة', 'dedupe_key' => 'k1',
        ]);

        $this->actingAs($this->admin())->get(route('automations.runs'))
            ->assertOk()->assertSee('تحضير الجلسات', false)->assertSee('نجح', false);
    }

    public function test_delete_rule_keeps_history(): void
    {
        $rule = $this->prepRule();
        AutomationRun::create(['automation_id' => $rule->id, 'trigger' => $rule->trigger, 'status' => 'success', 'dedupe_key' => 'k2']);

        $this->actingAs($this->admin())->delete(route('automations.destroy', $rule))->assertRedirect();

        $this->assertDatabaseMissing('automations', ['id' => $rule->id]);
        $this->assertSame(1, AutomationRun::count());
    }
}
