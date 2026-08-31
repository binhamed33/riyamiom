<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الأتمتة تعمل باسمها: «نظام مُداوَلة» — لا باسم صاحب القاعدة.
 *
 * ═══ شكوى من الاستعمال الحقيقيّ ═══
 *
 * قاعدةُ «تحضير الجلسات القادمة» تُنشئ مهمّةً ليلاً، فتظهر صفحتها:
 * «أُنشئ بواسطة: عبدالرحمن…» — واسمُ الرجل على شيءٍ لم يفعله، وقد
 * يُسأل عنه: لماذا أسندتَ إليّ هذا؟
 *
 * وcreated_by يبقى في القاعدة كما هو للمساءلة (قاعدةُ مَن فعلت)،
 * والوسمُ الظاهر وحده هو الذي يتغيّر.
 */
class AutomationActsInItsOwnNameTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'عبدالرحمن الريامي']);
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $client = Client::create(['name' => 'موكّل', 'phone' => '91234567', 'type' => 'individual']);
        $this->case = LegalCase::create([
            'client_id' => $client->id,
            'lawyer_id' => $lawyer->id,
            'case_number' => 'T-1',
            'title' => 'قضية اختبار',
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => 'active',
            'priority' => 'medium',
        ]);
    }

    private function runRule(array $action): void
    {
        $rule = Automation::create([
            'name' => 'تحضير الجلسات القادمة',
            'trigger' => 'case_created',
            'conditions' => [],
            'actions' => [$action],
            'is_active' => true,
            'created_by' => $this->owner->id,
        ]);

        \App\Models\Setting::set('automation_enabled', '1');
        \App\Services\Automation\AutomationEngine::fire('case_created', $this->case);
    }

    public function test_an_automated_task_shows_the_system_not_the_rule_owner(): void
    {
        $this->runRule(['type' => 'create_task', 'title' => 'تحضير جلسة', 'assign' => 'case_lawyer']);

        $task = Task::firstOrFail();
        $this->assertSame('نظام مُداوَلة', $task->creatorLabel(), 'نُسبت المهمّة إلى إنسان');
        // والمساءلة باقية: قاعدةُ مَن فعلت
        $this->assertSame($this->owner->id, $task->created_by);

        $this->actingAs($this->owner)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('نظام مُداوَلة')
            ->assertDontSee('أُنشئ بواسطة: عبدالرحمن');
    }

    public function test_an_automated_timeline_event_is_signed_by_the_system(): void
    {
        $this->runRule(['type' => 'add_timeline_event', 'title' => 'حدث تلقائي']);

        $activity = CaseActivity::firstOrFail();
        $this->assertSame('نظام مُداوَلة', $activity->actorLabel());

        $this->actingAs($this->owner)
            ->get(route('cases.show', $this->case))
            ->assertOk()
            ->assertSee('نظام مُداوَلة');
    }

    /** ومهمّة الإنسان تبقى باسمه — لا يبتلع النظامُ الجميع. */
    public function test_a_human_task_still_carries_the_human_name(): void
    {
        $task = Task::create([
            'title' => 'مهمّة يدويّة',
            'assigned_to' => $this->owner->id,
            'created_by' => $this->owner->id,
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => now()->addDay(),
        ]);

        $this->assertSame('عبدالرحمن الريامي', $task->creatorLabel());
    }

    /**
     * وموظّفٌ حُذف حسابه لا يتنكّر «نظامَ مُداوَلة».
     *
     * الحذف يُفرِّغ created_by (nullOnDelete)، فلو كان الفراغ هو
     * العلامة لاختلط المحذوفُ بالنظام — ولهذا كان العمود الصريح.
     */
    public function test_a_departed_users_task_does_not_masquerade_as_the_system(): void
    {
        $task = Task::create([
            'title' => 'مهمّة قديمة',
            'assigned_to' => $this->owner->id,
            'created_by' => null,
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => now()->addDay(),
        ]);

        $this->assertNotSame('نظام مُداوَلة', $task->creatorLabel());
    }
}
