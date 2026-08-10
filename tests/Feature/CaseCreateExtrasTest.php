<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaseCreateExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_optional_document_task_and_note(): void
    {
        Storage::fake('private');

        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $client = Client::factory()->create();

        $response = $this->actingAs($developer)->post('/cases', [
            'case_number' => 'CASE-EXTRAS-1',
            'title'       => 'Case With Extras',
            'description' => 'Description here',
            'type'        => 'مدني',
            'court'       => 'المحكمة الابتدائية',
            'opponent'    => 'Opponent',
            'status'      => 'active',
            'priority'    => 'medium',
            'client_id'   => $client->id,
            'doc_file'         => UploadedFile::fake()->create('مذكرة دفاع 2024.pdf', 100),
            'doc_title'        => 'مذكرة الدفاع',
            'doc_access_level' => 'team',
            'task_title'       => 'إعداد المذكرة',
            'task_description' => 'تحضير الدفوع',
            'task_due_date'    => '2026-09-01',
            'task_priority'    => 'high',
            'note_title'       => 'اتفاق مع الموكل',
            'note_content'     => 'تم الاتفاق على استراتيجية الدفاع',
        ]);

        $response->assertRedirect();

        $case = LegalCase::where('case_number', 'CASE-EXTRAS-1')->first();
        $this->assertNotNull($case);

        $document = Document::where('case_id', $case->id)->first();
        $this->assertNotNull($document);
        $this->assertSame('مذكرة الدفاع', $document->title);
        $this->assertSame('pdf', $document->file_type);
        $this->assertSame('team', $document->access_level);
        $this->assertSame($developer->id, $document->uploaded_by);
        Storage::disk('private')->assertExists($document->file_path);

        $task = Task::where('case_id', $case->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('إعداد المذكرة', $task->title);
        $this->assertSame('pending', $task->status);
        $this->assertSame('high', $task->priority);
        $this->assertSame($developer->id, $task->created_by);
        $this->assertNotNull($task->due_date);

        $note = CaseActivity::where('case_id', $case->id)->where('type', 'note')->first();
        $this->assertNotNull($note);
        $this->assertSame('اتفاق مع الموكل', $note->title);
        $this->assertSame('تم الاتفاق على استراتيجية الدفاع', $note->content);
        $this->assertSame($developer->id, $note->user_id);
    }

    public function test_store_without_extras_creates_only_case(): void
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $client = Client::factory()->create();

        $this->actingAs($developer)->post('/cases', [
            'case_number' => 'CASE-NOEXTRAS-1',
            'title'       => 'Plain Case',
            'description' => 'Description here',
            'type'        => 'مدني',
            'court'       => 'المحكمة الابتدائية',
            'opponent'    => 'Opponent',
            'status'      => 'active',
            'priority'    => 'medium',
            'client_id'   => $client->id,
        ])->assertRedirect();

        $case = LegalCase::where('case_number', 'CASE-NOEXTRAS-1')->first();
        $this->assertNotNull($case);
        $this->assertSame(0, Document::where('case_id', $case->id)->count());
        $this->assertSame(0, Task::where('case_id', $case->id)->count());
        $this->assertSame(0, CaseActivity::where('case_id', $case->id)->count());
    }

    public function test_create_page_shows_optional_sections(): void
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $this->actingAs($developer)
            ->get('/cases/create')
            ->assertOk()
            ->assertSee('doc_file', false)
            ->assertSee('task_title', false)
            ->assertSee('note_title', false);
    }

    public function test_store_sets_opened_at_and_monthly_report_filters_by_month(): void
    {
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $client = Client::factory()->create();

        $this->actingAs($developer)->post('/cases', [
            'case_number' => 'CASE-MONTHLY-1',
            'title'       => 'Case This Month',
            'description' => 'Description here',
            'type'        => 'مدني',
            'court'       => 'المحكمة الابتدائية',
            'opponent'    => 'Opponent',
            'status'      => 'active',
            'priority'    => 'medium',
            'client_id'   => $client->id,
        ])->assertRedirect();

        $case = LegalCase::where('case_number', 'CASE-MONTHLY-1')->first();
        $this->assertNotNull($case->opened_at);

        $now = now();
        $thisMonth = $now->month;
        $thisYear = $now->year;
        $nextMonth = $now->copy()->addMonth()->month;
        $nextYear = $now->copy()->addMonth()->year;

        $this->actingAs($developer)->getJson("/cases/monthly/data?month={$thisMonth}&year={$thisYear}")
            ->assertOk()
            ->assertJsonPath('cases.0.case_number', 'CASE-MONTHLY-1');

        $this->actingAs($developer)->getJson("/cases/monthly/data?month={$nextMonth}&year={$nextYear}")
            ->assertOk()
            ->assertJsonMissing(['cases' => [['case_number' => 'CASE-MONTHLY-1']]]);
    }
}