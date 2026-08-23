<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * نوع المستند يُختار أو يُكتب.
 *
 * كانت القائمة مغلقة: إمّا نوعٌ منها أو لا نوع. فالمحامي الذي يرفع
 * «لائحة تظلّم» ولم يُدرجها أحدٌ من قبل يترك الخانة فارغة ويمضي —
 * ويضيع التصنيف على من يبحث بعده. وصفحة القضية لم تكن تسأل عن النوع
 * أصلاً.
 */
class DocumentTypeWriteInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    private function developer(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function upload(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->developer())->post('/documents', array_merge([
            'title' => 'مستند',
            'file' => UploadedFile::fake()->create('doc.pdf', 40, 'application/pdf'),
            'access_level' => 'all',
        ], $extra));
    }

    // ── الصنف ────────────────────────────────────────────────────

    public function test_a_new_name_joins_the_office_list()
    {
        $name = DocumentType::remember('لائحة تظلّم');

        $this->assertSame('لائحة تظلّم', $name);
        $this->assertDatabaseHas('document_types', ['name' => 'لائحة تظلّم', 'is_active' => true]);
    }

    public function test_a_name_already_known_is_not_duplicated()
    {
        // «وكالة» من الأنواع التي يبدأ بها كل مكتب
        $this->assertSame(1, DocumentType::where('name', 'وكالة')->count());

        DocumentType::remember('وكالة');
        DocumentType::remember('  وكالة  ');

        $this->assertSame(1, DocumentType::where('name', 'وكالة')->count());
    }

    public function test_extra_spaces_do_not_make_a_second_type()
    {
        DocumentType::remember('صحيفة   دعوى');

        $this->assertDatabaseHas('document_types', ['name' => 'صحيفة دعوى']);
    }

    public function test_writing_a_disabled_type_brings_it_back()
    {
        DocumentType::where('name', 'إقرار')->update(['is_active' => false]);

        DocumentType::remember('إقرار');

        $this->assertTrue((bool) DocumentType::where('name', 'إقرار')->value('is_active'));
    }

    public function test_an_empty_name_registers_nothing()
    {
        $before = DocumentType::count();

        $this->assertNull(DocumentType::remember('   '));
        $this->assertNull(DocumentType::remember(null));

        $this->assertSame($before, DocumentType::count());
    }

    // ── الرفع من صفحة المستندات ───────────────────────────────────

    public function test_a_written_type_is_saved_with_the_document()
    {
        $this->upload(['doc_type' => 'لائحة تظلّم'])->assertSessionHasNoErrors();

        $this->assertSame('لائحة تظلّم', Document::first()->doc_type);
    }

    public function test_a_written_type_becomes_available_to_the_next_upload()
    {
        $this->upload(['doc_type' => 'لائحة تظلّم']);

        $this->assertDatabaseHas('document_types', ['name' => 'لائحة تظلّم']);

        $html = $this->actingAs($this->developer())->get('/documents')->getContent();
        $this->assertStringContainsString('لائحة تظلّم', $html);
    }

    public function test_the_field_is_writable_not_a_closed_list()
    {
        $html = $this->actingAs($this->developer())->get('/documents')->getContent();

        // خانة نصّ مع قائمة اقتراحات، لا select مغلق
        $this->assertMatchesRegularExpression('/<input[^>]+name="doc_type"[^>]+list="/', $html);
        $this->assertStringContainsString('<datalist', $html);
    }

    public function test_markup_is_not_a_document_type()
    {
        $this->upload(['doc_type' => '<b>وكالة</b>'])->assertSessionHasErrors('doc_type');

        $this->assertSame(0, Document::count());
    }

    public function test_leaving_it_empty_still_lets_the_system_infer()
    {
        $this->actingAs($this->developer())->post('/documents', [
            'title' => 'وكالة سالم',
            'file' => UploadedFile::fake()->create('وكالة-سالم.pdf', 40, 'application/pdf'),
            'access_level' => 'all',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Document::count());
    }

    // ── الرفع من صفحة إنشاء القضية ────────────────────────────────

    public function test_the_case_page_now_asks_for_the_type()
    {
        $html = $this->actingAs($this->developer())->get('/cases/create')->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]+name="doc_type"/', $html);
    }

    public function test_a_type_written_on_the_case_page_reaches_the_document()
    {
        $client = Client::factory()->create();

        $this->actingAs($this->developer())->post('/cases', [
            'case_number' => 'C-2026-900',
            'description' => 'وصف القضية',
            'court' => 'محكمة الاستئناف',
            'opponent' => 'الخصم',
            'status' => 'active',
            'priority' => 'medium',
            'client_id' => $client->id,
            'doc_file' => UploadedFile::fake()->create('ملف.pdf', 40, 'application/pdf'),
            'doc_title' => 'وكالة الموكّل',
            'doc_type' => 'وكالة خاصة',
        ])->assertSessionHasNoErrors();

        $this->assertSame('وكالة خاصة', Document::first()->doc_type);
        $this->assertDatabaseHas('document_types', ['name' => 'وكالة خاصة']);
    }

    public function test_the_case_page_still_infers_when_the_type_is_left_blank()
    {
        $client = Client::factory()->create();

        $this->actingAs($this->developer())->post('/cases', [
            'case_number' => 'C-2026-901',
            'description' => 'وصف',
            'court' => 'محكمة',
            'opponent' => 'الخصم',
            'status' => 'active',
            'priority' => 'medium',
            'client_id' => $client->id,
            'doc_file' => UploadedFile::fake()->create('عقد-إيجار.pdf', 40, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Document::count());
        $this->assertSame(1, LegalCase::count());
    }
}
