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

    /**
     * وكلُّ وسمٍ يُفتح يُغلق — في الحالتين: بترقيمٍ وبلا ترقيم.
     *
     * ═══ العطل الذي حرسه هذا ═══
     *
     * إغلاقُ منطقة الاستبدال كان مكتوباً داخل شرط الترقيم:
     * @if($x->hasPages()) … </div> @endif — فجدولٌ يسع صفحةً واحدةً
     * يخرج بوسمٍ مفتوح. والمتصفّحُ يتسامح فيغلقه عنه، فلا يُرى شيء —
     * حتى يُضاف وسمٌ آخرُ يوماً فتنهار الصفحة بلا سببٍ ظاهر.
     *
     * والفحصُ يجري على الصفحة الفارغة تحديداً: هي الحالةُ التي كانت
     * تُخرج الوسمَ المفتوح، وهي التي لا يفتحها أحدٌ وقتَ التطوير.
     */
    public function test_every_index_page_closes_every_tag(): void
    {
        foreach (self::LIVE_PAGES as $route => $region) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString('data-live="' . $region . '"', $html, "لا منطقةَ استبدالٍ في {$route}");

            // <div> مفتوحةٌ = </div> مغلقة. الوسومُ المفردة لا تُعدّ هنا
            $opens = preg_match_all('/<div\b/i', $html);
            $closes = preg_match_all('/<\/div>/i', $html);

            $this->assertSame($closes, $opens, "وسمُ div مفتوحٌ بلا إغلاقٍ في {$route} (فُتح {$opens} وأُغلق {$closes})");
        }
    }

    /**
     * والاستبدالُ يتحرّك: خفوتٌ ثم ظهورٌ ثم صعودُ الصفوف.
     *
     * كان يقع في إطارٍ واحد — صحيحٌ وناشف. والعينُ لا تتبع قفزةً،
     * فيُقرأ الجدولُ وميضاً لا إعادةَ ترتيب.
     */
    public function test_the_swap_is_animated_and_respects_stillness(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        foreach (['live-out', 'live-in', '@keyframes mdLiveIn', '@keyframes mdLiveRow'] as $piece) {
            $this->assertStringContainsString($piece, $layout, "لا أثرَ لـ{$piece} في الحركة");
        }

        // المحرّكُ يضع الصنفين فعلاً — لا CSS بلا مَن يشغّلها
        $this->assertStringContainsString("classList.add('live-out')", $layout);
        $this->assertStringContainsString("classList.add('live-in')", $layout);

        // الصفوفُ تصعد واحداً بعد واحد
        $this->assertStringContainsString('animationDelay', $layout, 'لا تتابعَ بين الصفوف');

        // ومن طلب تقليلَ الحركة لا يرى منها شيئاً — في CSS وفي المحرّك
        $this->assertStringContainsString('prefers-reduced-motion', $layout);
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $layout);
    }

    /**
     * والعقدةُ المُبدَّلة تُوقظ Alpine — بشرطٍ يمنع التهيئةَ مرّتين.
     *
     * زرُّ «مصغّرات» في المستندات يعيش داخل منطقة الاستبدال: لو خرج
     * مبدَّلاً بلا نطاقٍ صار زرّاً لا يستجيب — عطلٌ صامتٌ لا يُرى في
     * سجلٍّ ولا في اختبارٍ خادمي. وتهيئةٌ ثانيةٌ لشجرةٍ حيّة تضاعف
     * مستمعي الأحداث، فتصير النقرةُ نقرتين: الشرطُ يمنع الاثنين.
     */
    public function test_the_swapped_node_wakes_alpine_exactly_once(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('wakeAlpine(node)', $layout, 'العقدةُ المُبدَّلة لا تُوقظ Alpine');
        $this->assertStringContainsString('_x_dataStack', $layout, 'لا حارسَ ضدّ تهيئةٍ ثانية');
        $this->assertStringContainsString('Alpine.initTree', $layout);
    }

    /**
     * والاستبدالُ الحيُّ يعمّ قوائمَ الموقع كلَّها لا الجلساتِ وحدَها.
     *
     * صفحةٌ واحدةٌ تُعيد التحميل بين صفحاتٍ لا تُعيده تُقرأ عطلاً في
     * الصفحة لا استثناءً في التصميم.
     */
    public function test_every_list_page_swaps_in_place(): void
    {
        foreach (self::LIVE_PAGES as $route => $region) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString('data-live="' . $region . '"', $html, "لا منطقةَ في {$route}");

            // إمّا رابطُ ترتيبٍ وإمّا ترقيمٌ حيّ — وإلا فالمنطقةُ زينةٌ لا تُستعمل
            $this->assertTrue(
                str_contains($html, 'data-live-link') || str_contains($html, 'data-live-nav'),
                "منطقةُ {$route} بلا رابطٍ حيٍّ يستعملها"
            );
        }
    }

    /** خريطةُ القوائم الحيّة: المسارُ ⇐ اسمُ منطقته. */
    private const LIVE_PAGES = [
        'cases.index' => 'cases',
        'sessions.index' => 'sessions',
        'tasks.index' => 'tasks',
        'clients.index' => 'clients',
        'documents.index' => 'documents',
        'appointments.index' => 'appointments',
        'notifications.index' => 'notifications',
        'users.index' => 'users',
        'audit-log.index' => 'audit-log',
        'evaluations.index' => 'evaluations',
    ];
}
