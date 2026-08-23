<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تحكّم المدير في الأتمتة، وسجلٌّ يقول كم استغرق التنفيذ.
 *
 * «تعطيل الكل» أخطر ما في هذه الشاشة: زرٌّ واحد يمسّ كل القواعد. فأول
 * ما يُثبَت أنه لا يحذف واحدة.
 */
class AutomationControlTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function rules(int $active, int $inactive): void
    {
        for ($i = 0; $i < $active; $i++) {
            Automation::create(['name' => 'مفعّلة ' . $i, 'trigger' => 'session_upcoming',
                'conditions' => [], 'actions' => [], 'is_active' => true]);
        }
        for ($i = 0; $i < $inactive; $i++) {
            Automation::create(['name' => 'معطّلة ' . $i, 'trigger' => 'session_upcoming',
                'conditions' => [], 'actions' => [], 'is_active' => false]);
        }
    }

    public function test_disable_all_disables_without_deleting_a_single_rule(): void
    {
        $this->rules(3, 1);

        $this->actingAs($this->admin())
            ->post(route('automations.bulk'), ['action' => 'disable'])
            ->assertRedirect();

        $this->assertSame(4, Automation::count(), 'تعطيل الكل حذف قواعد');
        $this->assertSame(0, Automation::where('is_active', true)->count());
    }

    public function test_enable_all_turns_every_rule_back_on(): void
    {
        $this->rules(1, 3);

        $this->actingAs($this->admin())->post(route('automations.bulk'), ['action' => 'enable']);

        $this->assertSame(4, Automation::where('is_active', true)->count());
        $this->assertSame(4, Automation::count());
    }

    public function test_a_bulk_change_is_written_to_the_audit_log(): void
    {
        $this->rules(2, 0);

        $this->actingAs($this->admin())->post(route('automations.bulk'), ['action' => 'disable']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'automations.disable_all']);
    }

    public function test_a_bulk_change_on_nothing_says_so_instead_of_pretending(): void
    {
        $this->rules(2, 0);

        $this->actingAs($this->admin())
            ->post(route('automations.bulk'), ['action' => 'enable'])
            ->assertSessionHas('success', 'كل القواعد مفعّلة أصلاً.');

        // ولا يُكتب في السجل تغييرٌ لم يقع
        $this->assertDatabaseMissing('audit_logs', ['action' => 'automations.enable_all']);
    }

    public function test_staff_without_permission_cannot_touch_the_rules(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->rules(2, 0);

        // الحاجز في هذا النظام يُحوّل ولا يردّ 403 — والمهمّ أثره لا رمزه
        $response = $this->actingAs($lawyer)->post(route('automations.bulk'), ['action' => 'disable']);

        $this->assertTrue(
            in_array($response->status(), [302, 403], true),
            'محامٍ بلا صلاحية نفذ تعطيلاً جماعياً (الرمز ' . $response->status() . ')'
        );
        $this->assertSame(2, Automation::where('is_active', true)->count(),
            'القواعد تغيّرت رغم منع الصلاحية');
    }

    public function test_a_run_records_how_long_it_took(): void
    {
        $run = AutomationRun::create([
            'automation_id' => Automation::create([
                'name' => 'ق', 'trigger' => 'session_upcoming', 'conditions' => [], 'actions' => [], 'is_active' => true,
            ])->id,
            'trigger' => 'session_upcoming',
            'subject_type' => 'App\\Models\\Session',
            'subject_id' => 1,
            'status' => AutomationRun::STATUS_SUCCESS,
            'started_at' => now()->subMillisecond(),
            'finished_at' => now(),
            'duration_ms' => 420,
            'attempts' => 1,
        ]);

        $this->assertSame('420 مل.ث', $run->durationLabel());

        $run->update(['duration_ms' => 1350]);
        $this->assertSame('1.4 ث', $run->fresh()->durationLabel());
    }

    public function test_an_old_run_without_timing_reads_as_unknown_not_zero(): void
    {
        // الصفوف السابقة للهجرة لا مدّة لها — و«صفر» كذبٌ يوحي بالفورية
        $run = new AutomationRun(['duration_ms' => null]);

        $this->assertSame('—', $run->durationLabel());
    }
}
