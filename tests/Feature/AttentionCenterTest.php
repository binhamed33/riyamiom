<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttentionCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_attention_requires_auth()
    {
        $this->get('/attention')->assertRedirect('/login');
    }

    public function test_attention_page_loads_for_developer()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/attention');

        $response->assertStatus(200);
        $response->assertViewHas('items');
    }

    public function test_overdue_task_appears_as_attention()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        Task::factory()->create([
            'status' => 'pending',
            'due_date' => now()->subDays(2),
            'created_by' => $developer->id,
        ]);

        $response = $this->actingAs($developer)->get('/attention');

        $items = $response->viewData('items');
        $this->assertTrue(
            $items->contains(fn ($i) => str_contains($i['title'], 'مهمة متأخرة'))
        );
    }

    public function test_new_client_without_case_appears_as_attention()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        Client::factory()->create(['name' => 'عميل جديد', 'created_at' => now()->subDays(1)]);

        $response = $this->actingAs($developer)->get('/attention');

        $items = $response->viewData('items');
        $this->assertTrue(
            $items->contains(fn ($i) => str_contains($i['title'], 'موكل جديد بدون قضية'))
        );
    }

    public function test_client_user_gets_no_attention_items()
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        Task::factory()->create([
            'status' => 'pending',
            'due_date' => now()->subDays(1),
        ]);

        $response = $this->actingAs($client)->get('/attention');

        $response->assertStatus(200);
        $this->assertCount(0, $response->viewData('items'));
    }
}