<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use App\Services\LawyerEvaluationService;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'mudawala',
            'database.connections.mysql.username' => 'mudawala',
            'database.connections.mysql.password' => env('DB_PASSWORD', ''),
        ]);
    }

    public function test_service_scores_lawyer_from_cases_activity_and_quality(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $client = Client::factory()->create();

        try {
            $closed = LegalCase::factory()->create(['lawyer_id' => $lawyer->id, 'client_id' => $client->id, 'status' => 'closed']);
            $open = LegalCase::factory()->create(['lawyer_id' => $lawyer->id, 'client_id' => $client->id, 'status' => 'active']);

            Session::factory()->create(['case_id' => $open->id, 'report' => 'قرار']);
            Session::factory()->count(3)->create(['case_id' => $open->id]);

            Task::factory()->create([
                'assigned_to' => $lawyer->id,
                'case_id'     => $open->id,
                'status'      => 'completed',
                'completed_at' => now(),
                'due_date'    => now()->addDay(),
            ]);
            Task::factory()->create([
                'assigned_to' => $lawyer->id,
                'case_id'     => $open->id,
                'status'      => 'pending',
            ]);

            Document::factory()->create(['uploaded_by' => $lawyer->id, 'case_id' => $open->id]);

            AuditLog::create(['user_id' => $lawyer->id, 'action' => 'create', 'model_type' => LegalCase::class, 'model_id' => $open->id, 'new_values' => []]);
            AuditLog::create(['user_id' => $lawyer->id, 'action' => 'update', 'model_type' => LegalCase::class, 'model_id' => $open->id, 'new_values' => []]);

            $rows = app(LawyerEvaluationService::class)->evaluate('all');

            $row = collect($rows)->firstWhere('id', $lawyer->id);
            $this->assertNotNull($row, 'Lawyer missing from evaluation rows');

            $m = $row['metrics'];
            $this->assertEquals(2, $m['cases_total']);
            $this->assertEquals(1, $m['cases_closed']);
            $this->assertEquals(1, $m['cases_open']);
            $this->assertEquals(4, $m['sessions']);
            $this->assertEquals(1, $m['tasks_completed']);
            $this->assertEquals(1, $m['tasks_on_time']);
            $this->assertEquals(1, $m['documents']);
            $this->assertEquals(2, $m['audit_actions']);
            $this->assertGreaterThan(0, $m['score']);

            $expected = min(100, round((min(1 * 3, 24) + min(1, 16)) + (min(4, 12) + min(1, 10) + min(1, 8) + min(2 * 0.5, 5)) + (0.5 * 10 + 1 * 10 + 0.5 * 5), 1));
            $this->assertEquals($expected, $m['score']);
        } finally {
            $lawyer->cases()->forceDelete();
            $lawyer->documents()->delete();
            Task::where('assigned_to', $lawyer->id)->delete();
            AuditLog::where('user_id', $lawyer->id)->delete();
            $lawyer->delete();
            $client->delete();
        }
    }

    public function test_developer_is_excluded_from_evaluation(): void
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $client = Client::factory()->create();

        try {
            LegalCase::factory()->create(['lawyer_id' => $developer->id, 'client_id' => $client->id, 'status' => 'closed']);

            $rows = app(LawyerEvaluationService::class)->evaluate('all');

            $this->assertNull(collect($rows)->firstWhere('id', $developer->id), 'Developer must not appear in evaluation');
        } finally {
            $developer->cases()->forceDelete();
            $developer->delete();
            $client->delete();
        }
    }

    public function test_evaluations_page_renders(): void
    {
        $developer = User::where('role', 'developer')->firstOrFail();

        $response = $this->actingAs($developer)->get('/evaluations');

        $response->assertOk();
        $response->assertViewIs('evaluations.index');
    }
}
