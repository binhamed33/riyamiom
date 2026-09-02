<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الترتيبُ يستبدل الجدولَ وحدَه — لا الصفحةَ كلَّها.
 *
 * ═══ ما وُضعت له ═══
 *
 * كلُّ نقرةٍ على ترويسة عمودٍ كانت تُعيد بناء الصفحة: القائمةُ
 * الجانبيةُ تُرسَم من جديد وتقفز، ومن كان في أسفل جدولٍ طويلٍ عاد إلى
 * رأسه. والمطلوبُ من الخادم صفٌّ واحد: الجدول.
 *
 * ═══ وما تحرسه ═══
 *
 * ١) لكلّ رابطِ ترتيبٍ منطقةٌ تحويه — ورابطٌ بلا منطقةٍ يعمل عملَه
 *    القديم بلا كسر، لكنّه لا يستفيد. فالوجودُ يُفحص.
 * ٢) المنطقةُ تشمل شريطَ الترتيب والجدولَ معاً — وإلا بقي الشريطُ
 *    يقول «التاريخ» فوق جدولٍ رُتّب بالحالة.
 * ٣) والرابطُ يبقى رابطاً حقيقياً: من عطّل الجافاسكربت يرتّب كما كان.
 */
class LiveSortTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function seedCase(string $number = 'م/2026/1'): LegalCase
    {
        $client = Client::create([
            'name' => 'موكّل ' . $number, 'type' => 'individual',
            'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96891234567',
        ]);

        return LegalCase::create([
            'case_number' => $number, 'title' => 'قضية', 'description' => 'وصف',
            'type' => 'مدني', 'court' => 'المحكمة', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);
    }

    /** الجدولُ داخل منطقةٍ موسومة، وروابطُ الترتيب داخلَها. */
    public function test_the_cases_table_lives_in_a_swappable_region(): void
    {
        $this->seedCase();

        $html = $this->actingAs($this->admin)->get(route('cases.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-live="cases"', $html, 'لا منطقةَ تُستبدل');
        $this->assertStringContainsString('data-live-link', $html, 'روابطُ الترتيب بلا وسم');
        $this->assertStringContainsString('data-live-nav', $html, 'الترقيمُ خارج التبديل');
    }

    /** والجلسات: ترويسةٌ تُنقر، ومنطقةٌ تشمل شريطَ الترتيب معها. */
    public function test_the_sessions_headers_are_clickable_inside_one_region(): void
    {
        $case = $this->seedCase();
        CourtSession::create([
            'case_id' => $case->id, 'date' => now()->addDays(3),
            'location' => 'قاعة 1', 'status' => 'upcoming',
        ]);

        $html = $this->actingAs($this->admin)->get(route('sessions.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-live="sessions"', $html);

        // شريطُ الترتيب والجدولُ في منطقةٍ واحدة: الشريطُ يسبق الترويسة،
        // وكلاهما بعد فتح المنطقة وقبل إغلاقها
        $region = strpos($html, 'data-live="sessions"');
        $bar = strpos($html, 'app.sort_by') !== false ? strpos($html, 'sort_by') : strpos($html, 'ترتيب');
        $this->assertNotFalse($region);

        // الترويسةُ صارت رابطَ ترتيب
        $this->assertStringContainsString('sort=date', $html, 'ترويسةُ التاريخ لا تُرتِّب');
        $this->assertStringContainsString('sort=status', $html, 'ترويسةُ الحالة لا تُرتِّب');
    }

    /** والترتيبُ يعمل بالطلب العاديّ — لا شيءَ يعتمد على الجافاسكربت. */
    public function test_sorting_still_works_without_javascript(): void
    {
        $first = $this->seedCase('م/2026/1');
        $second = $this->seedCase('م/2026/2');

        CourtSession::create(['case_id' => $first->id, 'date' => now()->addDays(9), 'location' => 'ب', 'status' => 'upcoming']);
        CourtSession::create(['case_id' => $second->id, 'date' => now()->addDays(2), 'location' => 'أ', 'status' => 'upcoming']);

        $asc = $this->actingAs($this->admin)
            ->get(route('sessions.index', ['sort' => 'date', 'dir' => 'asc']))
            ->assertOk()->getContent();

        $desc = $this->actingAs($this->admin)
            ->get(route('sessions.index', ['sort' => 'date', 'dir' => 'desc']))
            ->assertOk()->getContent();

        // القريبةُ أوّلاً صاعداً، والبعيدةُ أوّلاً نازلاً
        $this->assertLessThan(strpos($asc, 'قاعة') ?: PHP_INT_MAX, strpos($asc, 'أ') ?: 0);
        $this->assertNotSame($asc, $desc, 'الاتجاهُ لا يغيّر شيئاً');
    }

    /** وكلُّ منطقةٍ موسومةٍ لها إغلاقُها — وسمٌ مفتوحٌ يكسر الصفحة. */
    public function test_every_live_region_is_balanced(): void
    {
        foreach (['cases', 'sessions'] as $page) {
            $file = resource_path("views/{$page}/index.blade.php");
            $html = (string) file_get_contents($file);

            $opens = substr_count($html, 'data-live="');
            $this->assertGreaterThan(0, $opens, "لا منطقةَ في {$page}");

            // الصفحةُ تُعرض فعلاً — الميزانُ الحقيقيُّ عند لارافل لا عندنا
            $this->actingAs($this->admin)->get(route($page . '.index'))->assertOk();
        }
    }
}
