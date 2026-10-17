<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\HrAttendance;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * الواجهةُ العربيّة تقول كلماتِها لا مفاتيحَها.
 *
 * ═══ ما ظهر في لقطات الإعلان ═══
 *
 * - رسمُ «الاتجاه الشهري» يكتب تحته May Jun Jul… (format('M')).
 * - «النشاط الأخير» يقول «2026/112 — pending».
 * - رقمُ الهاتف في سطرٍ عربيٍّ ينقلب: «1220 9000 968+».
 * - صفحةُ الموكّل تقول «0» قضايا فوق جدولٍ يسرد قضاياه، وشارتُها «active».
 * - الخطُّ الزمنيّ للقضيّة يقول «upcoming» و«completed».
 * - وقتُ الحضور «08:05 AM» داخل جملةٍ عربيّة.
 */
class ArabicLabelsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        app()->setLocale('ar');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'هلال', 'locale' => 'ar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function caseFor(Client $client, string $status = 'pending'): LegalCase
    {
        return LegalCase::create([
            'case_number' => '2026/' . random_int(100, 999), 'title' => 'مطالبة مالية', 'description' => 'وصف',
            'type' => 'تجاري', 'court' => 'المحكمة الابتدائية بمسقط', 'opponent' => 'خصم', 'opponent_phone' => '+968 9000 2412',
            'status' => $status, 'priority' => 'medium', 'client_id' => $client->id, 'created_by' => $this->admin->id,
        ]);
    }

    private function client(): Client
    {
        return Client::create(['name' => 'نوال بنت حمد', 'type' => 'individual', 'national_id' => (string) random_int(1000000, 9999999), 'phone' => '+968 9000 1220']);
    }

    public function test_the_dashboard_speaks_arabic(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 09:00', 'Asia/Muscat'));
        $this->caseFor($this->client(), 'pending');

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        // أسماءُ الأشهر تحت الرسم
        $this->assertStringContainsString('أكتوبر', $html);
        $this->assertStringNotContainsString('"label":"Oct"', $html);
        // الحالةُ بتسميتها في «النشاط الأخير»
        $this->assertStringContainsString('— قيد الانتظار', $html);
        $this->assertStringNotContainsString('— pending', $html);
        // الهاتفُ معزولٌ باتّجاهه
        $this->assertStringContainsString('<bdi dir="ltr">+968 9000 1220</bdi>', $html);
    }

    public function test_the_client_page_counts_and_names_its_cases(): void
    {
        $client = $this->client();
        $this->caseFor($client, 'active');
        $this->caseFor($client, 'won');

        $html = $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/text-5xl font-bold text-gold-dark">\s*2\s*</u', $html, 'العدّادُ لا يعدّ قضايا الموكّل');
        $this->assertStringContainsString('نشطة', $html);
        $this->assertStringContainsString('مكتسبة', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*active\s*</u', $html);
    }

    public function test_the_case_timeline_names_statuses_and_isolates_phones(): void
    {
        $case = $this->caseFor($this->client(), 'active');
        Session::create(['case_id' => $case->id, 'date' => now()->addDay(), 'status' => 'upcoming', 'location' => 'المحكمة الابتدائية بمسقط']);
        Task::factory()->create(['case_id' => $case->id, 'title' => 'إعداد المذكرة', 'status' => 'completed', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);

        $html = $this->actingAs($this->admin)->get(route('cases.show', $case))->assertOk()->getContent();

        $this->assertStringContainsString('قادمة', $html);
        $this->assertStringContainsString('مكتملة', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*(upcoming|completed)(\s*•|\s*<)/u', $html);
        $this->assertStringContainsString('<bdi dir="ltr">+968 9000 2412</bdi>', $html);
    }

    public function test_attendance_times_use_arabic_meridiem(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00', 'Asia/Muscat'));
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar']);
        HrAttendance::create(['user_id' => $lawyer->id, 'work_date' => HrAttendance::today(), 'check_in_at' => now()->setTime(8, 5), 'status' => 'present', 'source' => 'manual']);

        $html = $this->actingAs($lawyer)->get(route('cases.index'))->assertOk()->getContent();

        $this->assertStringContainsString('08:05 ص', $html);
        $this->assertStringNotContainsString('08:05 AM', $html);
    }
}
