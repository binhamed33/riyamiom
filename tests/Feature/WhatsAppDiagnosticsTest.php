<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\SendingGuard;
use App\Support\ClientEvents;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * أداتا التشخيص: «ما حدودي فعلاً؟» و«أين انقطعت السلسلة؟»
 *
 * ═══ العطلُ الذي حُرست منه ═══
 *
 * قال المكتبُ «كلُّ شيءٍ مفعَّل ولا تصل رسالة»، ولم يكن في النظام ما
 * يجيب. فصار الجوابُ تخميناً: أيكون الربط؟ أم الطابور؟ أم الرقم؟
 * وكلُّ تخمينٍ يُجرَّب على رقمٍ حيّ.
 *
 * وهذان الأمران يقطعان التخمين:
 *
 *  ١) `whatsapp:limits` يقرأ الحدَّ **النافذ** لا افتراضَ الكود —
 *     فمكتبٌ خُزّن فيه خمسون يبقى على خمسين مهما رُفع الكود، والشاشةُ
 *     لا تكشف ذلك.
 *
 *  ٢) `whatsapp:trace` يمشي الحلقاتِ التسع ويسمّي أوّلَ مقطوعة —
 *     ومنها «محجوزةٌ حتى الثامنة»، وهي أكثرُ ما يُظنّ عطلاً وليس به.
 */
class WhatsAppDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-testing-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');

        $this->client = Client::create([
            'name' => 'سعيد بن حمد الهنائي',
            'phone' => '91234567',
            'national_id' => '87654321',
            'type' => 'individual',
        ]);
    }

    private function makeCase(): LegalCase
    {
        return LegalCase::create([
            'case_number' => '2026/900', 'title' => 'قضية', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium',
            'client_id' => $this->client->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function limitsJson(): array
    {
        Artisan::call('whatsapp:limits', ['--json' => true]);

        return json_decode(trim(Artisan::output()), true) ?: [];
    }

    // ══════════ الحدود ══════════

    /**
     * مكتبٌ لم يُحفظ فيه شيء: مئةٌ في اليوم، ومطابقٌ للسياسة.
     *
     * وغيابُ الصفّ ليس مخالفةً — وإلا خرج كلُّ مكتبٍ جديدٍ أحمرَ في
     * أوّل فحص، فيُطارَد ما ليس عطلاً.
     */
    public function test_a_fresh_office_reports_a_hundred_a_day_and_matches_policy(): void
    {
        // الهجرةُ تثبّت القيمَ على كلّ مكتب، فالصفُّ موجود. وحذفُه
        // هنا يحاكي مكتباً لم تبلغه الهجرةُ بعد — وهو أيضاً مطابق،
        // لأنّ افتراضَ الكود هو السياسةُ نفسُها.
        $this->assertSame(100, $this->limitsJson()['per_day']);

        Setting::where('key', SendingGuard::KEY_PER_DAY)->delete();

        $report = $this->limitsJson();

        $this->assertSame(100, $report['per_day']);
        $this->assertSame('code', $report['per_day_source']);
        $this->assertTrue($report['policy_ok']);
        $this->assertSame(0, Artisan::call('whatsapp:limits'));
    }

    /** خمسون مخزَّنةٌ تغلب مئةَ الكود — وهذا ما لا تكشفه الشاشة. */
    public function test_a_stored_cap_overrides_the_code_and_is_reported_as_drift(): void
    {
        Setting::set(SendingGuard::KEY_PER_DAY, '50', SendingGuard::GROUP);

        $report = $this->limitsJson();

        $this->assertSame(50, $report['per_day']);
        $this->assertSame('stored', $report['per_day_source']);
        $this->assertFalse($report['policy_ok']);
        $this->assertArrayHasKey(SendingGuard::KEY_PER_DAY, $report['drift']);

        // ورمزُ الخروج غيرُ صفرٍ: سكربتُ الفحص الجماعي يعدّ المخالف
        $this->assertSame(1, Artisan::call('whatsapp:limits'));
    }

    /** ولا يُكتب شيءٌ إلا بطلبٍ صريح: الفحصُ قراءةٌ محضة. */
    public function test_reading_the_limits_never_writes(): void
    {
        Setting::set(SendingGuard::KEY_PER_DAY, '50', SendingGuard::GROUP);

        $this->limitsJson();

        $this->assertSame('50', (string) Setting::get(SendingGuard::KEY_PER_DAY));
    }

    /** و‎--fix يعيد المخالفَ إلى المعتمَد وحدَه. */
    public function test_fix_restores_the_approved_values(): void
    {
        Setting::set(SendingGuard::KEY_PER_DAY, '500', SendingGuard::GROUP);
        Setting::set(SendingGuard::KEY_ENABLED, '0', SendingGuard::GROUP);
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', SendingGuard::GROUP);

        Artisan::call('whatsapp:limits', ['--fix' => true]);

        $this->assertSame('100', (string) Setting::get(SendingGuard::KEY_PER_DAY));
        $this->assertSame('1', (string) Setting::get(SendingGuard::KEY_ENABLED));
        $this->assertSame('0', (string) Setting::get(WhatsAppSettings::KEY_INBOX_VISIBLE));
        $this->assertTrue($this->limitsJson()['policy_ok']);
    }

    /** والتقريرُ يقول إن كانت إشعاراتُ الموكّل مطفأةً أصلاً. */
    public function test_the_report_names_the_master_switch(): void
    {
        $this->assertFalse($this->limitsJson()['notifications_master']);

        ClientEvents::setMasterEnabled(true);

        $this->assertTrue($this->limitsJson()['notifications_master']);
    }

    // ══════════ التتبّع ══════════

    /** المفتاحُ الرئيسي مطفأ: تُسمّى الحلقةُ ولا يُقال «عطلٌ في الربط». */
    public function test_the_trace_names_the_master_switch_when_it_is_off(): void
    {
        Queue::fake();
        $case = $this->makeCase();

        $code = Artisan::call('whatsapp:trace', ['--case' => $case->id]);
        $output = Artisan::output();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('المقطوعة', $output);
        $this->assertStringContainsString('مطفأة', $output);
    }

    /**
     * الرسالةُ محجوزةٌ حتى الثامنة: يُقال موعدُها ويُقال إنّه صحيح.
     *
     * وهذا هو السؤالُ الذي أُغلق: رسالةُ الثالثة فجراً ليست ضائعة.
     */
    public function test_the_trace_shows_when_a_held_message_will_be_released(): void
    {
        Queue::fake();
        ClientEvents::setMasterEnabled(true);

        $case = $this->makeCase();
        $notification = ClientNotification::firstOrFail();
        $notification->forceFill(['channel_state' => ClientNotification::QUEUED, 'notified_at' => now()])->save();

        $contact = WhatsAppContact::create(['wa_id' => '96891234567', 'client_id' => $this->client->id]);
        $conversation = WhatsAppConversation::create([
            'contact_id' => $contact->id, 'status' => WhatsAppConversation::STATUS_OPEN, 'unread_count' => 0,
        ]);

        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'تنبيه',
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'hold_until' => now()->addHours(4),
        ]);

        Artisan::call('whatsapp:trace', ['--case' => $case->id]);
        $output = Artisan::output();

        $this->assertStringContainsString('محجوزة', $output);
        $this->assertStringContainsString('whatsapp:sweep', $output);
    }

    /** ورقمُ الموكّل يُقرأ بمفتاح عُمان لا كما كُتب في الحقل. */
    public function test_the_trace_reads_the_client_number_with_the_oman_code(): void
    {
        Queue::fake();
        ClientEvents::setMasterEnabled(true);
        $case = $this->makeCase();

        Artisan::call('whatsapp:trace', ['--case' => $case->id]);

        $this->assertStringContainsString('96891234567', Artisan::output());
    }

    /** ولا يسقط الأمرُ حين لا يُسمّى شيء: يأخذ آخرَ إشعارٍ قُيّد. */
    public function test_the_trace_falls_back_to_the_latest_notification(): void
    {
        Queue::fake();
        ClientEvents::setMasterEnabled(true);
        $this->makeCase();

        $this->assertContains(Artisan::call('whatsapp:trace'), [0, 1]);
        $this->assertStringContainsString('سعيد', Artisan::output());
    }
}
