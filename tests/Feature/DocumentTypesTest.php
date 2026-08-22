<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_the_office_starts_with_a_usable_catalogue(): void
    {
        $this->assertGreaterThan(20, DocumentType::count());
        $this->assertTrue(DocumentType::where('name', 'وكالة')->exists());
        $this->assertTrue(DocumentType::where('name', 'صحيفة دعوى')->exists());
        $this->assertTrue(DocumentType::where('name', 'حكم')->exists());
    }

    public function test_the_manager_can_add_rename_disable_and_delete_a_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('document-types.store'), ['name' => 'تقرير خبرة'])
            ->assertSessionHas('success');
        $type = DocumentType::where('name', 'تقرير خبرة')->firstOrFail();
        $this->assertFalse($type->is_builtin);

        $this->actingAs($admin)->put(route('document-types.update', $type), ['name' => 'تقرير خبير'])
            ->assertSessionHas('success');
        $this->assertSame('تقرير خبير', $type->fresh()->name);

        $this->actingAs($admin)->post(route('document-types.toggle', $type));
        $this->assertFalse($type->fresh()->is_active);

        $this->actingAs($admin)->delete(route('document-types.destroy', $type))
            ->assertSessionHas('success');
        $this->assertNull(DocumentType::find($type->id));
    }

    /** إعادة التسمية تتبعها المستندات، وإلا حملت نوعاً لا وجود له */
    public function test_renaming_a_type_carries_its_documents_with_it(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();
        $type = DocumentType::create(['name' => 'مرافعة', 'is_active' => true, 'sort' => 500]);

        $doc = Document::factory()->create(['case_id' => $case->id, 'doc_type' => 'مرافعة']);

        $this->actingAs($admin)->put(route('document-types.update', $type), ['name' => 'مرافعة شفهية']);

        $this->assertSame('مرافعة شفهية', $doc->fresh()->doc_type, 'المستند بقي على الاسم القديم.');
    }

    /** نوع تحته مستندات لا يُحذف — الحذف يترك مستندات بلا نوع معرّف */
    public function test_a_type_in_use_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();
        $type = DocumentType::create(['name' => 'إفادة', 'is_active' => true, 'sort' => 500]);
        Document::factory()->create(['case_id' => $case->id, 'doc_type' => 'إفادة']);

        $this->actingAs($admin)->delete(route('document-types.destroy', $type))
            ->assertSessionHas('error');

        $this->assertNotNull(DocumentType::find($type->id));
    }

    public function test_a_builtin_type_is_disabled_not_deleted(): void
    {
        $admin = $this->admin();
        $builtin = DocumentType::where('is_builtin', true)->firstOrFail();

        $this->actingAs($admin)->delete(route('document-types.destroy', $builtin))
            ->assertSessionHas('error');

        $this->assertNotNull(DocumentType::find($builtin->id));
    }

    /** المستندات القديمة لا تتعطّل ولا تُحذف ولا يُطلب رفعها ثانية */
    public function test_documents_without_a_type_keep_working(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();

        $old = Document::factory()->create([
            'case_id' => $case->id,
            'doc_type' => null,
            'title' => 'مستند قديم بلا نوع',
            'access_level' => 'all',
        ]);

        $this->actingAs($admin)->get(route('documents.index'))
            ->assertOk()
            ->assertSee('مستند قديم بلا نوع')
            ->assertSee('غير محدد');

        $this->assertNotNull($old->fresh(), 'المستند القديم يجب أن يبقى.');
    }

    public function test_filtering_by_type_happens_in_the_database(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();

        // عناوين لا تُشبه أسماء الأنواع: قائمة الفلترة نفسها تحوي كل
        // اسم نوع، فعنوان يطابق اسم نوع يظهر في الصفحة مهما كانت النتائج
        Document::factory()->create(['case_id' => $case->id, 'doc_type' => 'وكالة', 'title' => 'ملف-ألف', 'access_level' => 'all']);
        Document::factory()->create(['case_id' => $case->id, 'doc_type' => 'حكم', 'title' => 'ملف-باء', 'access_level' => 'all']);
        Document::factory()->create(['case_id' => $case->id, 'doc_type' => null, 'title' => 'ملف-جيم', 'access_level' => 'all']);

        $this->actingAs($admin)->get(route('documents.index', ['doc_type' => 'وكالة']))
            ->assertOk()->assertSee('ملف-ألف')->assertDontSee('ملف-باء')->assertDontSee('ملف-جيم');

        $this->actingAs($admin)->get(route('documents.index', ['doc_type' => '__untyped__']))
            ->assertOk()->assertSee('ملف-جيم')->assertDontSee('ملف-ألف');
    }

    public function test_search_covers_the_document_type(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();

        Document::factory()->create(['case_id' => $case->id, 'doc_type' => 'محضر جلسة', 'title' => 'ملف-دال', 'access_level' => 'all']);
        Document::factory()->create(['case_id' => $case->id, 'doc_type' => 'عقد', 'title' => 'ملف-هاء', 'access_level' => 'all']);

        $this->actingAs($admin)->get(route('documents.index', ['search' => 'محضر']))
            ->assertOk()->assertSee('ملف-دال')->assertDontSee('ملف-هاء');
    }

    /** الاختيار عند الرفع يُحترم ولا يطمسه الاستنتاج التلقائي */
    public function test_an_explicit_type_on_upload_wins_over_inference(): void
    {
        $admin = $this->admin();
        $case = LegalCase::factory()->create();

        $this->actingAs($admin)->post(route('documents.store'), [
            'case_id' => $case->id,
            'title' => 'ملف',
            'doc_type' => 'شهادة',
            'access_level' => 'all',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('حكم-2024.pdf', 20, 'application/pdf'),
        ]);

        $doc = Document::latest('id')->firstOrFail();
        $this->assertSame('شهادة', $doc->doc_type, 'الاختيار الصريح يجب أن يتقدّم على الاستنتاج.');
    }

    public function test_only_managers_reach_type_management(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $response = $this->actingAs($staff)->get(route('document-types.index'));
        $this->assertContains($response->status(), [302, 403]);

        $before = DocumentType::count();
        $this->actingAs($staff)->post(route('document-types.store'), ['name' => 'نوع مهرَّب']);
        $this->assertSame($before, DocumentType::count(), 'موظف أضاف نوعاً.');
    }
}
