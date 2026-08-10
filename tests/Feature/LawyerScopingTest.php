<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawyerScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCase(User $lawyer, Client $client, string $number): LegalCase
    {
        return LegalCase::create([
            'case_number' => $number,
            'title' => 'Case ' . $number,
            'description' => 'Description here',
            'type' => 'مدني',
            'court' => 'المحكمة الابتدائية',
            'opponent' => 'Opponent',
            'status' => 'active',
            'priority' => 'medium',
            'client_id' => $client->id,
            'lawyer_id' => $lawyer->id,
            'created_by' => $lawyer->id,
        ]);
    }

    public function test_lawyer_sees_only_own_sessions_tasks_and_alerts(): void
    {
        $lawyerA = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $lawyerB = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $client = Client::factory()->create();

        $caseA = $this->makeCase($lawyerA, $client, 'A-100');
        $caseB = $this->makeCase($lawyerB, $client, 'B-200');

        Session::create(['case_id' => $caseA->id, 'date' => now()->addDay(), 'status' => 'upcoming', 'location' => 'محكمة أ']);
        Session::create(['case_id' => $caseB->id, 'date' => now()->addDay(), 'status' => 'upcoming', 'location' => 'محكمة ب']);

        Task::create([
            'title' => 'مهمة المحامي أ', 'case_id' => $caseA->id,
            'assigned_to' => $lawyerA->id, 'created_by' => $lawyerA->id,
            'status' => 'pending', 'priority' => 'medium', 'due_date' => now()->subDay(),
        ]);
        Task::create([
            'title' => 'مهمة المحامي ب', 'case_id' => $caseB->id,
            'assigned_to' => $lawyerB->id, 'created_by' => $lawyerB->id,
            'status' => 'pending', 'priority' => 'medium', 'due_date' => now()->subDay(),
        ]);

        $this->actingAs($lawyerA)
            ->get('/sessions')
            ->assertOk()
            ->assertSee('A-100')
            ->assertDontSee('B-200');

        $this->actingAs($lawyerA)
            ->get('/tasks')
            ->assertOk()
            ->assertSee('مهمة المحامي أ')
            ->assertDontSee('مهمة المحامي ب');

        $this->actingAs($lawyerA)
            ->get('/attention')
            ->assertOk()
            ->assertSee('A-100')
            ->assertDontSee('B-200')
            ->assertSee('مهمة المحامي أ')
            ->assertDontSee('مهمة المحامي ب');

        $this->actingAs($lawyerA)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('A-100')
            ->assertDontSee('B-200');
    }

    public function test_admin_still_sees_everything(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $lawyerA = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $lawyerB = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $client = Client::factory()->create();

        $caseA = $this->makeCase($lawyerA, $client, 'A-300');
        $caseB = $this->makeCase($lawyerB, $client, 'B-400');

        Session::create(['case_id' => $caseA->id, 'date' => now()->addDay(), 'status' => 'upcoming', 'location' => 'محكمة']);
        Session::create(['case_id' => $caseB->id, 'date' => now()->addDay(), 'status' => 'upcoming', 'location' => 'محكمة']);

        $this->actingAs($admin)
            ->get('/sessions')
            ->assertOk()
            ->assertSee('A-300')
            ->assertSee('B-400');
    }
}