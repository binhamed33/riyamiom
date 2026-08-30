<?php

namespace Tests\Feature;

use App\Models\CaseFolder;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §2: المحامي ينظّم مستنداته بمجلدات يبنيها بنفسه وينقل بينها —
 * والتنظيم لا يمسّ المحتوى أبداً: حذف مجلد يعيد مستنداته إلى «عام».
 * §9: صفحة المستندات تعرض بطريقتين — تفصيلي ومصغّرات.
 */
class DocumentFoldersTest extends TestCase
{
    use RefreshDatabase;

    private User $lawyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function makeCase(string $title = 'قضية التنظيم'): LegalCase
    {
        $client = Client::create(['name' => 'موكّل ' . fake()->unique()->numberBetween(1, 99999), 'phone' => '91234567', 'type' => 'individual']);

        return LegalCase::create([
            'client_id' => $client->id,
            'case_number' => 'F-' . fake()->unique()->numberBetween(1, 99999),
            'title' => $title,
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'خصم',
            'status' => 'active',
            'priority' => 'medium',
        ]);
    }

    private function makeDoc(LegalCase $case, ?int $folderId = null, string $title = 'مذكرة'): Document
    {
        return Document::create([
            'case_id' => $case->id,
            'case_folder_id' => $folderId,
            'title' => $title,
            'file_path' => 'docs/' . fake()->unique()->numberBetween(1, 99999) . '.pdf',
            'file_type' => 'pdf',
            'file_size' => 2048,
            'uploaded_by' => $this->lawyer->id,
        ]);
    }

    public function test_a_lawyer_creates_renames_and_deletes_a_folder(): void
    {
        $case = $this->makeCase();

        $this->actingAs($this->lawyer)
            ->post(route('case-folders.store', $case), ['name' => 'مذكرات'])
            ->assertSessionHas('success');

        $folder = CaseFolder::where('case_id', $case->id)->firstOrFail();
        $this->assertSame('مذكرات', $folder->name);

        $this->actingAs($this->lawyer)
            ->put(route('case-folders.update', $folder), ['name' => 'مذكرات ولوائح'])
            ->assertSessionHas('success');

        $this->assertSame('مذكرات ولوائح', $folder->fresh()->name);
    }

    public function test_deleting_a_folder_keeps_every_document(): void
    {
        $case = $this->makeCase();
        $folder = CaseFolder::create(['case_id' => $case->id, 'name' => 'سندات', 'sort' => 1]);
        $doc = $this->makeDoc($case, $folder->id, 'سند قبض');

        $this->actingAs($this->lawyer)
            ->delete(route('case-folders.destroy', $folder))
            ->assertSessionHas('success');

        $this->assertNull(CaseFolder::find($folder->id));
        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'case_folder_id' => null]);
        $this->assertSame('سند قبض', $doc->fresh()->title, 'المستند نفسه لا يُمسّ');
    }

    public function test_duplicate_folder_names_are_refused_within_a_case(): void
    {
        $case = $this->makeCase();
        CaseFolder::create(['case_id' => $case->id, 'name' => 'مراسلات', 'sort' => 1]);

        $this->actingAs($this->lawyer)
            ->post(route('case-folders.store', $case), ['name' => 'مراسلات'])
            ->assertSessionHas('error');

        $this->assertSame(1, CaseFolder::where('case_id', $case->id)->count());
    }

    public function test_a_document_moves_between_folders_and_back_to_general(): void
    {
        $case = $this->makeCase();
        $a = CaseFolder::create(['case_id' => $case->id, 'name' => 'أ', 'sort' => 1]);
        $b = CaseFolder::create(['case_id' => $case->id, 'name' => 'ب', 'sort' => 2]);
        $doc = $this->makeDoc($case, $a->id);

        $this->actingAs($this->lawyer)
            ->post(route('documents.move', $doc), ['case_folder_id' => $b->id])
            ->assertSessionHas('success');
        $this->assertSame($b->id, $doc->fresh()->case_folder_id);

        $this->actingAs($this->lawyer)
            ->post(route('documents.move', $doc), ['case_folder_id' => null])
            ->assertSessionHas('success');
        $this->assertNull($doc->fresh()->case_folder_id);
    }

    public function test_a_document_never_lands_in_a_folder_of_another_case(): void
    {
        $mine = $this->makeCase('قضيتي');
        $other = $this->makeCase('قضية أخرى');
        $foreignFolder = CaseFolder::create(['case_id' => $other->id, 'name' => 'مجلد الغير', 'sort' => 1]);
        $doc = $this->makeDoc($mine);

        $this->actingAs($this->lawyer)
            ->post(route('documents.move', $doc), ['case_folder_id' => $foreignFolder->id])
            ->assertSessionHas('error');

        $this->assertNull($doc->fresh()->case_folder_id);
        $this->assertSame($mine->id, $doc->fresh()->case_id, 'القضية لم تتبدل');
    }

    public function test_moving_to_another_case_carries_its_own_folder(): void
    {
        $from = $this->makeCase('من');
        $to = $this->makeCase('إلى');
        $folder = CaseFolder::create(['case_id' => $to->id, 'name' => 'وجهة', 'sort' => 1]);
        $doc = $this->makeDoc($from);

        $this->actingAs($this->lawyer)
            ->post(route('documents.move', $doc), ['case_id' => $to->id, 'case_folder_id' => $folder->id])
            ->assertSessionHas('success');

        $this->assertSame($to->id, $doc->fresh()->case_id);
        $this->assertSame($folder->id, $doc->fresh()->case_folder_id);
    }

    public function test_a_client_cannot_reorganize_office_documents(): void
    {
        $case = $this->makeCase();
        $folder = CaseFolder::create(['case_id' => $case->id, 'name' => 'خاص', 'sort' => 1]);
        $doc = $this->makeDoc($case);
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);

        // الحجب طبقتان: وسيط الدور يصدّ قبل المتحكم فيردّ تحويلاً لا 403 —
        // فالمهم أن يُمنع لا كيف يُمنع، وأن لا شيء يتغير
        $blocked = fn ($response) => $this->assertContains(
            $response->getStatusCode(),
            [403, 302],
            'العميل يجب أن يُمنع من تنظيم مستندات المكتب'
        );

        $blocked($this->actingAs($client)->post(route('case-folders.store', $case), ['name' => 'محاولة']));
        $blocked($this->actingAs($client)->delete(route('case-folders.destroy', $folder)));
        $blocked($this->actingAs($client)->post(route('documents.move', $doc), ['case_folder_id' => $folder->id]));

        $this->assertNotNull(CaseFolder::find($folder->id));
        $this->assertSame(1, CaseFolder::where('case_id', $case->id)->count(), 'لم يُنشأ مجلد');
        $this->assertNull($doc->fresh()->case_folder_id, 'لم يُنقل المستند');
    }

    public function test_the_documents_page_offers_both_explorer_views(): void
    {
        $case = $this->makeCase();
        $this->makeDoc($case, null, 'عقد إيجار');

        $this->actingAs($this->lawyer)->get('/documents')
            ->assertOk()
            ->assertSee(__('app.view_details'))
            ->assertSee(__('app.view_thumbnails'))
            ->assertSee("view = 'tiles'", false)
            ->assertSee('عقد إيجار');
    }
}
