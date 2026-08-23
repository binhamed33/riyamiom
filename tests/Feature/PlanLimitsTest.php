<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Support\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * حدود الباقة تُفرَض في الخادم — إخفاء زرّ ليس منعاً.
 *
 * ولا يُحذف شيء عند التجاوز أبداً: مكتبٌ صار فوق حدّه (بتخفيض باقة
 * مثلاً) يبقى كل ما فيه، ويُمنع من الإضافة وحدها.
 */
class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function limit(array $limits, string $plan = 'مُداوَلة | بداية'): void
    {
        PlanLimits::sync('bidaya', $plan, $limits);
    }

    // ── الفشل مفتوح ──────────────────────────────────────────────

    public function test_an_office_with_no_synced_limits_is_unlimited(): void
    {
        // مكتب لم تصله حدود بعد: نسخة قديمة، أو جسر غير مربوط.
        // إقفاله لأنّ نبضةً لم تصل أسوأ من تجاوزٍ يوماً.
        $this->assertSame([], PlanLimits::all());
        $this->assertFalse(PlanLimits::reached('users'));
        $this->assertFalse(PlanLimits::reached('clients'));
        $this->assertNull(PlanLimits::of('cases'));
    }

    public function test_garbage_limits_are_refused_rather_than_stored(): void
    {
        $this->limit(['users' => 5]);
        PlanLimits::sync('bidaya', 'باقة', ['users' => -1, 'nonsense' => 9]);

        // القيم الفاسدة لا تمحو الحدود السليمة الموجودة
        $this->assertSame(5, PlanLimits::of('users'));
    }

    // ── المنع الفعلي ─────────────────────────────────────────────

    public function test_a_sixth_user_is_refused_by_the_backend(): void
    {
        $this->limit(['users' => 5]);
        User::factory()->count(5)->create(['role' => 'lawyer']);

        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'موظف سادس',
                'email' => 'sixth@office.test',
                'password' => 'Str0ng!Passw0rd#2026',
                'password_confirmation' => 'Str0ng!Passw0rd#2026',
                'role' => 'staff',
            ])
            ->assertSessionHasErrors('limit');

        $this->assertSame(0, User::where('email', 'sixth@office.test')->count());
    }

    public function test_the_limit_message_names_the_resource_and_the_numbers(): void
    {
        $this->limit(['clients' => 2]);
        Client::factory()->count(2)->create();

        $message = PlanLimits::message('clients');

        $this->assertStringContainsString('الموكّلون', $message);
        $this->assertStringContainsString('2 من 2', $message);
        $this->assertStringContainsString('مُداوَلة | بداية', $message);
    }

    public function test_below_the_limit_creation_still_works(): void
    {
        $this->limit(['clients' => 10]);

        $this->actingAs($this->admin())
            ->post('/clients', ['name' => 'موكّل جديد', 'type' => 'individual'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Client::count());
    }

    public function test_the_ajax_path_is_guarded_too(): void
    {
        // طريقان لإنشاء موكّل — حراسة أحدهما تجعل الآخر التفافاً
        $this->limit(['clients' => 1]);
        Client::factory()->create();

        $this->actingAs($this->admin())
            ->postJson('/clients/ajax', ['name' => 'التفاف'])
            ->assertStatus(422)
            ->assertJsonPath('limit_reached', 'clients');

        $this->assertSame(1, Client::count());
    }

    // ── التخفيض لا يحذف ──────────────────────────────────────────

    public function test_going_over_the_limit_never_deletes_anything(): void
    {
        Client::factory()->count(8)->create();

        // الباقة خُفّضت تحت الاستهلاك الحالي
        $this->limit(['clients' => 3]);

        $this->assertSame(8, Client::count(), 'حُذف موكّلون عند التخفيض');
        $this->assertTrue(PlanLimits::reached('clients'));

        // والقراءة تبقى عاملة تماماً
        $this->actingAs($this->admin())->get('/clients')->assertOk();
    }

    // ── المزامنة من اللوحة ───────────────────────────────────────

    public function test_the_heartbeat_stores_the_limits_that_come_down(): void
    {
        config([
            'panel.ingest_url' => 'https://panel.test',
            'panel.ingest_token' => 'tok',
        ]);

        Http::fake(['*/ingest/heartbeat' => Http::response([
            'ok' => true,
            'plan' => [
                'key' => 'bidaya',
                'name' => 'مُداوَلة | بداية',
                'limits' => ['users' => 5, 'clients' => 500, 'cases' => 500, 'documents' => 5000, 'storage_gb' => 10],
            ],
        ])]);

        $this->assertTrue(\App\Services\PanelReporter::heartbeat());

        $this->assertSame(5, PlanLimits::of('users'));
        $this->assertSame('مُداوَلة | بداية', PlanLimits::planName());
    }

    public function test_a_panel_that_sends_no_plan_leaves_limits_untouched(): void
    {
        $this->limit(['users' => 5]);

        config(['panel.ingest_url' => 'https://panel.test', 'panel.ingest_token' => 'tok']);
        Http::fake(['*/ingest/heartbeat' => Http::response(['ok' => true])]);

        \App\Services\PanelReporter::heartbeat();

        $this->assertSame(5, PlanLimits::of('users'), 'ردٌّ بلا باقة محا الحدود');
    }

    // ── الشاشة وطلب الترقية ──────────────────────────────────────

    public function test_the_plan_screen_shows_usage_against_limits(): void
    {
        $this->limit(['clients' => 500]);
        Client::factory()->count(3)->create();

        $this->actingAs($this->admin())->get('/plan')
            ->assertOk()
            ->assertSee('الموكّلون')
            ->assertSee('500');
    }

    public function test_an_unlinked_office_is_told_the_truth_not_a_false_success(): void
    {
        config(['panel.ingest_url' => null, 'panel.ingest_token' => null]);

        $this->actingAs($this->admin())
            ->post('/plan/upgrade', ['reason' => 'نحتاج مستخدمين'])
            ->assertSessionHasErrors('upgrade');
    }

    public function test_a_linked_office_sends_the_upgrade_request(): void
    {
        config(['panel.ingest_url' => 'https://panel.test', 'panel.ingest_token' => 'tok']);
        Http::fake(['*/ingest/upgrade-request' => Http::response(['ok' => true, 'id' => 1])]);

        $this->actingAs($this->admin())
            ->post('/plan/upgrade', ['reason' => 'نحتاج مستخدمين'])
            ->assertSessionHas('success');
    }

    public function test_a_lawyer_cannot_reach_the_plan_screen(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $this->actingAs($lawyer)->get('/plan')->assertRedirect();
    }
}
