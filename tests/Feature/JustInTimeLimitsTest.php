<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ترقيةُ الباقة تسري لحظةَ المحاولة التالية — لا بعد ربع ساعة.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * الحدود تنزل من اللوحة في ردّ النبضة، والنبضة كل ربع ساعة. فرُقّيت
 * باقةُ مكتبٍ من اللوحة، وعاد صاحبُه يضيف مستخدمَه فرُفض ثانيةً —
 * «باقتك لا تسمح» — بحدود الباقة القديمة. ترقيةٌ دُفع ثمنها ثم تُنتظر.
 *
 * فصار المكتب يطلب حدوداً طازجةً لحظةَ يهمّ بالرفض: نداءٌ واحد مخنوق،
 * في مسار الرفض وحده، وردُّه هو ردُّ النبضة نفسه فلا قناةَ مزامنةٍ
 * ثانية تُخترع.
 */
class JustInTimeLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function linkToPanel(): void
    {
        config(['panel.ingest_url' => 'https://panel.example', 'panel.ingest_token' => 'tok']);
    }

    private function withPlan(string $name, int $users): void
    {
        PlanLimits::sync('x', $name, [
            'users' => $users, 'clients' => 500, 'cases' => 500,
            'documents' => 5000, 'storage_gb' => 10,
        ]);
    }

    /** ردُّ نبضةٍ من اللوحة يحمل باقةً بحدٍّ جديد. */
    private function panelAnswers(int $users): void
    {
        Http::fake([
            'panel.example/*' => Http::response([
                'ok' => true,
                'plan' => [
                    'key' => 'ihtiraf',
                    'name' => 'مُداوَلة | احتراف',
                    'limits' => [
                        'users' => $users, 'clients' => 2500, 'cases' => 2500,
                        'documents' => 25000, 'storage_gb' => 50,
                    ],
                ],
            ], 200),
        ]);
    }

    private function fillUsers(int $count): void
    {
        User::factory()->count($count)->create(['role' => 'lawyer', 'is_active' => true]);
    }

    // ─────────────────────────────────── الجوهر

    /** ═══ ما وقع بالحرف: رُقّي من اللوحة، فالمحاولة التالية تنجح ═══ */
    public function test_an_upgrade_in_the_panel_takes_effect_on_the_very_next_attempt(): void
    {
        $this->linkToPanel();
        $this->withPlan('مُداوَلة | بداية', 2);
        $this->fillUsers(2);
        $this->panelAnswers(20);

        $this->assertFalse(PlanLimits::reached('users'), 'رفض بحدودٍ قديمة رغم ترقيةٍ قائمة في اللوحة');
        $this->assertSame('مُداوَلة | احتراف', PlanLimits::planName(), 'الحدود وصلت والاسم لم يصل');
    }

    /** وإن لم تكن ترقية فالرفض يبقى — بلا استثناءٍ يطفو. */
    public function test_no_upgrade_means_the_refusal_stands(): void
    {
        $this->linkToPanel();
        $this->withPlan('مُداوَلة | بداية', 2);
        $this->fillUsers(2);

        Http::fake([
            'panel.example/*' => Http::response([
                'ok' => true,
                'plan' => ['key' => 'bidaya', 'name' => 'مُداوَلة | بداية', 'limits' => [
                    'users' => 2, 'clients' => 500, 'cases' => 500,
                    'documents' => 5000, 'storage_gb' => 10,
                ]],
            ], 200),
        ]);

        $this->assertTrue(PlanLimits::reached('users'));
    }

    // ─────────────────────────────────── لا يُبطأ أحدٌ ولا تُقصف اللوحة

    /** من هو دون حدّه لا يلمس الشبكة أصلاً. */
    public function test_nobody_under_the_limit_pays_a_network_call(): void
    {
        $this->linkToPanel();
        $this->withPlan('مُداوَلة | بداية', 5);
        $this->fillUsers(2);
        Http::fake();

        $this->assertFalse(PlanLimits::reached('users'));
        Http::assertNothingSent();
    }

    /** والرافضون المتزاحمون نداءٌ واحد — الخنقُ بفاصل نصف دقيقة. */
    public function test_repeated_refusals_make_one_call_not_a_flood(): void
    {
        $this->linkToPanel();
        $this->withPlan('مُداوَلة | بداية', 2);
        $this->fillUsers(2);

        Http::fake(['panel.example/*' => Http::response([
            'ok' => true,
            'plan' => ['key' => 'bidaya', 'name' => 'بداية', 'limits' => [
                'users' => 2, 'clients' => 500, 'cases' => 500,
                'documents' => 5000, 'storage_gb' => 10,
            ]],
        ], 200)]);

        PlanLimits::reached('users');
        PlanLimits::reached('users');
        PlanLimits::reached('users');

        Http::assertSentCount(1);
    }

    /** ومكتبٌ غير مربوط لا يحاول: لا جسرَ يسأله. */
    public function test_an_unlinked_office_does_not_try(): void
    {
        config(['panel.ingest_url' => '', 'panel.ingest_token' => '']);
        $this->withPlan('مُداوَلة | بداية', 2);
        $this->fillUsers(2);
        Http::fake();

        $this->assertTrue(PlanLimits::reached('users'));
        Http::assertNothingSent();
    }

    /**
     * ولوحةٌ لا تُجيب لا تفتح الحدّ.
     *
     * الفشل هنا مغلقٌ عمداً، عكسَ غيابِ الحدود كلّها: حدٌّ معروفٌ
     * يُحترم حتى يصل بديلُه — وإلا صار قطعُ الجسر طريقةً للتجاوز.
     */
    public function test_an_unreachable_panel_does_not_open_the_gate(): void
    {
        $this->linkToPanel();
        $this->withPlan('مُداوَلة | بداية', 2);
        $this->fillUsers(2);

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));

        $this->assertTrue(PlanLimits::reached('users'));
    }

    // ─────────────────────────────────── من طرف الشاشة

    /** والمشهدُ نفسه من المتصفّح: «حفظ» بعد الترقية يمرّ. */
    public function test_the_blocked_save_succeeds_right_after_the_upgrade(): void
    {
        $this->linkToPanel();
        $this->withPlan('مُداوَلة | بداية', 2);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->fillUsers(1); // مع المدير: اثنان — الحدّ مبلوغ
        $this->panelAnswers(20);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'أبو حمد',
            'email' => 'new@example.om',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
            'role' => 'lawyer',
        ])->assertSessionMissing('limit_reached');

        $this->assertDatabaseHas('users', ['email' => 'new@example.om']);
    }
}
