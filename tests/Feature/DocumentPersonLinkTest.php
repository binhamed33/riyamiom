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

/**
 * ورقةٌ تُنسب إلى شخصٍ مباشرةً — أو لا تُنسب.
 *
 * ═══ العطل ═══
 *
 * صاحبُ المستند كان يُستنتج من قضيّته وحدَها: مستند ⇐ قضية ⇐ موكّل.
 * فما لا قضيةَ له فلا صاحبَ له، ويسقط في كومة «غير منسوبة» مهما عرف
 * الموظّفُ لمن هو — وكالةٌ قبل فتح الملفّ، هويةٌ، عقدٌ لموكّلٍ لم
 * يخاصم أحداً بعد.
 *
 * ═══ وما تحرسه ═══
 *
 * ١) النسبةُ المباشرة تُحفظ وتُعرض في ملفّ الشخص.
 * ٢) والاستنتاجُ من القضية يبقى كما كان — لم يتغيّر لأحدٍ شيء.
 * ٣) والمكتوبةُ باليد تعلو المستنتَجة.
 * ٤) و«بلا نسبة» خيارٌ يبقى في مكانه.
 */
class DocumentPersonLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function client(string $name): Client
    {
        return Client::create([
            'name' => $name, 'type' => 'individual',
            'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96890000000',
        ]);
    }

    private function upload(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('documents.store'), array_merge([
            'title' => 'وكالة قانونية',
            'file' => UploadedFile::fake()->create('wakala.pdf', 40, 'application/pdf'),
            'access_level' => 'all',
        ], $extra));
    }

    /** النسبةُ المباشرة: ورقةٌ بلا قضيةٍ تجد صاحبَها. */
    public function test_a_file_can_be_filed_under_a_person_with_no_case(): void
    {
        $client = $this->client('حمد بن سالم الرواحي');

        $this->upload(['client_id' => $client->id])->assertRedirect();

        $doc = Document::latest('id')->firstOrFail();
        $this->assertSame($client->id, $doc->client_id);
        $this->assertNull($doc->case_id, 'أُلصقت بقضيةٍ لم تُطلب');
        $this->assertSame($client->id, $doc->ownerClient()?->id);

        // وتُعدّ في ملفّه على الجذر، لا في «غير منسوبة»
        $root = $this->actingAs($this->admin)->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('مستندات (حمد بن سالم الرواحي)', $root);

        // وتُعرض داخل ملفّه
        $folder = $this->actingAs($this->admin)
            ->get(route('documents.index', ['client_id' => $client->id]))->assertOk()->getContent();
        $this->assertStringContainsString('وكالة قانونية', $folder);
    }

    /** والاستنتاجُ من القضية يبقى — لم يتغيّر لأحدٍ شيء. */
    public function test_the_old_inference_through_the_case_still_holds(): void
    {
        $client = $this->client('عائشة بنت محمد الزدجالية');
        $case = LegalCase::create([
            'case_number' => 'ق/1', 'office_case_number' => 'ق/1', 'title' => 'قضية', 'description' => 'و',
            'type' => 'مدني', 'court' => 'م', 'opponent' => 'خ', 'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);

        $this->upload(['case_id' => $case->id, 'title' => 'صحيفة دعوى'])->assertRedirect();

        $doc = Document::latest('id')->firstOrFail();
        $this->assertNull($doc->client_id, 'كُتبت نسبةٌ لم تُطلب');
        $this->assertSame($client->id, $doc->ownerClient()?->id, 'ضاع الاستنتاجُ من القضية');

        $root = $this->actingAs($this->admin)->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('مستندات (عائشة بنت محمد الزدجالية)', $root);
    }

    /** والمكتوبةُ باليد تعلو المستنتَجة. */
    public function test_a_hand_written_owner_outranks_the_inferred_one(): void
    {
        $caseOwner = $this->client('صاحبُ القضية');
        $named = $this->client('الشخصُ المكتوب');

        $case = LegalCase::create([
            'case_number' => 'ق/2', 'office_case_number' => 'ق/2', 'title' => 'قضية', 'description' => 'و',
            'type' => 'مدني', 'court' => 'م', 'opponent' => 'خ', 'status' => 'active', 'priority' => 'medium',
            'client_id' => $caseOwner->id, 'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);

        $this->upload(['case_id' => $case->id, 'client_id' => $named->id])->assertRedirect();

        $doc = Document::latest('id')->firstOrFail();
        $this->assertSame($named->id, $doc->ownerClient()?->id);

        // وتُعرض في ملفّ المكتوب لا في ملفّ صاحب القضية
        $inNamed = $this->actingAs($this->admin)
            ->get(route('documents.index', ['client_id' => $named->id]))->assertOk()->getContent();
        $this->assertStringContainsString('وكالة قانونية', $inNamed);

        $inOwner = $this->actingAs($this->admin)
            ->get(route('documents.index', ['client_id' => $caseOwner->id]))->assertOk()->getContent();
        $this->assertStringNotContainsString('وكالة قانونية', $inOwner, 'ظهرت في ملفّ من لم تُنسب إليه');
    }

    /** و«بلا نسبة» خيارٌ يبقى: الورقةُ تُرفع وتبقى في كومتها. */
    public function test_no_owner_at_all_is_still_a_choice(): void
    {
        $this->upload(['title' => 'ورقة بلا صاحب'])->assertRedirect();

        $doc = Document::latest('id')->firstOrFail();
        $this->assertNull($doc->client_id);
        $this->assertNull($doc->ownerClient());

        $root = $this->actingAs($this->admin)->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('غير منسوبة', $root);
    }

    /** والنموذجُ يعرض الخانةَ ويقول إنّها اختيارية. */
    public function test_the_upload_form_offers_the_person_field(): void
    {
        $this->client('موكّل النموذج');

        $html = $this->actingAs($this->admin)->get(route('documents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="client_id"', $html, 'لا خانةَ نسبةٍ في النموذج');
        $this->assertStringContainsString('بلا نسبة', $html, 'لا خيارَ لتركها بلا صاحب');
        $this->assertStringContainsString('موكّل النموذج', $html);
    }
}
