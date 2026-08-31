<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\InboxService;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ما وعد به النظامُ وما لا يُصدَّق من الوارد.
 *
 * ═══ ما تحرسه هذه الاختبارات ═══
 *
 * ١) «للتحدث مع موظف اكتب: موظف» — وعدٌ في رسالةٍ باسم المكتب. فإن
 *    كتبها العميل ولم يحدث شيء، ظلّ ينتظر إنساناً لا يأتي، وقد يكون في
 *    ميعادٍ يسقط. ويُقاس الطلبُ بميزان التعليمة لا الموضوع: من ذكر
 *    «موظف» في جملةٍ لا يُحوَّل خيطُه بلا سبب.
 *
 * ٢) اسمُ ملفّ المُرسِل نصٌّ يكتبه هو ولا يمرّ باستمارةٍ عندنا. فيدخل
 *    إلى النظام مقلَّماً: بلا زوايا ولا محارفَ حاكمةٍ تقلب اتجاه ما
 *    بعدها في قائمة المحادثات.
 *
 * ٣) وحسابُ الموكّل لا يرى واتساب في قائمته ولو مُنح الصلاحية بالخطأ.
 */
class WhatsAppHardeningTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppContact $contact;
    private WhatsAppConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-value-for-testing'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');

        User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->contact = WhatsAppContact::create(['wa_id' => '96891234567']);
        $this->conversation = WhatsAppConversation::create([
            'contact_id' => $this->contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now(),
        ]);
    }

    private function receive(string $body, string $wamid = 'wamid.T1'): ?WhatsAppMessage
    {
        return app(InboxService::class)->ingestIncoming([
            'from' => '96891234567',
            'id' => $wamid,
            'timestamp' => (string) now()->timestamp,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    // ── طلبُ موظّف ───────────────────────────────────────────────

    public function test_the_word_the_system_promised_actually_summons_a_person(): void
    {
        Queue::fake();

        $this->receive('موظف');

        $this->assertNotNull(
            $this->conversation->fresh()->handoff_at,
            'كُتبت الكلمةُ التي وعد بها النظام ولم يُحوَّل الخيط',
        );

        // ولا يردّ الآليُّ بعدها
        $this->assertFalse($this->conversation->fresh()->aiMayReply());

        // ويُطمأن العميلُ أنّ طلبه وصل
        $reply = WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->first();
        $this->assertNotNull($reply, 'لم يُطمأن العميل — ينتظر بلا علمٍ أنّ طلبه سُجّل');
        $this->assertStringContainsString('سيتواصل معكم', (string) $reply->body);
        Queue::assertPushed(SendWhatsAppMessage::class);
    }

    /** ويُقبل «أريد محامي» كذلك — ثلاثُ كلماتٍ فأقلّ تعليمة. */
    public function test_a_short_request_for_a_lawyer_is_a_handoff_too(): void
    {
        Queue::fake();

        $this->receive('اريد محامي');

        $this->assertNotNull($this->conversation->fresh()->handoff_at);
    }

    /** أمّا الجملةُ فحديثٌ لا تعليمة. */
    public function test_mentioning_a_clerk_in_a_sentence_does_not_hand_the_thread_over(): void
    {
        Queue::fake();

        $this->receive('تعاملت مع موظف عندكم أمس وكان متعاوناً جزاه الله خيراً');

        $this->assertNull(
            $this->conversation->fresh()->handoff_at,
            'حُوِّل خيطٌ لمجرّد ذكر الكلمة في جملة — تنبيهٌ بلا سبب وردٌّ آليٌّ أُوقف بلا طلب',
        );
        $this->assertSame(0, WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->count());
    }

    /** والتحويلُ مرّةً واحدة: لا تطمينَ مع كلّ رسالةٍ بعده. */
    public function test_an_already_handed_over_thread_is_not_confirmed_again(): void
    {
        Queue::fake();

        $this->receive('موظف', 'wamid.T1');
        $this->receive('موظف', 'wamid.T2');

        $this->assertSame(1, WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->count());
    }

    /** ومن سجّل رفضَ المراسلة يُحوَّل خيطُه ولا يُراسَل آلياً. */
    public function test_an_opted_out_contact_is_handed_over_but_not_messaged(): void
    {
        Queue::fake();

        $this->contact->forceFill(['opted_out_at' => now()])->save();

        $this->receive('موظف');

        $this->assertNotNull($this->conversation->fresh()->handoff_at);
        $this->assertSame(0, WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->count());
        Queue::assertNothingPushed();
    }

    // ── اسمُ الملفّ الوارد ───────────────────────────────────────

    public function test_a_profile_name_carrying_markup_is_stripped_at_the_door(): void
    {
        $clean = InboxService::sanitizeProfileName('<img src=x onerror=alert(1)>سالم');

        $this->assertStringNotContainsString('<', $clean);
        $this->assertStringNotContainsString('>', $clean);
        $this->assertStringContainsString('سالم', $clean);
    }

    public function test_a_profile_name_carrying_direction_overrides_is_stripped(): void
    {
        $clean = InboxService::sanitizeProfileName("سالم\u{202E}9999");

        $this->assertStringNotContainsString("\u{202E}", $clean);
    }

    public function test_a_profile_name_is_one_line_and_bounded(): void
    {
        $clean = InboxService::sanitizeProfileName("سالم\nالبوسعيدي\t" . str_repeat('ب', 400));

        $this->assertStringNotContainsString("\n", $clean);
        $this->assertLessThanOrEqual(120, mb_strlen($clean));
    }

    // ── رمزُ المزوّد لا يظهر في استجابة ─────────────────────────

    /**
     * تعذّرُ الاتصال بمزوّد الإرسال لا يُعيد نصَّ الاستثناء.
     *
     * الرمزُ في ذلك المزوّد جزءٌ من المسار: `/sendMessage/{token}`،
     * ورسالةُ Guzzle عند التعذّر تحمل العنوانَ كاملاً. فكان يُعرض في
     * الاستجابة لكلّ من يفتح الصفحة، ويثبت في سجلّ المتصفّح وفي أيّ
     * لقطةِ شاشةٍ تُرسَل للدعم.
     */
    public function test_a_failed_provider_call_never_echoes_the_token(): void
    {
        config([
            'services.whatsapp.url' => 'https://api.example-provider.test',
            'services.whatsapp.token' => 'SECRET-PROVIDER-TOKEN-9f2c',
            'services.whatsapp.meta_token' => '',
            'services.whatsapp.meta_phone_id' => '',
            'services.infobip.api_key' => '',
        ]);

        \Illuminate\Support\Facades\Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException(
                'cURL error 7: Failed to connect to api.example-provider.test'
                . ' (see https://api.example-provider.test/sendMessage/SECRET-PROVIDER-TOKEN-9f2c)'
            );
        });

        $admin = User::where('role', 'admin')->firstOrFail();
        $client = Client::create([
            'name' => 'سالم', 'phone' => '91234567', 'type' => 'individual', 'email' => null,
        ]);
        $case = \App\Models\LegalCase::create([
            'case_number' => '2026/9', 'title' => 'قضية', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $client->id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('cases.sendPortalMessage', $case));

        $response->assertStatus(400);

        // أنّ المسارَ المعنيَّ نُفِّذ فعلاً — وإلا كان الاختبار فارغاً
        $this->assertStringContainsString(
            'تعذّر الاتصال بمزوّد الإرسال',
            (string) $response->json('error'),
        );

        $this->assertStringNotContainsString('SECRET-PROVIDER-TOKEN-9f2c', $response->getContent());
        $this->assertStringNotContainsString('sendMessage/', $response->getContent());
    }

    // ── قائمةُ التنقّل ───────────────────────────────────────────

    /**
     * حسابُ الموكّل لا يرى مدخلَ واتساب ولو حُشر في صلاحياته.
     *
     * والوسمُ وحده تسريب: عددُ المحادثات غير المقروءة رقمٌ عن مراسلات
     * موكّلين آخرين.
     */
    public function test_a_client_account_never_sees_the_whatsapp_entry(): void
    {
        $account = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $account->givePermission('whatsapp.view');

        Client::create([
            'name' => 'موكّل', 'phone' => '95555555', 'type' => 'individual',
            'user_id' => $account->id,
        ]);

        $this->actingAs($account)
            ->get(route('dashboard'))
            ->assertDontSee(route('whatsapp.index'));

        // وأنّ الاختبار ليس فارغاً: المديرُ يراه في نفس الصفحة
        $this->actingAs(User::where('role', 'admin')->firstOrFail())
            ->get(route('dashboard'))
            ->assertSee(route('whatsapp.index'));
    }
}
