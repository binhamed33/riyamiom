<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\HrAttendance;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Setting;
use App\Models\User;
use App\Support\Trend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لوحةُ التحكّم تقول ما جرى، وبرقمٍ يعني شيئاً، وبأعمدةٍ متساوية.
 *
 * ═══ ما وقع ═══
 *
 * - «النشاط الأخير» فيه بطاقاتٌ فارغة لا تحمل إلا «منذ 46 دقيقة»: سطرُ
 *   التدقيق كان يُعرض بعمود description ولا وجودَ له.
 * - «إجمالي القضايا 160 ▲ +1350٪»: قضيّتان في الشهر الماضي أساسٌ لنسبة.
 * - الجلساتُ القادمة اثنتا عشرة في عمودٍ وخمسٌ في جاريه.
 * - شريطُ الجوال ستّةُ عناصر في خمسة أعمدة، والتذييلُ تحته لا يُرى.
 */
class DashboardActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        // الجملُ عربيّةٌ بلغة المستخدم لا بلغة بيئة الاختبار
        app()->setLocale('ar');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'عبدالرحمن', 'locale' => 'ar']);
    }

    private function client(string $name = 'سعيد بن حمد'): Client
    {
        return Client::create(['name' => $name, 'type' => 'individual', 'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96891234567']);
    }

    // ───────────────────────────────────────── ما جرى

    public function test_a_create_entry_names_what_was_created(): void
    {
        $client = $this->client();
        $log = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'create', 'model_type' => Client::class, 'model_id' => $client->id, 'new_values' => $client->toArray()]);

        $this->assertSame('أُنشئ موكّل «سعيد بن حمد»', $log->describe());
        $this->assertSame('عبدالرحمن', $log->actorName());
    }

    public function test_a_sweep_close_names_the_employee_and_the_reason_and_the_system(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'أحمد البوسعيدي']);
        $log = AuditLog::create([
            'user_id' => null, 'action' => 'attendance_close', 'model_type' => HrAttendance::class, 'model_id' => 1,
            'old_values' => ['employee_id' => $lawyer->id, 'work_date' => now()->toDateString()],
            'new_values' => ['check_out_at' => now()->toDateTimeString(), 'minutes' => 480, 'closed_by' => 'cap'],
        ]);

        $this->assertSame('أُقفل حضور أحمد البوسعيدي — بلغ السقف اليومي', $log->describe());
        $this->assertSame('نظام', $log->actorName(), 'المكنسةُ لا مستخدمَ لها — تُنسب إلى النظام لا إلى فراغ');
    }

    public function test_login_and_unknown_actions_never_render_empty(): void
    {
        $login = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'login', 'model_type' => User::class, 'model_id' => $this->admin->id]);
        $odd = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'export_csv', 'model_type' => LegalCase::class, 'model_id' => null]);

        $this->assertSame('تسجيل دخول', $login->describe());
        $this->assertNotSame('', trim($odd->describe()));
        $this->assertStringContainsString('قضية', $odd->describe());
    }

    /** وتظهر الجملةُ في اللوحة نفسِها — لا بطاقةَ بلا عنوان. */
    public function test_the_dashboard_shows_the_sentence_not_a_blank_card(): void
    {
        $client = $this->client('موكّل الاختبار');
        AuditLog::create(['user_id' => null, 'action' => 'create', 'model_type' => Client::class, 'model_id' => $client->id, 'new_values' => ['name' => $client->name]]);

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('أُنشئ موكّل «موكّل الاختبار»', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<p class="text-gray-900 text-xs font-medium truncate[^"]*">\s*<\/p>/u',
            $html,
            'بطاقةُ نشاطٍ بلا عنوان'
        );
    }

    // ───────────────────────────────────────── الأرقام

    public function test_percentages_need_a_base_of_five_and_totals_grow_on_what_preceded_them(): void
    {
        $this->assertNull(Trend::percent(29, 2), '٢ ⇐ ٢٩ ليست «+١٣٥٠٪»');
        $this->assertSame('مقابل 2 الشهر السابق', Trend::label(29, 2));
        $this->assertSame('مقابل 0 الشهر السابق', Trend::label(3, 0));

        $this->assertSame(20, Trend::percent(12, 10));
        $this->assertSame('+20٪ من الشهر السابق', Trend::label(12, 10));
        $this->assertSame(-50, Trend::percent(5, 10));

        $this->assertSame(22, Trend::growthOfTotal(160, 29), 'نموُّ الإجمالي = ٢٩ على ١٣١');
        $this->assertNull(Trend::growthOfTotal(3, 3), 'لا رصيدَ سابقاً يُقاس عليه');
        $this->assertNull(Trend::growthOfTotal(160, 0));
    }

    public function test_the_dashboard_prints_the_count_comparison_instead_of_an_absurd_percent(): void
    {
        // قضيّتان الشهر الماضي وستٌّ هذا الشهر
        foreach ([now()->subMonth()->startOfMonth()->addDays(3), now()->subMonth()->startOfMonth()->addDays(4)] as $at) {
            $this->caseAt($at);
        }
        for ($i = 0; $i < 6; $i++) {
            $this->caseAt(now()->startOfMonth()->addHours($i + 1));
        }

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('مقابل 2 الشهر السابق', $html);
        $this->assertStringNotContainsString('+200%', $html);
        $this->assertStringNotContainsString('+200٪', $html);
    }

    // ───────────────────────────────────────── الأعمدة

    public function test_upcoming_sessions_are_capped_at_five_like_the_neighbouring_columns(): void
    {
        $case = $this->caseAt(now()->subDay());
        // غداً فما بعدَه — لا اليوم، كي لا تُعدّ في بطاقة «جلسات اليوم»
        for ($i = 1; $i <= 7; $i++) {
            Session::create(['case_id' => $case->id, 'date' => now()->addDays($i)->setTime(10, 0), 'location' => 'قاعة ' . $i, 'status' => 'upcoming']);
        }

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        // صفوفُ العمود وحدَها: شارةُ التاريخ لا تظهر إلا فيها
        $this->assertSame(5, substr_count($html, 'w-10 h-10 rounded-lg bg-gold/12 flex items-center justify-center flex-shrink-0'), 'خمسُ جلساتٍ في العمود لا سبع');
    }

    // ───────────────────────────────────────── الجوال

    public function test_the_mobile_bar_has_five_items_and_the_footer_clears_it(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<nav class="md:hidden fixed bottom-0.*?<\/nav>/su', $html, $nav);
        $this->assertNotEmpty($nav, 'لا شريطَ سفليّاً');
        $this->assertSame(5, substr_count($nav[0], 'flex flex-col items-center gap-0.5 py-2'), 'خمسةُ عناصر في خمسة أعمدة');
        $this->assertStringNotContainsString(route('clients.index'), $nav[0], 'العملاءُ من القائمة الجانبيّة لا الشريط');

        $this->assertMatchesRegularExpression('/<footer class="[^"]*bottom-nav-space/u', $html, 'التذييلُ يُرى فوق الشريط');
        $this->assertStringContainsString('مُداوَلة', $html);
    }

    private function caseAt(\Illuminate\Support\Carbon $createdAt): LegalCase
    {
        $client = $this->client('موكّل ' . $createdAt->timestamp . random_int(1, 999));
        $case = LegalCase::create([
            'case_number' => 'م/' . $createdAt->timestamp . random_int(1, 999),
            'title' => 'قضية', 'description' => 'وصف', 'type' => 'مدني', 'court' => 'المحكمة', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $client->id, 'created_by' => $this->admin->id,
            'opened_at' => $createdAt,
        ]);
        $case->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $case;
    }
}
