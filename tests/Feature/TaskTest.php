<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function taskData(array $overrides = []): array
    {
        $assignee = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        return array_merge([
            'title' => 'Test Task',
            'description' => 'Task description',
            'assigned_to' => $assignee->id,
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => now()->addWeek()->format('Y-m-d'),
        ], $overrides);
    }

    public function test_guest_redirected_to_login_on_index()
    {
        $this->get('/tasks')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_create()
    {
        $this->get('/tasks/create')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_store()
    {
        $this->post('/tasks', [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_show()
    {
        $task = Task::factory()->create();
        $this->get("/tasks/{$task->id}")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_edit()
    {
        $task = Task::factory()->create();
        $this->get("/tasks/{$task->id}/edit")->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_update()
    {
        $task = Task::factory()->create();
        $this->put("/tasks/{$task->id}", [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_destroy()
    {
        $task = Task::factory()->create();
        $this->delete("/tasks/{$task->id}")->assertRedirect('/login');
    }

    public function test_developer_can_view_tasks_index()
    {
        $developer = $this->developer();
        Task::factory()->count(2)->create();

        $response = $this->actingAs($developer)->get('/tasks');

        $response->assertStatus(200);
        $response->assertViewHas('tasks');
        $this->assertCount(2, $response->viewData('tasks'));
    }

    public function test_developer_can_create_task()
    {
        $developer = $this->developer();
        $data = $this->taskData();

        $response = $this->actingAs($developer)->post('/tasks', $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tasks', ['title' => 'Test Task']);
    }

    public function test_developer_can_view_task()
    {
        $developer = $this->developer();
        $task = Task::factory()->create();

        $response = $this->actingAs($developer)->get("/tasks/{$task->id}");

        $response->assertStatus(200);
        $response->assertViewHas('task');
    }

    public function test_developer_can_edit_task()
    {
        $developer = $this->developer();
        $task = Task::factory()->create();

        $response = $this->actingAs($developer)->get("/tasks/{$task->id}/edit");

        $response->assertStatus(200);
        $response->assertViewHas('task');
    }

    public function test_developer_can_update_task()
    {
        $developer = $this->developer();
        $task = Task::factory()->create(['title' => 'Old Title']);

        $response = $this->actingAs($developer)->put("/tasks/{$task->id}", $this->taskData([
            'title' => 'Updated Title',
            'assigned_to' => $task->assigned_to,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('Updated Title', $task->fresh()->title);
    }

    public function test_developer_can_delete_task()
    {
        $developer = $this->developer();
        $task = Task::factory()->create();

        $response = $this->actingAs($developer)->delete("/tasks/{$task->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertModelMissing($task);
    }

    public function test_task_validation_title_required()
    {
        $developer = $this->developer();
        $data = $this->taskData(['title' => '']);

        $response = $this->actingAs($developer)->post('/tasks', $data);

        $response->assertSessionHasErrors('title');
    }

    public function test_task_validation_assigned_to_required()
    {
        $developer = $this->developer();
        $data = $this->taskData(['assigned_to' => '']);

        $response = $this->actingAs($developer)->post('/tasks', $data);

        $response->assertSessionHasErrors('assigned_to');
    }

    public function test_task_validation_assigned_to_must_exist()
    {
        $developer = $this->developer();
        $data = $this->taskData(['assigned_to' => 99999]);

        $response = $this->actingAs($developer)->post('/tasks', $data);

        $response->assertSessionHasErrors('assigned_to');
    }

    public function test_task_validation_status_required()
    {
        $developer = $this->developer();
        $data = $this->taskData(['status' => '']);

        $response = $this->actingAs($developer)->post('/tasks', $data);

        $response->assertSessionHasErrors('status');
    }

    public function test_task_validation_status_must_be_valid()
    {
        $developer = $this->developer();
        $data = $this->taskData(['status' => 'invalid_status']);

        $response = $this->actingAs($developer)->post('/tasks', $data);

        $response->assertSessionHasErrors('status');
    }

    public function test_task_validation_priority_required()
    {
        $developer = $this->developer();
        $data = $this->taskData(['priority' => '']);

        $response = $this->actingAs($developer)->post('/tasks', $data);

        $response->assertSessionHasErrors('priority');
    }

    public function test_task_validation_priority_must_be_valid()
    {
        $developer = $this->developer();
        $data = $this->taskData(['priority' => 'invalid']);

        $response = $this->actingAs($developer)->post('/tasks', $data);

        $response->assertSessionHasErrors('priority');
    }

    public function test_completed_status_sets_completed_at()
    {
        $developer = $this->developer();
        $task = Task::factory()->create([
            'status' => 'pending',
            'completed_at' => null,
        ]);

        $this->actingAs($developer)->put("/tasks/{$task->id}", $this->taskData([
            'status' => 'completed',
            'assigned_to' => $task->assigned_to,
        ]));

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_non_completed_status_clears_completed_at()
    {
        $developer = $this->developer();
        $task = Task::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($developer)->put("/tasks/{$task->id}", $this->taskData([
            'status' => 'in_progress',
            'assigned_to' => $task->assigned_to,
        ]));

        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_my_tasks_filter_shows_only_assigned_tasks()
    {
        $developer = $this->developer();
        $otherUser = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        Task::factory()->create(['assigned_to' => $developer->id]);
        Task::factory()->create(['assigned_to' => $otherUser->id]);

        $response = $this->actingAs($developer)->get('/my-tasks');

        $response->assertStatus(200);
        $tasks = $response->viewData('tasks');
        $this->assertCount(1, $tasks);
        $this->assertEquals($developer->id, $tasks->first()->assigned_to);
    }

    public function test_task_creation_sends_notification_to_assignee()
    {
        $developer = $this->developer();
        $assignee = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($developer)->post('/tasks', [
            'title' => 'Notify Task',
            'description' => 'Test',
            'assigned_to' => $assignee->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignee->id,
        ]);
    }

    public function test_task_creation_does_not_notify_self_assignment()
    {
        $developer = $this->developer();

        $this->actingAs($developer)->post('/tasks', [
            'title' => 'Self Task',
            'description' => 'Test',
            'assigned_to' => $developer->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $developer->id,
        ]);
    }

    public function test_staff_can_access_tasks()
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        Task::factory()->create();

        $response = $this->actingAs($staff)->get('/tasks');

        $response->assertStatus(200);
    }

    public function test_client_role_cannot_access_tasks_index()
    {
        $clientUser = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($clientUser)->get('/tasks');

        // المنع يردّ إلى لوحة المتابعة برسالة «غير مصرح لك بالوصول»،
        // لا برمز 403 عارٍ. نفحص المنع نفسه لا رمزه.
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_can_filter_tasks_by_status()
    {
        $developer = $this->developer();
        Task::factory()->create(['status' => 'pending']);
        Task::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($developer)->get('/tasks?status=pending');

        $response->assertStatus(200);
        $tasks = $response->viewData('tasks');
        $this->assertCount(1, $tasks);
        $this->assertEquals('pending', $tasks->first()->status);
    }

    public function test_can_filter_tasks_by_priority()
    {
        $developer = $this->developer();
        Task::factory()->create(['priority' => 'high']);
        Task::factory()->create(['priority' => 'low']);

        $response = $this->actingAs($developer)->get('/tasks?priority=high');

        $response->assertStatus(200);
        $tasks = $response->viewData('tasks');
        $this->assertCount(1, $tasks);
        $this->assertEquals('high', $tasks->first()->priority);
    }
}
