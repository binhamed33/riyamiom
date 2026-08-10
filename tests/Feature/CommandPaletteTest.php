<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_auth()
    {
        $this->get('/command')->assertRedirect('/login');
    }

    public function test_command_returns_recent_groups_for_developer()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        LegalCase::factory()->create(['title' => 'قضية حديثة', 'status' => 'active']);

        $response = $this->actingAs($developer)->get('/command');

        $response->assertStatus(200)
            ->assertJsonStructure(['groups', 'actions', 'empty']);
        $this->assertArrayHasKey('recent-case', $response->json('groups'));
    }

    public function test_command_searches_cases()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        LegalCase::factory()->create(['title' => 'خلاف تجاري — شركة الأفق', 'status' => 'active']);

        $response = $this->actingAs($developer)->get('/command?q=الأفق');

        $response->assertStatus(200)
            ->assertJsonStructure(['groups', 'actions']);
        $caseGroup = $response->json('groups.case');
        $this->assertNotEmpty($caseGroup);
        $this->assertStringContainsString('الأفق', $caseGroup[0]['label']);
    }

    public function test_command_searches_clients()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        Client::factory()->create(['name' => 'سعد بن خلفان']);

        $response = $this->actingAs($developer)->get('/command?q=خلفان');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('groups.client'));
    }

    public function test_short_query_returns_recent_not_search()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/command?q=a');

        $response->assertStatus(200);
        $this->assertTrue($response->json('empty'));
        $this->assertArrayNotHasKey('case', $response->json('groups'));
    }

    public function test_actions_are_always_present()
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->get('/command?q=أي شيء');

        $response->assertStatus(200);
        $keys = array_column($response->json('actions'), 'key');
        $this->assertContains('new_case', $keys);
        $this->assertContains('new_task', $keys);
    }

    public function test_client_role_gets_no_internal_results()
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        LegalCase::factory()->create(['title' => 'قضية سرية', 'status' => 'active']);

        $response = $this->actingAs($client)->get('/command?q=قضية');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('case', $response->json('groups'));
    }
}