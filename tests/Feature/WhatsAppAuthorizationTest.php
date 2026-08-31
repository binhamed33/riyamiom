<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * من يرى محادثات واتساب ومن يردّ ومن يربط.
 *
 * ═══ لماذا ثلاث صلاحيات لا واحدة ═══
 *
 * مراسلاتُ الموكّلين ليست كسائر الشاشات: من يقرأها يطّلع على ما لم
 * يُقيَّد في قضيّةٍ بعد، ومن يردّ يتكلّم باسم المكتب أمام موكّل، ومن
 * يربط يكتب في ملفّ القضية نفسه. فالفصلُ بينها ليس تعقيداً بل هو
 * الفرقُ بين موظّف استقبالٍ ومحامٍ ومدير.
 *
 * وحسابُ الموكّل (بوابة العملاء) لا يبلغ شيئاً من هذا إطلاقاً.
 */
class WhatsAppAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-tests'), 'whatsapp');

        // صندوقُ الوارد مخفيٌّ افتراضاً — وهذه الاختباراتُ تفحصه
        // نفسَه، فتُشغّله صراحةً كما يشغّله المكتب الذي يريده.
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');

        $contact = WhatsAppContact::create(['wa_id' => '96891234567', 'profile_name' => 'مستفسر']);
        $this->conversation = WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now(),
        ]);
    }

    /**
     * «مرفوض» في هذا النظام تحويلٌ إلى لوحة التحكّم برسالة، لا 403 خام:
     * معالجُ الاستثناءات في bootstrap/app.php يحوّل 403 لطلبات HTML
     * عمداً كي يرى المستخدم سبب المنع بدل صفحة خطأ. فالاختبار يقيس
     * ما يقع فعلاً لا ما يُفترض.
     */
    private function assertRefused(TestResponse $response): void
    {
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    private function user(string $role, array $permissions = []): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        foreach ($permissions as $permission) {
            $user->permissions()->create(['permission' => $permission]);
        }

        return $user;
    }

    // ── الموكّل ──────────────────────────────────────────────────

    /**
     * حسابُ الموكّل لا يبلغ صندوق الوارد بأيّ طريق.
     *
     * لو بلغه لرأى مراسلاتِ موكّلين آخرين مع المكتب — وهو أخطر تسريبٍ
     * ممكن في نظام محاماة.
     */
    public function test_a_client_account_cannot_reach_the_inbox_at_all(): void
    {
        $client = $this->user('client');

        $this->assertRefused($this->actingAs($client)->get(route('whatsapp.index')));
        $this->assertRefused($this->actingAs($client)->get(route('whatsapp.show', $this->conversation)));
        $this->assertRefused($this->actingAs($client)->post(route('whatsapp.send', $this->conversation), ['body' => 'اختراق']));
        $this->assertRefused($this->actingAs($client)->post(route('whatsapp.link-case', $this->conversation), ['case_id' => 1]));

        $this->assertSame(0, WhatsAppMessage::count());
    }

    /** وحتى لو مُنح الموكّل الصلاحية خطأً — دورُه يمنعه. */
    public function test_a_client_account_holding_the_permission_is_still_refused(): void
    {
        $client = $this->user('client', ['whatsapp.view', 'whatsapp.send', 'whatsapp.manage']);

        $this->assertRefused($this->actingAs($client)->get(route('whatsapp.index')));
    }

    public function test_a_guest_reaches_nothing(): void
    {
        $this->get(route('whatsapp.index'))->assertRedirect(route('login'));
        $this->post(route('whatsapp.send', $this->conversation), ['body' => 'x'])->assertRedirect(route('login'));
    }

    // ── الموظّف ──────────────────────────────────────────────────

    /** موظّفٌ بلا صلاحية لا يرى الصندوق. */
    public function test_an_employee_without_the_permission_cannot_view(): void
    {
        $this->assertRefused($this->actingAs($this->user('staff'))->get(route('whatsapp.index')));
    }

    public function test_an_employee_with_view_can_read_but_not_link(): void
    {
        $employee = $this->user('staff', ['whatsapp.view']);

        $this->actingAs($employee)->get(route('whatsapp.index'))->assertOk();
        $this->actingAs($employee)->get(route('whatsapp.show', $this->conversation))->assertOk();

        // القراءةُ لا تعني الردّ ولا الربط
        $this->assertRefused($this->actingAs($employee)
            ->post(route('whatsapp.send', $this->conversation), ['body' => 'رد']));
        $this->assertRefused($this->actingAs($employee)
            ->post(route('whatsapp.link-client', $this->conversation), ['client_id' => null]));
    }

    public function test_an_employee_with_send_can_reply_but_not_link(): void
    {
        $employee = $this->user('staff', ['whatsapp.view', 'whatsapp.send']);

        $this->actingAs($employee)
            ->post(route('whatsapp.send', $this->conversation), ['body' => 'وعليكم السلام'])
            ->assertRedirect();

        $this->assertSame(1, WhatsAppMessage::where('direction', WhatsAppMessage::OUT)->count());

        $this->assertRefused($this->actingAs($employee)
            ->post(route('whatsapp.link-case', $this->conversation), ['case_id' => null]));
    }

    // ── إعدادات الربط ────────────────────────────────────────────

    /**
     * ربطُ الرقم لمن يدير الإعدادات وحده.
     *
     * الرمزُ يُرسل باسم المكتب كلِّه؛ ومن يملك تبديله يملك انتحالَ
     * صوت المكتب أمام كلّ موكّليه.
     */
    public function test_only_settings_managers_may_connect_or_disconnect_the_number(): void
    {
        $lawyer = $this->user('lawyer', ['whatsapp.view', 'whatsapp.send', 'whatsapp.manage']);

        $this->assertRefused($this->actingAs($lawyer)
            ->post(route('settings.whatsapp.update'), ['wa_phone_number_id' => '999']));

        $this->assertRefused($this->actingAs($lawyer)
            ->delete(route('settings.whatsapp.disconnect')));

        $admin = $this->user('admin');
        $this->actingAs($admin)
            ->post(route('settings.whatsapp.update'), ['wa_phone_number_id' => '444555666'])
            ->assertRedirect();

        $this->assertSame('444555666', WhatsAppSettings::phoneNumberId());
    }

    /** والرمزُ لا يظهر في أيّ صفحةٍ ولا استجابة مهما كان الدور. */
    public function test_the_access_token_is_never_rendered_anywhere(): void
    {
        $secret = 'EAA-super-secret-token-value-1234567890';
        WhatsAppSettings::store($secret, '111222333', '999', 'app-secret-value-here');

        $admin = $this->user('admin');

        foreach ([route('whatsapp.index'), route('whatsapp.show', $this->conversation)] as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk();   // صفحةٌ لم تُعرض لا تُثبت شيئاً عن ما تعرضه
            $body = $response->getContent();

            $this->assertStringNotContainsString($secret, $body, 'ظهر الرمز في ' . $url);
            $this->assertStringNotContainsString('app-secret-value-here', $body);
        }

        // وحتى في المخزَّن: مشفَّرٌ لا نصّ صريح
        $stored = (string) Setting::get(WhatsAppSettings::KEY_TOKEN);
        $this->assertNotSame($secret, $stored, 'خُزّن الرمز نصّاً صريحاً');
        $this->assertSame($secret, Crypt::decryptString($stored));
    }

    /**
     * سببُ الإخفاق المدوَّن لا يحمل سرّاً — ولو حمله نصُّ Meta.
     *
     * هذا النصّ يُعرض في صفحة الإعدادات وفي مخرَج whatsapp:doctor.
     * وMailIdentity::scrub تعرف اعتماداتِ البريد وحدها، فلو اتُّكل
     * عليها وحدها لظهر رمزُ المكتب على الشاشة يومَ يعيده المزوّد في
     * رسالة خطأ.
     */
    public function test_a_recorded_error_never_keeps_a_token(): void
    {
        $token = 'EAAG' . str_repeat('x', 40);
        WhatsAppSettings::store($token, '111222333', '999', 'the-app-secret-value');

        WhatsAppSettings::recordError('فشل الطلب بالرمز ' . $token . ' والسرّ the-app-secret-value');

        $stored = (string) Setting::get(WhatsAppSettings::KEY_LAST_ERROR);

        $this->assertStringNotContainsString($token, $stored);
        $this->assertStringNotContainsString('the-app-secret-value', $stored);
        $this->assertStringContainsString('[محجوب]', $stored);

        // ورمزٌ على هيئة Meta ليس رمزَنا يُحجب أيضاً
        WhatsAppSettings::recordError('رمز آخر: EAA' . str_repeat('z', 30));
        $this->assertStringNotContainsString(str_repeat('z', 30), (string) Setting::get(WhatsAppSettings::KEY_LAST_ERROR));
    }

    /** وفصلُ الرقم يمحو الاعتمادات ولا يمحو المراسلات. */
    public function test_disconnecting_clears_credentials_and_keeps_the_history(): void
    {
        WhatsAppSettings::store('EAA-a-token-value-long-enough-x', '111222333', '999', 'a-secret-value');
        WhatsAppMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => WhatsAppMessage::IN,
            'type' => 'text',
            'body' => 'رسالة قديمة',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
        ]);

        $this->actingAs($this->user('admin'))
            ->delete(route('settings.whatsapp.disconnect'))
            ->assertRedirect();

        $this->assertFalse(WhatsAppSettings::isConnected());
        $this->assertSame(1, WhatsAppMessage::count(), 'مُحيت المراسلات مع فصل الرقم');
        $this->assertSame(1, WhatsAppConversation::count());
    }
}
