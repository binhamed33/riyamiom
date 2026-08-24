<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\HrAttendance;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * عيوب كشفتها المراجعة بعد الإطلاق — كلٌّ منها أُعيد إنتاجه قبل إصلاحه.
 */
class AutomationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function case(User $lawyer, string $status = 'active'): LegalCase
    {
        $client = Client::create(['name' => 'موكّل', 'phone' => '91234567', 'type' => 'individual']);

        return LegalCase::create([
            'client_id' => $client->id,
            'lawyer_id' => $lawyer->id,
            'case_number' => 'H-' . fake()->unique()->numberBetween(1, 99999),
            'title' => 'قضية',
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => $status,
            'priority' => 'medium',
        ]);
    }

    private function changeStatus(User $admin, LegalCase $case, string $to): void
    {
        $this->actingAs($admin)->put("/cases/{$case->id}", [
            'client_id' => $case->client_id,
            'case_number' => $case->case_number,
            'title' => $case->title,
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => $to,
            'priority' => 'medium',
        ]);
        // الطابع الزمني هو دلو منع التكرار — وتغييران في الثانية نفسها
        // يُعدّان نقراً مزدوجاً عمداً
        $this->travel(2)->seconds();
    }

    // ── العيب الأول: القاعدة تعمل مرة واحدة في عمر القضية ───────

    public function test_a_status_rule_fires_on_every_change_not_once_per_case(): void
    {
        Setting::set('automation_enabled', '1', 'automation');
        $admin = $this->admin();
        AutomationEngine::seedByName('توثيق تغيّر حالة القضية', $admin->id);
        $case = $this->case($admin);

        $this->changeStatus($admin, $case, 'pending');
        $this->changeStatus($admin, $case, 'closed');

        $events = CaseActivity::where('case_id', $case->id)
            ->where('title', 'like', '%تغيّرت حالة القضية%')->count();

        $this->assertSame(2, $events, 'التغيير الثاني لم يُنفَّذ — منع التكرار ابتلع الحدث');
    }

    public function test_closing_a_reopened_case_prepares_the_closing_steps_again(): void
    {
        Setting::set('automation_enabled', '1', 'automation');
        $admin = $this->admin();
        AutomationEngine::seedByName('خطوات إغلاق القضية', $admin->id);
        $case = $this->case($admin);

        $this->changeStatus($admin, $case, 'closed');
        $this->changeStatus($admin, $case, 'active');   // أُعيد فتحها
        $this->changeStatus($admin, $case, 'closed');

        $this->assertSame(2, Task::where('title', 'like', '%إغلاق ملف%')->count(),
            'الإغلاق الثاني لم يُجهّز خطواته');
    }

    // ── العيب الثاني: انصراف بعد منتصف الليل ─────────────────────

    public function test_check_out_after_midnight_closes_yesterdays_open_record(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->travelTo(now()->setTime(23, 50));
        $this->actingAs($staff)->post('/hr/attendance/check-in')->assertRedirect();
        $opened = HrAttendance::where('user_id', $staff->id)->first();

        $this->travelTo(now()->addMinutes(20));   // بعد منتصف الليل
        $this->actingAs($staff)->post('/hr/attendance/check-out')->assertSessionHasNoErrors();

        $opened->refresh();
        $this->assertNotNull($opened->check_out_at, 'سجلّ الأمس بقي مفتوحاً بلا انصراف');
        $this->assertSame(1, HrAttendance::count(), 'لم يُنشأ سجلٌّ ثانٍ');
        $this->travelBack();
    }

    // ── العيب الثالث: مصفوفة في الفلتر تُسقط الصفحة ──────────────

    public function test_array_query_parameters_do_not_break_the_new_pages(): void
    {
        $admin = $this->admin();

        foreach ([
            '/sessions/print?status[]=upcoming',
            '/automations?q[]=x',
            '/case-templates?q[]=x',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    // ── العيب الرابع: الاستعادة خارج سجلّ التدقيق ────────────────

    public function test_restoring_a_version_is_written_to_the_audit_trail(): void
    {
        $admin = $this->admin();
        AutomationEngine::seedByName('تحضير الجلسات القادمة', $admin->id);
        $rule = Automation::first();

        $this->actingAs($admin)->put("/automations/{$rule->id}", [
            'name' => 'اسم معدَّل',
            'trigger' => $rule->trigger,
            'conditions' => collect($rule->conditions)->map(fn ($c) => [
                'field' => $c['field'], 'operator' => $c['operator'], 'value' => (string) $c['value'],
            ])->all(),
            'actions' => $rule->actions,
        ]);

        $this->actingAs($admin)->post("/automations/{$rule->id}/versions/1/restore");

        $this->assertDatabaseHas('audit_logs', [
            'action' => \App\Models\AuditLog::ACTION_UPDATE,
            'model_type' => Automation::class,
            'model_id' => $rule->id,
        ]);
    }

    // ── العيب الخامس: زرع متزامن يُنشئ توأماً يعمل مرتين ─────────

    public function test_seeding_twice_never_creates_twin_rules(): void
    {
        $admin = $this->admin();

        AutomationEngine::seedDefaults($admin->id);
        AutomationEngine::seedDefaults($admin->id);
        AutomationEngine::seedByName('تحضير الجلسات القادمة', $admin->id);

        $this->assertSame(
            Automation::distinct('name')->count('name'),
            Automation::count(),
            'وُجدت قاعدتان بالاسم نفسه — ستعملان كلتاهما'
        );
    }

    // ── العيب السادس: زرّ حضور لحساب موكّل ───────────────────────

    public function test_a_client_never_sees_the_attendance_control(): void
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $html = $this->actingAs($client)->get('/dashboard')->getContent();

        $this->assertStringNotContainsString('hr/attendance/check-in', $html);
    }
}
