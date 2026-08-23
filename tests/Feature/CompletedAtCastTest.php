<?php

namespace Tests\Feature;

use App\Exports\TasksExport;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * تاريخ إنجاز المهمة يُقرأ تاريخاً.
 *
 * كان القالب في النموذج «timestamp» فيُرجع عدداً صحيحاً، والعمود في
 * القاعدة يخزّن تاريخاً سليماً. فكل ما نادى ->format() عليه انكسر:
 * تصدير المهام كان يسقط كلّما وُجدت مهمة واحدة منجَزة، وصفحة المهمة
 * المنجَزة كانت تُعيد المستخدم إلى اللوحة برسالة خطأ عامة.
 *
 * لم يُمسّ شيء في القاعدة — القراءة وحدها كانت خاطئة.
 */
class CompletedAtCastTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function completedTask(): Task
    {
        return Task::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ])->fresh();
    }

    public function test_the_column_still_holds_a_real_date_not_a_number()
    {
        $task = $this->completedTask();

        $raw = DB::table('tasks')->where('id', $task->id)->value('completed_at');

        $this->assertIsString($raw);
        $this->assertNotNull(Carbon::parse($raw));
    }

    public function test_the_model_reads_it_as_a_date()
    {
        $this->assertInstanceOf(Carbon::class, $this->completedTask()->completed_at);
    }

    public function test_exporting_tasks_survives_a_completed_task()
    {
        $row = (new TasksExport($this->developer()))->map($this->completedTask());

        $this->assertSame(now()->format('Y/m/d'), $row[7]);
    }

    public function test_the_full_export_survives_a_completed_task()
    {
        $task = $this->completedTask();

        // ورقة المهام داخل التصدير الشامل — انكسرت لنفس السبب.
        // أوراقه أصنافٌ في ملفٍ واحد، فتُؤخذ منه لا تُستدعى باسمها.
        $sheets = (new \App\Exports\AllExport($this->developer()))->sheets();
        $sheet = collect($sheets)->first(fn ($s) => $s instanceof \Maatwebsite\Excel\Concerns\WithMapping
            && $s->title() === 'المهام');

        $row = $sheet->map($task);

        $this->assertSame(now()->format('Y/m/d'), $row[7]);
        $this->assertTrue($sheet->collection()->contains('id', $task->id));
    }

    public function test_the_page_of_a_completed_task_opens()
    {
        $task = $this->completedTask();

        $this->actingAs($this->developer())
            ->get("/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertSee($task->completed_at->format('Y-m-d H:i'));
    }

    public function test_an_unfinished_task_has_no_completion_date()
    {
        $task = Task::factory()->create(['status' => 'pending', 'completed_at' => null]);

        $this->assertNull($task->fresh()->completed_at);

        $this->actingAs($this->developer())
            ->get("/tasks/{$task->id}")
            ->assertStatus(200);
    }
}
