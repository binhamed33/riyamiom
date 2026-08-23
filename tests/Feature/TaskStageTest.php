<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مسار المهمة: محطّة واحدة تتقدّم، ولا شيء غيرها يُكتب.
 *
 * كان زرّ «إكمال المهمة» يمرّ عبر tasks.update فيرسل عنوان المهمة
 * ووصفها ومَن أُسندت إليه كما كانت الصفحة ساعة فُتحت. فإن عدّلها
 * زميلٌ في تلك الأثناء، محا الضغطُ تعديلَه وأعاد القديم بلا إنذار.
 */
class TaskStageTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        $user = User::factory()->create(['role' => 'developer']);
        $user->is_active = true;
        $user->save();

        return $user;
    }

    public function test_the_stage_control_moves_a_task_one_step_forward()
    {
        $task = Task::factory()->create(['status' => 'pending']);

        $this->actingAs($this->developer())
            ->patch("/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertRedirect("/tasks/{$task->id}");

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_completing_a_task_stamps_the_time_it_finished()
    {
        $task = Task::factory()->create(['status' => 'in_progress', 'completed_at' => null]);

        $this->actingAs($this->developer())
            ->patch("/tasks/{$task->id}/status", ['status' => 'completed']);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_reopening_a_task_clears_the_completion_time()
    {
        $task = Task::factory()->create(['status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($this->developer())
            ->patch("/tasks/{$task->id}/status", ['status' => 'in_progress']);

        $this->assertNull($task->fresh()->completed_at);
    }

    /** جوهر التغيير: لا يُكتب غير الحالة. */
    public function test_changing_the_status_does_not_touch_a_colleagues_edit()
    {
        $task = Task::factory()->create([
            'status' => 'in_progress',
            'title' => 'العنوان القديم',
            'priority' => 'low',
        ]);

        // زميلٌ عدّل المهمة بعد أن فُتحت الصفحة
        $task->update(['title' => 'العنوان الذي كتبه الزميل', 'priority' => 'urgent']);

        $this->actingAs($this->developer())
            ->patch("/tasks/{$task->id}/status", ['status' => 'completed']);

        $fresh = $task->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('العنوان الذي كتبه الزميل', $fresh->title);
        $this->assertSame('urgent', $fresh->priority);
    }

    public function test_an_unknown_status_is_refused()
    {
        $task = Task::factory()->create(['status' => 'pending']);

        $this->actingAs($this->developer())
            ->patch("/tasks/{$task->id}/status", ['status' => 'deleted'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_a_guest_cannot_move_a_task()
    {
        $task = Task::factory()->create(['status' => 'pending']);

        $this->patch("/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertRedirect('/login');

        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_the_creator_is_told_once_when_the_task_is_done()
    {
        $creator = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $task = Task::factory()->create(['status' => 'in_progress', 'created_by' => $creator->id]);

        $doer = $this->developer();

        $this->actingAs($doer)->patch("/tasks/{$task->id}/status", ['status' => 'completed']);
        // الضغط ثانيةً على الحالة نفسها لا يُرسل إخطاراً آخر
        $this->actingAs($doer)->patch("/tasks/{$task->id}/status", ['status' => 'completed']);

        $this->assertSame(1, Notification::where('user_id', $creator->id)->count());
    }

    public function test_the_page_shows_the_three_stages_not_two_identical_buttons()
    {
        $task = Task::factory()->create(['status' => 'in_progress']);

        $response = $this->actingAs($this->developer())->get("/tasks/{$task->id}");

        $response->assertStatus(200);
        $response->assertSee(__('app.status_pending'));
        $response->assertSee(__('app.status_in_progress'));
        $response->assertSee(__('app.status_completed'));
        $response->assertSee('md-stage-track', false);
        // المحطّة الحالية معلَّمة لقارئ الشاشة لا للعين وحدها
        $response->assertSee('aria-current="step"', false);
    }
}
