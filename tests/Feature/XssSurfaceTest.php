<?php

namespace Tests\Feature;

use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الحقول التي يكتبها المستخدم لا تخرج كـHTML.
 *
 * الخطر المحدَّد: صفحة المالية تحقن JSON خامّاً داخل خاصية HTML:
 *     @click="$dispatch('set-tx', {!! $tx->toJson() !!})"
 * وtoJson لا يهرّب علامة الاقتباس المزدوجة، فوصفٌ فيه اقتباس يُنهي
 * الخاصية ويفتح الباب لخاصية جديدة — وهي بوّابة XSS مخزَّن الكلاسيكية:
 * موظّف يكتب الوصف، وكل من يفتح الصفحة يُنفّذه.
 *
 * الاختبار لا يقرأ الكود بل يُرسل قيمة عدائية ويقرأ المخرَج.
 */
class XssSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '"><img src=x onerror=alert(1)>';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_a_hostile_description_cannot_break_out_of_the_finance_attribute(): void
    {
        $admin = $this->admin();

        FinanceTransaction::create([
            'type' => 'income',
            'category' => 'أتعاب',
            'amount' => 100,
            'description' => self::PAYLOAD,
            'date' => now()->toDateString(),
            'user_id' => $admin->id,
        ]);

        $html = $this->actingAs($admin)->get(route('finance.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('onerror=alert(1)>', $html,
            'وصفٌ عدائي خرج كـHTML قابل للتنفيذ في صفحة المالية');
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_a_hostile_user_name_does_not_execute_anywhere_it_is_shown(): void
    {
        $attacker = User::factory()->create([
            'name' => self::PAYLOAD,
            'role' => 'lawyer',
            'is_active' => true,
        ]);

        $html = $this->actingAs($this->admin())->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<img src=x', $html,
            'اسم مستخدم عدائي خرج كـHTML في صفحة المستخدمين');

        // الاسم نفسه يجب أن يظهر — مهرَّباً لا محذوفاً
        $this->assertStringContainsString('&lt;img', $html);
        unset($attacker);
    }

    public function test_a_hostile_client_name_is_escaped_on_the_clients_page(): void
    {
        \App\Models\Client::factory()->create(['name' => self::PAYLOAD]);

        $html = $this->actingAs($this->admin())->get(route('clients.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_the_audit_log_escapes_the_values_it_replays(): void
    {
        \App\Models\AuditLog::create([
            'user_id' => $this->admin()->id,
            'action' => 'update',
            'model_type' => 'App\\Models\\Client',
            'model_id' => 1,
            'new_values' => ['name' => self::PAYLOAD],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
        ]);

        $html = $this->actingAs($this->admin())->get(route('audit-log.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<img src=x', $html,
            'سجل التدقيق يُعيد عرض قيمة عدائية كـHTML');
    }

    public function test_an_apostrophe_in_a_case_title_cannot_break_the_monthly_script(): void
    {
        // كان JSON يُحقن داخل نصّ JS بفاصلة عليا:
        //     JSON.parse('{!! json_encode(...) !!}')
        // وjson_encode لا يهرّب الفاصلة العليا — فعنوان قضية فيه ' يُنهي
        // نصّ الجافاسكربت. و«شركة عبدالله'» عنوانٌ عادي لا هجوم.
        $client = \App\Models\Client::factory()->create();

        // opened_at مضبوط على الشهر الحالي عمداً: الصفحة تُرشّح به،
        // وقضيةٌ خارج الشهر تجعل القائمة فارغة فيمرّ الاختبار بلا أن
        // يفحص شيئاً — وهذا ما وقع في أول صياغة له.
        \App\Models\LegalCase::factory()->create([
            'client_id' => $client->id,
            'title' => "قضية شركة O'Brien </script><img src=x onerror=alert(1)>",
            'opened_at' => now(),
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('cases.monthly'))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><img', $html,
            'عنوان قضية أغلق وسم السكربت وحقن HTML');
        $this->assertStringNotContainsString('onerror=alert(1)>', $html);
    }
}
