<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * حين يمنع الحدُّ، يُقال لماذا ويُعرض الطريق.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * أربعةُ متحكّمات تمنع الإنشاء وتضع في الجلسة limit_reached ومعه رسالةٌ
 * مكتوبة تشرح السبب وتذكر الترقية — ولم يكن في التطبيق كلِّه موضعٌ واحد
 * يقرؤهما. فيضغط المستخدم «حفظ» فلا يُحفظ شيء ولا يُقال له لماذا:
 * صفحةٌ تعود كما كانت، ونجاحٌ لم يقع وخطأٌ لم يُعلَن.
 */
class LimitNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function withPlan(int $users = 2): void
    {
        PlanLimits::sync('bidaya', 'مُداوَلة | بداية', [
            'users' => $users, 'clients' => 500, 'cases' => 500,
            'documents' => 5000, 'storage_gb' => 10,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function fillUsersToLimit(): void
    {
        // المدير نفسه يُحسب، فيكفي واحدٌ آخر لبلوغ حدّ اثنين
        User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function createUser(User $actor): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor)->post(route('users.store'), [
            'name' => 'مستخدم جديد',
            'email' => 'new' . fake()->unique()->numberBetween(1, 9999) . '@example.om',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
            'role' => 'lawyer',
        ]);
    }

    // ─────────────────────────────────────────── المنع يُقال

    /** ═══ ما اشتكى منه المستخدم ═══ */
    public function test_a_blocked_creation_explains_the_plan_does_not_allow_it(): void
    {
        $this->withPlan(2);
        $admin = $this->admin();
        $this->fillUsersToLimit();

        $this->createUser($admin)->assertRedirect();

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('باقتك لا تسمح بإضافة المستخدمون أكثر')
            ->assertSee('مُداوَلة | بداية');
    }

    /** ويُقال إنّ القائم لا يُمسّ — المنع على الجديد وحده. */
    public function test_it_says_existing_data_is_untouched(): void
    {
        $this->withPlan(2);
        $admin = $this->admin();
        $this->fillUsersToLimit();
        $this->createUser($admin);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertSee('ما هو مسجَّل عندك يبقى كما هو');
    }

    /** ويُعرض طريقُ الترقية لمن يملكه. */
    public function test_an_admin_is_offered_the_upgrade_button(): void
    {
        $this->withPlan(2);
        $admin = $this->admin();
        $this->fillUsersToLimit();
        $this->createUser($admin);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertSee('اطلب ترقية الباقة')
            ->assertSee(route('plan.upgrade'), false);
    }

    /**
     * ولا يُعرض زرٌّ سيُرفض.
     *
     * مسارُ الترقية مقصورٌ على الإدارة، وعرضُ زرٍّ لمن لا يملكه دعوةٌ
     * إلى بابٍ مغلق — يُقال له بدلها من يفتحه.
     */
    public function test_a_lawyer_is_told_who_can_upgrade_instead_of_a_button_that_would_be_refused(): void
    {
        $this->withPlan(2);
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        // نضع الحالة في الجلسة مباشرةً: المحامي لا يملك إنشاء مستخدم أصلاً
        $page = $this->actingAs($lawyer)
            ->withSession(['limit_reached' => 'users'])
            ->get(route('dashboard'));

        $page->assertOk()
            ->assertSee('تواصل مع مدير المكتب')
            ->assertDontSee('اطلب ترقية الباقة');
    }

    // ─────────────────────────────────────────── ولا يظهر بلا سبب

    public function test_nothing_is_shown_when_no_limit_was_hit(): void
    {
        $this->withPlan(20);

        $this->actingAs($this->admin())->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('باقتك لا تسمح');
    }

    /** وهو في التخطيط، فيظهر لأي مورد لا لصفحة المستخدمين وحدها. */
    public function test_it_shows_for_any_resource_because_it_lives_in_the_layout(): void
    {
        $this->withPlan(2);

        $this->actingAs($this->admin())
            ->withSession(['limit_reached' => 'cases'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('باقتك لا تسمح بإضافة القضايا أكثر');
    }

    /**
     * والرقمان لا ينقلبان.
     *
     * «2 / 2» في فقرةٍ عربية تنقلب على الشاشة، فيقرأ المستخدم الحدَّ
     * استهلاكاً — وهذا إشعارٌ كلُّ غرضه أن يُفهم من أول نظرة.
     */
    public function test_the_used_over_limit_pair_is_pinned_left_to_right(): void
    {
        $this->withPlan(2);

        $html = $this->actingAs($this->admin())
            ->withSession(['limit_reached' => 'users'])
            ->get(route('dashboard'))->getContent();

        $this->assertMatchesRegularExpression('/dir="ltr"[^>]*>\s*\d+ \/ 2/u', $html);
    }

    /** وازدحامُ القفل ليس بلوغَ حدّ — لا يُعرض له زرُّ ترقية. */
    public function test_a_lock_contention_message_does_not_offer_an_upgrade(): void
    {
        Setting::query()->where('key', PlanLimits::KEY)->delete();

        $this->actingAs($this->admin())
            ->withSession(['limit_reached' => 'users'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ازدحامٍ لحظي')
            ->assertDontSee('اطلب ترقية الباقة');
    }
}
