<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    public function test_guest_redirected_to_login_on_index()
    {
        $this->get('/documents')->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_store()
    {
        $this->post('/documents', [])->assertRedirect('/login');
    }

    public function test_guest_redirected_to_login_on_destroy()
    {
        $doc = Document::factory()->create();
        $this->delete("/documents/{$doc->id}")->assertRedirect('/login');
    }

    public function test_developer_can_upload_document()
    {
        Storage::fake('private');
        $developer = $this->developer();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['client_id' => $client->id]);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($developer)->post('/documents', [
            'case_id' => $case->id,
            'title' => 'Test Document',
            'file' => $file,
            'access_level' => 'all',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', ['title' => 'Test Document']);
    }

    public function test_developer_can_view_documents_index()
    {
        $developer = $this->developer();
        Document::factory()->count(2)->create(['access_level' => 'all']);

        $response = $this->actingAs($developer)->get('/documents');

        $response->assertStatus(200);
        $response->assertViewHas('documents');
        $this->assertCount(2, $response->viewData('documents'));
    }

    public function test_document_validation_title_required()
    {
        Storage::fake('private');
        $developer = $this->developer();
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($developer)->post('/documents', [
            'title' => '',
            'file' => $file,
            'access_level' => 'all',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_document_validation_file_required()
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post('/documents', [
            'title' => 'Test',
            'file' => null,
            'access_level' => 'all',
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_document_validation_access_level_required()
    {
        Storage::fake('private');
        $developer = $this->developer();
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($developer)->post('/documents', [
            'title' => 'Test',
            'file' => $file,
            'access_level' => '',
        ]);

        $response->assertSessionHasErrors('access_level');
    }

    public function test_document_validation_access_level_must_be_valid()
    {
        Storage::fake('private');
        $developer = $this->developer();
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($developer)->post('/documents', [
            'title' => 'Test',
            'file' => $file,
            'access_level' => 'invalid_level',
        ]);

        $response->assertSessionHasErrors('access_level');
    }

    public function test_document_rejects_invalid_file_type()
    {
        Storage::fake('private');
        $developer = $this->developer();
        $file = UploadedFile::fake()->create('document.exe', 100);

        $response = $this->actingAs($developer)->post('/documents', [
            'title' => 'Bad File',
            'file' => $file,
            'access_level' => 'all',
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_document_rejects_file_size_too_large()
    {
        Storage::fake('private');
        $developer = $this->developer();

        $file = UploadedFile::fake()->create('large.pdf', 21000);

        $response = $this->actingAs($developer)->post('/documents', [
            'title' => 'Large File',
            'file' => $file,
            'access_level' => 'all',
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_document_accepts_valid_file_types()
    {
        $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

        foreach ($allowedTypes as $ext) {
            Storage::fake('private');
            $developer = $this->developer();
            $client = Client::factory()->create();
            $case = LegalCase::factory()->create(['client_id' => $client->id]);
            $file = UploadedFile::fake()->create("document.{$ext}", 100);

            $response = $this->actingAs($developer)->post('/documents', [
                'case_id' => $case->id,
                'title' => "Test {$ext}",
                'file' => $file,
                'access_level' => 'all',
            ]);

            $response->assertSessionDoesntHaveErrors('file');
            $response->assertSessionHas('success');
        }
    }

    public function test_delete_removes_file_from_storage()
    {
        Storage::fake('private');
        $developer = $this->developer();

        $file = UploadedFile::fake()->create('delete-me.pdf', 100);
        $path = $file->store('documents', 'private');

        $document = Document::factory()->create([
            'file_path' => $path,
            'file_type' => 'pdf',
            'uploaded_by' => $developer->id,
        ]);

        $response = $this->actingAs($developer)->delete("/documents/{$document->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Storage::disk('private')->assertMissing($path);
        $this->assertModelMissing($document);
    }

    public function test_private_document_cannot_be_deleted_by_other_user()
    {
        Storage::fake('private');
        $developer = $this->developer();
        $otherUser = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $file = UploadedFile::fake()->create('private.pdf', 100);
        $path = $file->store('documents', 'private');

        $document = Document::factory()->create([
            'file_path' => $path,
            'file_type' => 'pdf',
            'uploaded_by' => $developer->id,
            'access_level' => 'private',
        ]);

        $response = $this->actingAs($otherUser)->delete("/documents/{$document->id}");

        // المنع يردّ إلى لوحة المتابعة برسالة. ما يهمّ: المستند والملف باقيان.
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        Storage::disk('private')->assertExists($path);
    }

    public function test_private_document_can_be_deleted_by_owner()
    {
        Storage::fake('private');
        $developer = $this->developer();

        $file = UploadedFile::fake()->create('my-private.pdf', 100);
        $path = $file->store('documents', 'private');

        $document = Document::factory()->create([
            'file_path' => $path,
            'file_type' => 'pdf',
            'uploaded_by' => $developer->id,
            'access_level' => 'private',
        ]);

        $response = $this->actingAs($developer)->delete("/documents/{$document->id}");

        $response->assertRedirect();
        Storage::disk('private')->assertMissing($path);
    }

    public function test_team_document_can_be_deleted_by_admin()
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $otherUser = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $file = UploadedFile::fake()->create('team.pdf', 100);
        $path = $file->store('documents', 'private');

        $document = Document::factory()->create([
            'file_path' => $path,
            'file_type' => 'pdf',
            'uploaded_by' => $otherUser->id,
            'access_level' => 'team',
        ]);

        $response = $this->actingAs($admin)->delete("/documents/{$document->id}");

        $response->assertRedirect();
        Storage::disk('private')->assertMissing($path);
    }

    public function test_private_document_download_denied_for_other_user()
    {
        Storage::fake('private');
        $developer = $this->developer();
        $otherUser = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $file = UploadedFile::fake()->create('secret.pdf', 100);
        $path = $file->store('documents', 'private');

        $document = Document::factory()->create([
            'file_path' => $path,
            'file_type' => 'pdf',
            'uploaded_by' => $developer->id,
            'access_level' => 'private',
        ]);

        $response = $this->actingAs($otherUser)->get("/documents/{$document->id}/download");

        // المهم أن الملف لم يُسلَّم، لا رمز الحالة: المنع يردّ إلى لوحة
        // المتابعة برسالة، والمستند السرّي يبقى حيث هو.
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
        $this->assertNotEquals(200, $response->status());
    }

    public function test_private_document_download_allowed_for_owner()
    {
        Storage::fake('private');
        $developer = $this->developer();

        $file = UploadedFile::fake()->create('mine.pdf', 100);
        $path = $file->store('documents', 'private');

        $document = Document::factory()->create([
            'file_path' => $path,
            'file_type' => 'pdf',
            'uploaded_by' => $developer->id,
            'access_level' => 'private',
        ]);

        $response = $this->actingAs($developer)->get("/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_staff_can_access_documents()
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        Document::factory()->create();

        $response = $this->actingAs($staff)->get('/documents');

        $response->assertStatus(200);
    }

    public function test_client_role_cannot_access_documents_index()
    {
        $clientUser = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($clientUser)->get('/documents');

        // المنع يردّ إلى لوحة المتابعة برسالة «غير مصرح لك بالوصول»،
        // لا برمز 403 عارٍ. نفحص المنع نفسه لا رمزه.
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }
}
