<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * المستنداتُ منسوبةٌ إلى أصحابها.
 *
 * ═══ العطلُ الذي وُضعت له ═══
 *
 * الشاشةُ كانت تُفتح على كومةٍ واحدة لا يُعرف لمن كلُّ ورقةٍ فيها،
 * فتضيع «وكالة فلان» بين مئةِ ملفٍّ اسمُها «وكالة». والآن جذرٌ فيه
 * «مستندات (فلان)» لكلّ موكّل، ثمّ قضاياه، ثمّ مجلداتُ القضية.
 *
 * ولا مجلدَ يُحفر في القاعدة باسم أحد: مجلدٌ باسم موكّلٍ يكذب أوّلَ
 * ما يُصحَّح اسمُه، والمحسوبُ من الرابط القائم يبقى صادقاً.
 */
class DocumentsByPersonTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function documentFor(?LegalCase $case, string $title): Document
    {
        return Document::create([
            'case_id' => $case?->id,
            'uploaded_by' => $this->staff->id,
            'title' => $title,
            'file_path' => 'documents/' . md5($title) . '.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'access_level' => Document::ACCESS_ALL,
        ]);
    }

    private function clientWithCase(string $name, string $nid, string $caseNumber): array
    {
        $client = Client::create(['name' => $name, 'type' => 'individual', 'national_id' => $nid, 'phone' => '9689' . $nid]);

        $case = LegalCase::create([
            'case_number' => $caseNumber,
            'title' => 'قضية ' . $name,
            'description' => 'وصفٌ للاختبار',
            'type' => 'مدني',
            'court' => 'المحكمة الابتدائية',
            'opponent' => 'خصم',
            'status' => 'active',
            'priority' => 'medium',
            'client_id' => $client->id,
            'created_by' => $this->staff->id,
            'opened_at' => now()->subMonth(),
        ]);

        return [$client, $case];
    }

    public function test_the_root_lists_a_folder_per_person(): void
    {
        [$rashid, $rashidCase] = $this->clientWithCase('راشد الحبسي', '1111111', 'م/2026/1');
        [$noura, $nouraCase] = $this->clientWithCase('نورة السيابية', '2222222', 'م/2026/2');

        $this->documentFor($rashidCase, 'وكالة');
        $this->documentFor($rashidCase, 'صحيفة دعوى');
        $this->documentFor($nouraCase, 'عقد');

        $html = $this->actingAs($this->staff)->get(route('documents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('مستندات (راشد الحبسي)', $html);
        $this->assertStringContainsString('مستندات (نورة السيابية)', $html);
    }

    /** الدخولُ إلى شخصٍ يعرض مستنداتِه وحدَه. */
    public function test_entering_a_person_narrows_to_their_documents(): void
    {
        [$rashid, $rashidCase] = $this->clientWithCase('راشد الحبسي', '1111111', 'م/2026/1');
        [$noura, $nouraCase] = $this->clientWithCase('نورة السيابية', '2222222', 'م/2026/2');

        $this->documentFor($rashidCase, 'وكالة راشد');
        $this->documentFor($nouraCase, 'وكالة نورة');

        $html = $this->actingAs($this->staff)
            ->get(route('documents.index', ['client_id' => $rashid->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('وكالة راشد', $html);
        $this->assertStringNotContainsString('وكالة نورة', $html, 'ظهر مستندُ شخصٍ آخر داخل ملفّه');

        // وقضاياه تُعرض مجلداتٍ داخلَ ملفّه
        $this->assertStringContainsString('م/2026/1', $html);
    }

    /** ما لا صاحبَ له يبقى كما هو — في كومةٍ واحدةٍ تُعرف. */
    public function test_unassigned_documents_stay_as_they_are(): void
    {
        [$rashid, $rashidCase] = $this->clientWithCase('راشد الحبسي', '1111111', 'م/2026/1');

        $this->documentFor($rashidCase, 'وكالة راشد');
        $this->documentFor(null, 'مسحٌ قديم بلا نسب');

        $root = $this->actingAs($this->staff)->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('غير منسوبة', $root);

        // والدخولُ إليها يعرضها وحدَها
        $html = $this->actingAs($this->staff)
            ->get(route('documents.index', ['client_id' => 0]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('مسحٌ قديم بلا نسب', $html);
        $this->assertStringNotContainsString('وكالة راشد', $html);
    }

    /** المجلداتُ محسوبةٌ لا محفورة: تصحيحُ الاسم يظهر فوراً بلا هجرة. */
    public function test_renaming_a_client_renames_their_folder(): void
    {
        [$client, $case] = $this->clientWithCase('راشد الحبسي', '1111111', 'م/2026/1');
        $this->documentFor($case, 'وكالة');

        $client->update(['name' => 'راشد بن سعيد الحبسي']);

        $html = $this->actingAs($this->staff)->get(route('documents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('مستندات (راشد بن سعيد الحبسي)', $html);
        $this->assertStringNotContainsString('مستندات (راشد الحبسي)', $html);
    }

    /** ومجلداتُ القضية القائمة لم تُمسّ — الطبقةُ فوقها لا بدلاً منها. */
    public function test_case_folders_still_work_inside_a_case(): void
    {
        [$client, $case] = $this->clientWithCase('راشد الحبسي', '1111111', 'م/2026/1');

        $folder = \App\Models\CaseFolder::create(['case_id' => $case->id, 'name' => 'المرافعات', 'sort' => 0]);
        $doc = $this->documentFor($case, 'مذكرة دفاع');
        $doc->update(['case_folder_id' => $folder->id]);

        $html = $this->actingAs($this->staff)
            ->get(route('documents.index', ['case_id' => $case->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('المرافعات', $html);
    }
}
