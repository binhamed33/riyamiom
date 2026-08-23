<?php

namespace Tests\Feature;

use App\Models\CaseTemplate;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function template(): CaseTemplate
    {
        return CaseTemplate::create([
            'name' => 'قضية تجارية',
            'description' => 'قالب النزاعات التجارية',
            'items' => [
                ['title' => 'مراجعة المستندات', 'days_offset' => 1, 'priority' => 'high'],
                ['title' => 'إعداد المذكرة', 'days_offset' => 3, 'priority' => 'medium'],
            ],
            'checklist' => [['title' => 'التحقق من الوكالة'], ['title' => 'تصوير العقد']],
            'folders' => [['name' => 'المستندات'], ['name' => 'المحكمة'], ['name' => 'المراسلات']],
            'reminders' => [['title' => 'متابعة موعد الجلسة', 'days_offset' => 7, 'target' => 'lawyer']],
            'default_status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_manager_creates_template_via_builder(): void
    {
        $this->actingAs($this->admin())->post(route('case-templates.store'), [
            'name' => 'قضية عمالية',
            'description' => 'وصف',
            'default_status' => 'active',
            'items' => [['title' => 'مهمة أولى', 'days_offset' => '2', 'priority' => 'high']],
            'checklist' => [['title' => 'بند تحقق']],
            'folders' => [['name' => 'مجلد']],
            'reminders' => [['title' => 'تذكير', 'days_offset' => '5', 'target' => 'manager']],
        ])->assertRedirect(route('case-templates.index'));

        $t = CaseTemplate::where('name', 'قضية عمالية')->firstOrFail();
        $this->assertCount(1, $t->items);
        $this->assertCount(1, $t->checklist);
        $this->assertCount(1, $t->folders);
        $this->assertCount(1, $t->reminders);
    }

    public function test_empty_template_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('case-templates.store'), [
            'name' => 'فارغ',
            'items' => [['title' => '']],
        ])->assertSessionHasErrors('name');
    }

    public function test_apply_creates_tasks_checklist_folders_reminders_and_timeline(): void
    {
        $template = $this->template();
        $case = LegalCase::factory()->create(['status' => 'pending']);
        $creator = $this->admin();

        $created = $template->applyTo($case, $creator->id);

        $this->assertSame(['tasks' => 2, 'checklist' => 2, 'folders' => 3, 'reminders' => 1], $created);
        $this->assertSame(2, $case->tasks()->count());
        $this->assertSame(2, $case->checklistItems()->count());
        $this->assertSame(3, $case->folders()->count());
        $this->assertSame(1, $case->reminders()->count());
        $this->assertSame('active', $case->fresh()->status); // الحالة الافتراضية طُبّقت
        $this->assertSame(1, $template->fresh()->usage_count);
        $this->assertDatabaseHas('case_activities', ['case_id' => $case->id]);

        // «الأول» صار غير ثابت: القالب ينقل الحالة من pending إلى
        // active، ومراقب القضية يسجّل ذلك التحوّل — وهو سطر مقصود.
        // فنبحث عن حدث القالب لا عن ترتيبه.
        $titles = $case->activities()->pluck('title');

        $this->assertTrue(
            $titles->contains(fn ($t) => str_contains($t, 'قالب')),
            'تطبيق القالب لم يُسجَّل في المسار الزمني'
        );
        $this->assertTrue(
            $titles->contains('تحدّثت حالة القضية'),
            'نقل الحالة من pending إلى active لم يُسجَّل'
        );
    }

    public function test_used_template_is_disabled_instead_of_deleted(): void
    {
        $template = $this->template();
        $template->applyTo(LegalCase::factory()->create(), $this->admin()->id);

        $this->actingAs($this->admin())->delete(route('case-templates.destroy', $template))->assertRedirect();

        $this->assertDatabaseHas('case_templates', ['id' => $template->id, 'is_active' => false]);

        // قالب غير مستخدم يُحذف فعلاً
        $unused = CaseTemplate::create(['name' => 'غير مستخدم', 'items' => [['title' => 'م', 'days_offset' => 0, 'priority' => 'medium']]]);
        $this->actingAs($this->admin())->delete(route('case-templates.destroy', $unused))->assertRedirect();
        $this->assertDatabaseMissing('case_templates', ['id' => $unused->id]);
    }

    public function test_duplicate_resets_usage(): void
    {
        $template = $this->template();
        $template->increment('usage_count');

        $this->actingAs($this->admin())->post(route('case-templates.duplicate', $template))->assertRedirect();

        $copy = CaseTemplate::where('name', 'قضية تجارية (نسخة)')->firstOrFail();
        $this->assertSame(0, $copy->usage_count);
        $this->assertEquals($template->items, $copy->items);
    }

    public function test_staff_blocked_without_permission_and_allowed_with_it(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        // اصطلاح التطبيق: غير المصرح له يعاد للوحة التحكم برسالة خطأ
        $this->actingAs($staff)->get(route('case-templates.index'))->assertRedirect(route('dashboard'));

        $staff->givePermission('templates.manage');
        $this->actingAs($staff)->get(route('case-templates.index'))->assertOk();
    }

    public function test_checklist_toggle_records_who_and_when(): void
    {
        $template = $this->template();
        $case = LegalCase::factory()->create();
        $admin = $this->admin();
        $template->applyTo($case, $admin->id);

        $item = $case->checklistItems()->first();

        $this->actingAs($admin)->post(route('cases.checklist.toggle', [$case, $item]))->assertRedirect();

        $item->refresh();
        $this->assertTrue($item->is_done);
        $this->assertSame($admin->id, $item->done_by);
        $this->assertNotNull($item->done_at);

        // بند من قضية أخرى لا يُقبل عبر هذه القضية
        $other = LegalCase::factory()->create();
        $this->actingAs($admin)->post(route('cases.checklist.toggle', [$other, $item]))->assertNotFound();
    }
}
