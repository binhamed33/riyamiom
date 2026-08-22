<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * معاينة المستندات داخل الصفحة.
 *
 * ما تحرسه: أن ترويسات الأمان تمنع المواقع الخارجية من احتضان النظام
 * في إطار، دون أن تخنقه عن نفسه. كانت frame-ancestors 'none' مع
 * X-Frame-Options: DENY تحجبان معاينة PDF — العارض يعرضها في إطار من
 * الموقع نفسه، فيحجبها المتصفّح ويظهر صندوق رمادي فارغ.
 */
class DocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_may_frame_itself_but_no_one_else_may(): void
    {
        $user = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $response = $this->actingAs($user)->get('/documents');

        // 'self' لا 'none': المعاينة إطار من الموقع نفسه
        $this->assertStringContainsString(
            "frame-ancestors 'self'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function test_a_pdf_preview_streams_inline_not_as_a_download(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $path = UploadedFile::fake()->create('doc.pdf', 40, 'application/pdf')->store('documents', 'private');

        $document = Document::factory()->create([
            'file_path' => $path,
            'file_type' => 'pdf',
            'uploaded_by' => $user->id,
            'access_level' => 'all',
        ]);

        $response = $this->actingAs($user)->get("/documents/{$document->id}/preview");

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        // ترويسة المعاينة نفسها لا تمنع عرضها في إطار من الموقع
        $this->assertNotSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function test_a_private_document_preview_is_still_denied_to_others(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $path = UploadedFile::fake()->create('secret.pdf', 40, 'application/pdf')->store('documents', 'private');

        $document = Document::factory()->create([
            'file_path' => $path,
            'file_type' => 'pdf',
            'uploaded_by' => $owner->id,
            'access_level' => 'private',
        ]);

        $response = $this->actingAs($other)->get("/documents/{$document->id}/preview");

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }
}
