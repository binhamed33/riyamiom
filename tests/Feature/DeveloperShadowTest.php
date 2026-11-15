<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\DeveloperShadow;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «المطوّر ما يظهر نشاطه بالموقع بتاتاً».
 *
 * يُسجَّل كلُّ شيءٍ كما هو، ويُخفى عند القراءة عن كلّ من ليس مطوّراً:
 * لا في «النشاط الأخير»، ولا سجلّ التدقيق، ولا مسار القضية، ولا بوابة
 * الموكّل، ولا «متصلٌ الآن». وما بقي أثرُه في بيانات المكتب يُنسب إلى
 * «نظام مُداوَلة». والمطوّرُ نفسُه يرى كلَّ شيء.
 */
class DeveloperShadowTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_NAME = 'مطوّر المنظومة الخفيّ';

    private User $dev;
    private User $admin;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        $this->dev = User::factory()->create(['role' => 'developer', 'is_active' => true, 'name' => self::DEV_NAME, 'email' => 'dev@example.test']);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'مدير المكتب']);

        $client = Client::create(['name' => 'موكّل', 'phone' => '91234567', 'type' => 'individual']);
        $this->case = LegalCase::create([
            'case_number' => '2026/9', 'title' => 'قضية', 'type' => 'civil', 'description' => 'و',
            'court' => 'م', 'opponent' => 'خ', 'status' => 'active', 'priority' => 'medium', 'client_id' => $client->id,
        ]);
    }

    private function audit(?User $by, array $extra = []): AuditLog
    {
        return AuditLog::create($extra + [
            'user_id' => $by?->id, 'action' => AuditLog::ACTION_UPDATE,
            'model_type' => LegalCase::class, 'model_id' => $this->case->id, 'case_id' => $this->case->id,
            'old_values' => ['title' => 'قديم'], 'new_values' => ['title' => 'جديد'],
        ]);
    }

    // ── السجلّ والنشاط ───────────────────────────────────────────

    public function test_the_dashboard_feed_hides_the_developer_from_the_office_but_not_from_the_developer(): void
    {
        $this->audit($this->dev);

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertDontSee(self::DEV_NAME);
        $this->actingAs($this->dev)->get(route('dashboard'))->assertOk()->assertSee(self::DEV_NAME);
    }

    public function test_the_audit_log_page_hides_developer_rows_and_name_but_keeps_system_rows(): void
    {
        $mine = $this->audit($this->dev);
        $system = $this->audit(null, ['new_values' => ['title' => 'صفُّ النظام']]);
        $locked = AuditLog::create(['user_id' => null, 'action' => 'login_failed_locked', 'new_values' => ['email' => 'dev@example.test']]);

        $visible = AuditLog::visibleTo($this->admin)->pluck('id')->all();
        $this->assertNotContains($mine->id, $visible);
        $this->assertNotContains($locked->id, $visible, 'محاولاتُ الدخول على بريد المطوّر');
        $this->assertContains($system->id, $visible, 'صفُّ النظام (بلا فاعل) يبقى');

        $this->actingAs($this->admin)->get(route('audit-log.index'))->assertOk()->assertDontSee(self::DEV_NAME);

        // المطوّرُ يرى كلَّ شيء — والصفوفُ لم تُحذف
        $this->assertCount(3, AuditLog::visibleTo($this->dev)->get());
        $this->assertSame(3, AuditLog::count());
    }

    public function test_a_value_naming_the_developer_is_attributed_to_the_platform(): void
    {
        $this->actingAs($this->admin);
        $this->assertSame(__('app.developer_shadow_name'), AuditLog::formatValue('assigned_to', (string) $this->dev->id));
        $this->assertSame('مدير المكتب', AuditLog::formatValue('assigned_to', (string) $this->admin->id));

        $this->actingAs($this->dev);
        $this->assertSame(self::DEV_NAME, AuditLog::formatValue('assigned_to', (string) $this->dev->id));
    }

    public function test_the_case_timeline_and_the_client_portal_hide_the_developers_entries(): void
    {
        CaseActivity::create(['case_id' => $this->case->id, 'user_id' => $this->dev->id, 'type' => CaseActivity::TYPE_NOTE, 'title' => 'فحصُ المطوّر للقضية', 'occurred_at' => now()]);
        CaseActivity::create(['case_id' => $this->case->id, 'user_id' => $this->admin->id, 'type' => CaseActivity::TYPE_NOTE, 'title' => 'ملاحظةُ المدير', 'occurred_at' => now()]);
        $this->audit($this->dev);

        $this->actingAs($this->admin)->get(route('cases.show', $this->case))->assertOk()
            ->assertSee('ملاحظةُ المدير')->assertDontSee('فحصُ المطوّر للقضية')->assertDontSee(self::DEV_NAME);

        $this->assertSame(['ملاحظةُ المدير'], CaseActivity::visibleTo($this->admin)->pluck('title')->all());
        $this->assertCount(2, CaseActivity::visibleTo($this->dev)->get());

        // بوابةُ الموكّل: القارئُ ليس مطوّراً أبداً
        auth()->logout();
        $ids = \App\Services\ClientPortal\ClientCaseGateway::for($this->case->client)->timelineFor($this->case)->pluck('user_id')->all();
        $this->assertNotContains($this->dev->id, $ids);
    }

    public function test_a_document_the_developer_uploaded_is_attributed_to_the_platform(): void
    {
        Document::create([
            'case_id' => $this->case->id, 'uploaded_by' => $this->dev->id, 'title' => 'مستند الفحص',
            'file_path' => 'documents/x.pdf', 'file_type' => 'pdf', 'file_size' => 1, 'access_level' => 'all',
        ]);

        $this->actingAs($this->admin)->get(route('documents.index'))->assertOk()
            ->assertSee('مستند الفحص')->assertDontSee(self::DEV_NAME)->assertSee(__('app.developer_shadow_name'));
    }

    // ── الحضور ───────────────────────────────────────────────────

    public function test_the_developers_presence_is_not_stamped(): void
    {
        $this->actingAs($this->dev)->get(route('dashboard'))->assertOk();
        $this->assertNull(DB::table('users')->where('id', $this->dev->id)->value('last_seen_at'));
        $this->assertFalse(cache()->has('staff_active_' . $this->dev->id));

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $this->assertNotNull(DB::table('users')->where('id', $this->admin->id)->value('last_seen_at'));
    }

    public function test_the_developer_is_not_ranked_with_the_team(): void
    {
        $ids = \App\Http\Controllers\FeasibilityController::teamEfficiency()->map(fn ($row) => $row['user']->id)->all();

        $this->assertNotContains($this->dev->id, $ids);
        $this->assertContains($this->admin->id, $ids);
    }

    // ── واتساب ───────────────────────────────────────────────────

    public function test_the_developer_opening_a_whatsapp_thread_leaves_it_unread(): void
    {
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-tests'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');
        \Illuminate\Support\Facades\Http::fake();

        $contact = WhatsAppContact::create(['wa_id' => '96891234567', 'profile_name' => 'مستفسر']);
        $c = WhatsAppConversation::create(['contact_id' => $contact->id, 'status' => 'open', 'unread_count' => 2, 'last_inbound_at' => now()]);
        WhatsAppMessage::create(['conversation_id' => $c->id, 'direction' => 'in', 'type' => 'text', 'wamid' => 'wamid.A', 'body' => 'مرحبا', 'status' => 'delivered', 'sent_at' => now()]);

        $this->actingAs($this->dev)->get(route('whatsapp.show', $c))->assertOk();
        $this->assertSame(2, $c->fresh()->unread_count);
        \Illuminate\Support\Facades\Http::assertNothingSent();

        $this->actingAs($this->admin)->get(route('whatsapp.show', $c))->assertOk();
        $this->assertSame(0, $c->fresh()->unread_count);
    }

    public function test_the_helper_names(): void
    {
        $this->assertSame(__('app.developer_shadow_name'), DeveloperShadow::name($this->dev, null, $this->admin));
        $this->assertSame(self::DEV_NAME, DeveloperShadow::name($this->dev, null, $this->dev));
        $this->assertSame('مدير المكتب', DeveloperShadow::name($this->admin, null, $this->admin));
        $this->assertSame('—', DeveloperShadow::name(null, '—'));
    }

    // ── ما أكّدته المراجعة ──────────────────────────────────────

    /** أثرُ البيانات (جلسةٌ أدخلها المطوّر) وأحداثُ الأتمتة تبقى — منسوبةً إلى المنظومة. */
    public function test_data_traces_and_automation_events_stay_visible_under_the_platform_name(): void
    {
        CaseActivity::create(['case_id' => $this->case->id, 'user_id' => $this->dev->id, 'type' => CaseActivity::TYPE_SESSION, 'title' => 'جلسة جديدة أدخلها المطوّر', 'occurred_at' => now()]);
        CaseActivity::create(['case_id' => $this->case->id, 'user_id' => $this->dev->id, 'type' => CaseActivity::TYPE_NOTE, 'created_via' => 'automation', 'title' => '⚙️ حدثُ أتمتة', 'occurred_at' => now()]);
        CaseActivity::create(['case_id' => $this->case->id, 'user_id' => $this->dev->id, 'type' => CaseActivity::TYPE_CALL, 'title' => 'اتصالُ المطوّر', 'occurred_at' => now()]);

        $titles = CaseActivity::visibleTo($this->admin)->pluck('title')->all();
        $this->assertContains('جلسة جديدة أدخلها المطوّر', $titles);
        $this->assertContains('⚙️ حدثُ أتمتة', $titles);
        $this->assertNotContains('اتصالُ المطوّر', $titles);

        $this->actingAs($this->admin);
        $row = CaseActivity::where('title', 'جلسة جديدة أدخلها المطوّر')->first();
        $this->assertSame(__('app.developer_shadow_name'), $row->actorLabel());

        // والموكّلُ يرى موعدَ جلسته في البوابة وإن أدخلها المطوّر
        auth()->logout();
        $portal = \App\Services\ClientPortal\ClientCaseGateway::for($this->case->client)->timelineFor($this->case)->pluck('title')->all();
        $this->assertContains('جلسة جديدة أدخلها المطوّر', $portal);
    }

    /** محاولاتُ الدخول على بريد المطوّر بأيّ حالة أحرف — مخفيّة. */
    public function test_locked_login_rows_are_hidden_whatever_the_letter_case(): void
    {
        $upper = AuditLog::create(['user_id' => null, 'action' => 'login_failed_locked', 'new_values' => ['email' => 'DEV@Example.Test']]);
        $other = AuditLog::create(['user_id' => null, 'action' => 'login_failed_locked', 'new_values' => ['email' => 'someone@else.test']]);

        $visible = AuditLog::visibleTo($this->admin)->pluck('id')->all();
        $this->assertNotContains($upper->id, $visible);
        $this->assertContains($other->id, $visible);
    }

    /** مساعدُ القضية للمطوّر: لا يُحفظ في القضية، ويتذكّر ما قبله في جلسته. */
    public function test_the_developers_case_ai_chat_is_not_saved_but_keeps_its_context(): void
    {
        \App\Support\AiSettings::store('gemini', 'AIzaSyTESTKEY1234567890abcd', 'gemini-flash-latest');
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(
            ['candidates' => [['content' => ['parts' => [['text' => 'ردُّ المساعد']]]]]], 200)]);

        $this->actingAs($this->dev)->postJson(route('cases.ai_chat', $this->case), ['message' => 'لخّص القضية'])->assertOk();
        $this->actingAs($this->dev)->postJson(route('cases.ai_chat', $this->case), ['message' => 'وماذا عن الجلسة الثانية؟'])->assertOk();

        $this->assertSame(0, $this->case->aiMessages()->count(), 'محادثةُ المطوّر لا تُحفظ في القضية');

        $requests = \Illuminate\Support\Facades\Http::recorded();
        $last = json_encode($requests[count($requests) - 1][0]->data(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('لخّص القضية', $last, 'السؤالُ الثاني يُرسَل ومعه الأوّل');
    }
}
