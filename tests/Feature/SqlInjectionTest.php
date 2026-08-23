<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * حقن SQL — إثبات لا تطمين.
 *
 * فحص ZAP رفع تنبيهاً على /login في الحقل password بثقة «متوسطة»
 * وبملاحظته الخاصة: «لم تُعَد بيانات للمُعامل الأصلي». هذه الاختبارات
 * تفحص المسار فعلياً بدل الاكتفاء بقراءة الكود:
 *
 *  - تسجيل الدخول لا يمرّ فيه password إلى SQL أصلاً — auth()->attempt
 *    يبحث بالبريد ثم يقارن الهاش في PHP.
 *  - الترتيب والبحث والترشيح: مداخل يتحكّم بها المستخدم وتصل الاستعلام.
 *
 * وسبب تنبيه ZAP على الأرجح مذكور في الاختبار الأخير: صفحة الدخول
 * تتغيّر بين الطلبات لأن عدّاد المحاولات يغيّر نصّها.
 */
class SqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function payloads(): array
    {
        return [
            "ZAP' AND '1'='1' -- ",
            "ZAP' OR '1'='1' -- ",
            "' OR 1=1 -- ",
            "'; DROP TABLE users; -- ",
            "admin'--",
            "1' UNION SELECT null,null,null -- ",
            "\\' OR 1=1 -- ",
        ];
    }

    public function test_login_cannot_be_bypassed_by_any_injection_payload(): void
    {
        $user = User::factory()->create([
            'email' => 'real@example.com',
            'password' => bcrypt('correct-horse'),
            'is_active' => true,
        ]);

        foreach ($this->payloads() as $i => $payload) {
            Cache::flush(); // القفل بعد 5 محاولات ليس موضوع هذا الاختبار

            $this->post('/login', ['email' => 'real@example.com', 'password' => $payload]);
            $this->assertFalse(auth()->check(), "الحقنة نجحت في تخطّي الدخول: {$payload}");

            $this->post('/login', ['email' => $payload, 'password' => 'anything']);
            $this->assertFalse(auth()->check(), "الحقنة في البريد نجحت: {$payload}");
        }

        // الجداول ما زالت قائمة والمستخدم لم يُمسّ
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'real@example.com']);
        $this->assertSame(1, User::query()->count());
    }

    public function test_the_password_never_reaches_a_query(): void
    {
        User::factory()->create(['email' => 'real@example.com', 'password' => bcrypt('x'), 'is_active' => true]);

        $seen = [];
        DB::listen(function ($q) use (&$seen) { $seen[] = $q->sql . ' :: ' . json_encode($q->bindings); });

        Cache::flush();
        $this->post('/login', ['email' => 'real@example.com', 'password' => "ZAP' OR '1'='1' -- "]);

        foreach ($seen as $line) {
            $this->assertStringNotContainsString("OR '1'='1", $line,
                'كلمة المرور وصلت نصَّ الاستعلام — هذا هو الحقن بعينه');
        }
    }

    public function test_sort_and_direction_are_allowlisted_not_interpolated(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        LegalCase::factory()->count(3)->create();

        $bad = [
            'sort' => 'id; DROP TABLE cases; --',
            'dir' => 'asc, (SELECT 1) --',
        ];

        $this->actingAs($admin)->get('/cases?' . http_build_query($bad))->assertOk();

        // الجدول قائم وصفوفه كما هي
        $this->assertSame(3, LegalCase::query()->count());
    }

    public function test_search_payloads_do_not_change_the_result_set(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        LegalCase::factory()->count(4)->create();

        // بحث لا يطابق شيئاً يجب أن يبقى لا يطابق شيئاً مهما أُضيف إليه
        foreach (["zzzz' OR '1'='1", "zzzz' OR 1=1 -- ", "zzzz'; --"] as $payload) {
            $html = $this->actingAs($admin)
                ->get('/cases?' . http_build_query(['search' => $payload]))
                ->assertOk()->getContent();

            $this->assertStringNotContainsString('data-case-row', $html,
                "الحقنة وسّعت نتيجة البحث: {$payload}");
        }

        $this->assertSame(4, LegalCase::query()->count());
    }

    public function test_why_zap_saw_a_difference_the_attempt_counter_not_sql(): void
    {
        User::factory()->create(['email' => 'real@example.com', 'password' => bcrypt('x'), 'is_active' => true]);
        Cache::flush();

        // نفس الطلب تماماً مرّتين متتاليتين يعطي نصّين مختلفين، لأن
        // عدّاد المحاولات يغيّر الرسالة — وهذا ما قرأه الفاحص «تلاعباً»
        $bodies = [];
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => 'real@example.com', 'password' => 'wrong']);
            $bodies[] = $this->get('/login')->getContent();
        }

        $this->assertNotSame($bodies[0], $bodies[3],
            'إن تطابقت الصفحات فتفسيري لتنبيه ZAP خاطئ ويحتاج بحثاً آخر');
    }
}
