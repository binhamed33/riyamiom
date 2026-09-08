<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * كلُّ عنوانٍ فوق عموده.
 *
 * ═══ ما وقع ═══
 *
 * جدولُ القضايا كان يبني رأسَه من قائمةِ **الأعمدة القابلة للترتيب**:
 *
 *     $sortableCols = ['number','court','client','type','lawyer', …];
 *
 * وحين جُعل كلُّ عمودٍ قابلاً للترتيب أُسقط «الخصم» من تلك القائمة —
 * لأنّه مشفَّرٌ في القاعدة، وترتيبُه يرتّب النصَّ المعمَّى لا الاسم.
 * فسقط **عنوانُه** معه، وبقيت خليّتُه في الجسد.
 *
 * فصار في الرأس تسعةُ عناوينَ وفي الصفّ عشرُ خلايا. وكلُّ عنوانٍ بعد
 * «الموكّل» يقف فوق عمودٍ ليس له:
 *
 *     «نوع القضية»   ⇦  اسمُ وزارة (وهو الخصم)
 *     «محامي القضية» ⇦  «مدني»    (وهو نوعُ القضية)
 *     «الحالة»       ⇦  اسمُ المحامي
 *     «الأولوية»     ⇦  «قيد الانتظار» (وهي الحالة)
 *     «تاريخ الإنشاء» ⇦ «عالية»   (وهي الأولوية)
 *
 * والجدولُ يبدو سليماً تماماً — لا فراغَ ولا خطأ — فيصدّقه القارئ.
 * وهذا أسوأُ من عمودٍ فارغ: بياناتٌ صحيحةٌ تُقرأ خطأً.
 *
 * ═══ ولماذا لم يمسكه اختبارٌ قائم ═══
 *
 * اختباراتُ الترتيب تفحص **ترتيبَ الصفوف**، لا مطابقةَ الرأس للجسد.
 * فمرّت كلُّها خضراء والجدولُ مزاح.
 *
 * فهذا يعُدّ في الصفحة المعروضة فعلاً — لا في نصّ القالب — ويشترط
 * تساويَ العددين في كلّ جدولٍ رئيسٍ في النظام.
 */
class TableHeaderAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $client = Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => '1234567', 'phone' => '96890000000',
        ]);

        $case = LegalCase::create([
            'case_number' => 'ق/1', 'office_case_number' => '1', 'title' => 'قضية',
            'description' => 'و', 'type' => 'مدني', 'case_type' => 'مدني',
            'court' => 'المحكمة العليا', 'opponent' => 'وزارة الإسكان',
            'status' => 'active', 'priority' => 'high',
            'client_id' => $client->id, 'lawyer_id' => $this->admin->id,
            'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);

        // صفُّ بياناتٍ حقيقيٌّ في كلّ جدول: صفُّ «لا نتائج» خليّةٌ واحدة
        // بـcolspan، فلا يُقاس عليه شيء
        \App\Models\Task::create([
            'case_id' => $case->id, 'title' => 'مهمّة', 'status' => 'pending',
            'priority' => 'medium', 'assigned_to' => $this->admin->id,
            'created_by' => $this->admin->id, 'due_date' => now()->addDays(3),
        ]);

        \App\Models\Session::create([
            'case_id' => $case->id, 'date' => now()->addDays(5),
            'time' => '10:00', 'location' => 'المحكمة العليا', 'status' => 'upcoming',
        ]);

        \App\Models\Document::create([
            'case_id' => $case->id, 'uploaded_by' => $this->admin->id,
            'title' => 'مستند', 'file_path' => 'documents/x.pdf',
            'file_type' => 'pdf', 'file_size' => 100, 'access_level' => 'all',
        ]);
    }

    /**
     * يعُدّ خلايا أوّل صفِّ بياناتٍ حقيقيّ ويقارنه برأس الجدول.
     *
     * @return array{0: int, 1: int}
     */
    private function countCells(string $html): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        $headers = $xpath->query('//table//thead//tr[1]/th');
        $headerCount = $headers ? $headers->length : 0;

        $bodyCount = 0;

        foreach ($xpath->query('//table//tbody/tr') ?: [] as $tr) {
            $tds = $xpath->query('./td', $tr);

            // صفُّ «لا نتائج» يحمل خليّةً واحدةً بـcolspan — ليس صفَّ بيانات
            if (!$tds || $tds->length <= 1) {
                continue;
            }

            $bodyCount = $tds->length;
            break;
        }

        return [$headerCount, $bodyCount];
    }

    /** @return array<string, array{0: string}> */
    public static function tables(): array
    {
        return [
            'القضايا' => ['/cases'],
            'الموكّلون' => ['/clients'],
            'المهامّ' => ['/tasks'],
            'الجلسات' => ['/sessions'],
            'المستندات' => ['/documents'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tables')]
    public function test_every_header_sits_above_its_own_column(string $path): void
    {
        $html = $this->actingAs($this->admin)->get($path)->assertOk()->getContent();

        [$headers, $cells] = $this->countCells($html);

        if ($headers === 0 && $cells === 0) {
            $this->markTestSkipped($path . ' — لا جدولَ في هذه الصفحة');
        }

        $this->assertSame($headers, $cells,
            $path . " — الرأسُ {$headers} عنواناً والصفُّ {$cells} خليّة. "
            . 'كلُّ عنوانٍ بعد موضع الفرق يقف فوق عمودٍ ليس له، والجدولُ يبدو سليماً.');
    }

    /**
     * و«الخصم» بعينه له عنوان — وهو العمودُ الذي سقط.
     */
    public function test_the_opponent_column_has_a_header(): void
    {
        $html = $this->actingAs($this->admin)->get('/cases')->assertOk()->getContent();

        $this->assertStringContainsString(__('app.case_opponent'), $html,
            'عمودُ الخصم يُعرض بلا عنوان');
        $this->assertStringContainsString('وزارة الإسكان', $html, 'قيمةُ الخصم لا تُعرض');
    }

    /**
     * ولا يُرتَّب: ترتيبُ عمودٍ مشفَّرٍ يرتّب النصَّ المعمَّى لا الاسم،
     * فالنتيجةُ عشوائيّةٌ تبدو ترتيباً.
     */
    public function test_the_encrypted_column_is_shown_but_not_sortable(): void
    {
        $html = $this->actingAs($this->admin)->get('/cases')->assertOk()->getContent();

        $this->assertStringNotContainsString('sort=opponent', $html,
            'عمودُ الخصم قابلٌ للترتيب — وهو مشفَّرٌ فالترتيبُ عشوائيّ');
    }

    /** وبقيّةُ الأعمدة تُرتَّب كما كانت. */
    public function test_the_other_columns_stay_sortable(): void
    {
        $html = $this->actingAs($this->admin)->get('/cases')->assertOk()->getContent();

        foreach (['number', 'court', 'client', 'type', 'lawyer', 'status', 'priority', 'created'] as $key) {
            $this->assertStringContainsString('sort=' . $key, $html, $key . ' — لم يعد يُرتَّب');
        }
    }
}
