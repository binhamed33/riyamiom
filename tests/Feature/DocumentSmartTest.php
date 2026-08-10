<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentSmartTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_extracts_type_and_date_from_filename(): void
    {
        Storage::fake('private');
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $case = LegalCase::factory()->create(['status' => 'active']);

        $response = $this->actingAs($developer)->post('/documents', [
            'case_id' => $case->id,
            'title' => 'عقد إيجار 15-09-2026',
            'access_level' => 'team',
            'file' => UploadedFile::fake()->create('عقد إيجار 15-09-2026.pdf', 100),
        ]);

        $response->assertRedirect(route('documents.index'));
        $this->assertDatabaseHas('documents', [
            'case_id' => $case->id,
            'doc_type' => 'عقد إيجار',
        ]);
        $this->assertSame('2026-09-15', Document::where('case_id', $case->id)->first()->doc_date->toDateString());
    }

    public function test_manual_doc_type_overrides_inference(): void
    {
        Storage::fake('private');
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($developer)->post('/documents', [
            'title' => 'مذكرة دفاع - أحمد محمد',
            'access_level' => 'team',
            'doc_type' => 'لائحة اعتراضية',
            'file' => UploadedFile::fake()->create('مذكرة دفاع.pdf', 100),
        ]);

        $response->assertRedirect(route('documents.index'));
        $this->assertDatabaseHas('documents', [
            'title' => 'مذكرة دفاع - أحمد محمد',
            'doc_type' => 'لائحة اعتراضية',
        ]);
    }
}