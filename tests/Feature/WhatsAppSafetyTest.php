<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\SendingGuard;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ما يخفض احتمالَ حظر رقم المكتب.
 *
 * ═══ ما يُحظَر لأجله رقمٌ فعلاً ═══
 *
 * البلاغُ هو الوقود: دفعةٌ في دقيقة، وإرسالٌ إلى من لا علاقة له
 * بالمكتب، ورسائلُ في الثالثة فجراً. فتُختبر الحدودُ الأربعة:
 * الموكّلون وحدهم، والمهلة، والسقوف، والصمت الليلي.
 *
 * ولا شيءَ هنا يَعِد بعدم الحظر — الضمانُ الوحيد الواجهةُ الرسمية.
 */
class WhatsAppSafetyTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppConversation $conversation;
    private WhatsAppContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-testing-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');

        $client = Client::create([
            'name' => 'سالم', 'phone' => '91234567', 'type' => 'individual',
        ]);

        $this->contact = WhatsAppContact::create(['wa_id' => '96891234567', 'client_id' => $client->id]);
        $this->conversation = WhatsAppConversation::create([
            'contact_id' => $this->contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now(),
        ]);

        // منتصفُ النهار: خارج الصمت الليلي في كلّ الاختبارات إلا ما
        // يقصده
        $this->travelTo(now()->setTime(12, 0));
    }

    private function queued(string $body = 'مرحبا'): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);
    }

    // ── الموكّلون وحدهم ─────────────────────────────────────────

    /** رقمٌ ليس في السجلّ ولم يراسل المكتب لا يُراسَل. */
    public function test_a_stranger_who_never_wrote_is_never_messaged(): void
    {
        Http::fake();

        $stranger = WhatsAppContact::create(['wa_id' => '96899999999']);
        $thread = WhatsAppConversation::create([
            'contact_id' => $stranger->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
        ]);

        $message = WhatsAppMessage::create([
            'conversation_id' => $thread->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'عرض',
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);

        (new SendWhatsAppMessage($message->id))->handle();

        Http::assertNothingSent();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $message->fresh()->status);
    }

    /** ومن راسل المكتب بنفسه يُردّ عليه — ليس اقتحاماً. */
    public function test_someone_who_wrote_first_can_be_answered(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.OK']]], 200)]);

        $stranger = WhatsAppContact::create(['wa_id' => '96899999999']);
        $thread = WhatsAppConversation::create([
            'contact_id' => $stranger->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 1,
            'last_inbound_at' => now(),
        ]);

        $message = WhatsAppMessage::create([
            'conversation_id' => $thread->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'أهلاً',
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);

        (new SendWhatsAppMessage($message->id))->handle();

        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->fresh()->status);
    }

    // ── المهلة والسقوف ──────────────────────────────────────────

    /** رسالتان متتاليتان: الثانية تنتظر. */
    public function test_two_messages_back_to_back_are_paced_apart(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.A']]], 200)]);

        $first = $this->queued('الأولى');
        (new SendWhatsAppMessage($first->id))->handle();
        $this->assertSame(WhatsAppMessage::STATUS_SENT, $first->fresh()->status);

        $second = $this->queued('الثانية');
        (new SendWhatsAppMessage($second->id))->handle();

        // لم تُرسَل — حُجزت بموعدٍ قريب يُفرج عنها فيه
        $fresh = $second->fresh();
        $this->assertSame(WhatsAppMessage::STATUS_QUEUED, $fresh->status);
        $this->assertNotNull($fresh->hold_until, 'لم يُكتب موعدُ الإفراج');
    }

    /** والسقفُ اليومي يوقف ما بعده. */
    public function test_the_daily_cap_holds_the_rest(): void
    {
        Queue::fake();
        Setting::set(SendingGuard::KEY_PER_DAY, '2', 'whatsapp');
        Setting::set(SendingGuard::KEY_MIN_GAP, '3', 'whatsapp');

        // رسالتان أُرسلتا اليوم
        foreach (['أ', 'ب'] as $body) {
            WhatsAppMessage::create([
                'conversation_id' => $this->conversation->id,
                'direction' => WhatsAppMessage::OUT, 'type' => 'text', 'body' => $body,
                'status' => WhatsAppMessage::STATUS_SENT, 'sent_at' => now()->subHours(2),
            ]);
        }

        $this->assertSame(0, SendingGuard::remainingToday());

        $third = $this->queued('ج');
        (new SendWhatsAppMessage($third->id))->handle();

        $this->assertSame(WhatsAppMessage::STATUS_QUEUED, $third->fresh()->status);
    }

    // ── الصمت الليلي ────────────────────────────────────────────

    /**
     * الثالثةُ فجراً: تنتظر الصباح — ولا تُلغى ولا تُعلَن «فشلاً».
     *
     * وقع هذا فعلاً: نسخةٌ سابقة كانت تعيد دفعَ المهمّة بمهلة، فلم
     * تُحترم المهلةُ فدارت وأُعلنت الرسالةُ «فشل الإرسال» — وهي لم
     * تُجرَّب قطّ. فالانتظارُ الآن يُكتب في الرسالة نفسها.
     */
    public function test_an_automatic_message_at_three_in_the_morning_waits_and_never_fails(): void
    {
        Queue::fake();
        $this->travelTo(now()->setTime(3, 0));

        $message = $this->queued();
        (new SendWhatsAppMessage($message->id))->handle();

        $fresh = $message->fresh();

        $this->assertSame(WhatsAppMessage::STATUS_QUEUED, $fresh->status, 'أُلغيت رسالةٌ بدل أن تنتظر');
        $this->assertNotNull($fresh->hold_until, 'لم يُكتب موعدُ الإفراج');
        $this->assertTrue($fresh->hold_until->isFuture());

        // ولا تدور: لا مهمّةَ جديدة تُدفع مع كلّ محاولة
        Queue::assertNotPushed(SendWhatsAppMessage::class);
    }

    /**
     * وردُّ المحامي بيده لا يمنعه الصمتُ الليلي.
     *
     * الصمتُ وُضع لئلّا يوقظ النظامُ موكّلاً برسالةٍ آليّة. أمّا محامٍ
     * يجلس الآن ويكتب رداً في محادثةٍ مفتوحة فذاك سلوكُ إنسانٍ عادي —
     * وحجزُ رسالته خمسَ ساعاتٍ وهو ينتظر وصولَها عطلٌ لا حماية.
     */
    public function test_a_human_reply_at_three_in_the_morning_goes_out(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.NIGHT']]], 200)]);
        $this->travelTo(now()->setTime(3, 0));

        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $message = WhatsAppMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'ردٌّ بيد المحامي',
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sent_by' => $lawyer->id,
        ]);

        (new SendWhatsAppMessage($message->id))->handle();

        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->fresh()->status);
    }

    /** والمحجوزةُ تُفرَج حين يحين وقتُها — من أمر الاستدراك. */
    public function test_the_sweep_releases_a_message_whose_time_has_come(): void
    {
        Queue::fake();

        $message = $this->queued('محجوزة');
        $message->forceFill(['hold_until' => now()->subMinute()])->save();

        $this->artisan('whatsapp:sweep')->assertSuccessful();

        Queue::assertPushed(SendWhatsAppMessage::class);
    }

    /** وما أسقطته دورةُ التأجيل القديمة يُحيا ولا يُترك في «فشل». */
    public function test_the_sweep_revives_messages_the_old_defer_loop_dropped(): void
    {
        Queue::fake();

        $message = $this->queued('ضاعت');
        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_FAILED,
            'error_title' => 'تعذّر الإرسال ضمن حدود الأمان — راجع إعدادات الإيقاع.',
        ])->save();

        $this->artisan('whatsapp:sweep')->assertSuccessful();

        $this->assertSame(WhatsAppMessage::STATUS_QUEUED, $message->fresh()->status);
        Queue::assertPushed(SendWhatsAppMessage::class);
    }

    /** ولا تُحيا رسالةٌ وصلت فعلاً. */
    public function test_the_sweep_never_revives_something_already_delivered(): void
    {
        Queue::fake();

        $message = $this->queued('وصلت');
        $message->forceFill([
            'status' => WhatsAppMessage::STATUS_FAILED,
            'wamid' => 'wamid.DELIVERED',
            'error_title' => 'تعذّر الإرسال ضمن حدود الأمان — راجع إعدادات الإيقاع.',
        ])->save();

        $this->artisan('whatsapp:sweep')->assertSuccessful();

        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $message->fresh()->status);
    }

    public function test_midday_is_not_quiet(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $this->assertNull(SendingGuard::delayFor($this->queued()));
    }

    /** والصمتُ يُطفأ بمساواة الساعتين. */
    public function test_quiet_hours_can_be_switched_off(): void
    {
        $this->travelTo(now()->setTime(3, 0));
        Setting::set(SendingGuard::KEY_QUIET_FROM, '0', 'whatsapp');
        Setting::set(SendingGuard::KEY_QUIET_TO, '0', 'whatsapp');

        $this->assertNull(SendingGuard::delayFor($this->queued()));
    }

    // ── التدرّج بعد الاقتران ────────────────────────────────────

    /** رقمٌ اقترن اليوم لا يرسل بسقفه الكامل. */
    public function test_a_freshly_paired_number_starts_low(): void
    {
        Setting::set(SendingGuard::KEY_PER_DAY, '70', 'whatsapp');

        $this->assertSame(70, SendingGuard::perDay(), 'بلا اقترانٍ مسجَّل يعمل بالسقف الكامل');

        Setting::set(SendingGuard::KEY_PAIRED_AT, now()->toIso8601String(), 'whatsapp');

        $this->assertLessThan(70, SendingGuard::perDay());
        $this->assertGreaterThan(0, SendingGuard::perDay());
    }

    /** وبعد أسبوعٍ يبلغ سقفَه. */
    public function test_after_a_week_the_full_cap_applies(): void
    {
        Setting::set(SendingGuard::KEY_PER_DAY, '70', 'whatsapp');
        Setting::set(SendingGuard::KEY_PAIRED_AT, now()->subDays(10)->toIso8601String(), 'whatsapp');

        $this->assertSame(70, SendingGuard::perDay());
    }

    // ── إطفاء الحاكم ────────────────────────────────────────────

    public function test_the_guard_can_be_switched_off_entirely(): void
    {
        $this->travelTo(now()->setTime(3, 0));
        Setting::set(SendingGuard::KEY_ENABLED, '0', 'whatsapp');

        $this->assertNull(SendingGuard::delayFor($this->queued()));
    }

    // ── صندوق الوارد ────────────────────────────────────────────

    /** مخفيٌّ افتراضاً — والمسارُ نفسُه يُرفض لا الرابطُ وحده. */
    public function test_the_inbox_is_hidden_and_its_routes_are_refused_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->assertFalse(WhatsAppSettings::inboxVisible());

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('whatsapp.index'));

        $this->actingAs($admin)->get(route('whatsapp.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->post(route('whatsapp.send', $this->conversation), ['body' => 'يدوي'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_showing_the_inbox_opens_it_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');

        $this->actingAs($admin)->get(route('whatsapp.index'))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))->assertSee(route('whatsapp.index'));
    }

    /** والمطوّرُ يمرّ ولو كان مخفيّاً — هو من يشخّص العطل. */
    public function test_a_developer_still_reaches_the_inbox(): void
    {
        $dev = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $this->actingAs($dev)->get(route('whatsapp.index'))->assertOk();
    }

    // ── ما لا يملكه المكتب ──────────────────────────────────────

    /**
     * مديرُ المكتب لا يُطفئ حدودَ الأمان ولو بنى الطلبَ بيده.
     *
     * تعطيلُ الحقل في الصفحة ليس حماية: من يرسل الطلبَ مباشرةً
     * يتجاوزه. فالرفضُ في الخادم.
     */
    public function test_an_admin_cannot_switch_off_the_guard_even_by_posting_directly(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('settings.whatsapp.update'), [
            'wa_guard_enabled' => null,
            'wa_guard_clients_only' => null,
            'wa_inbox_visible' => '1',
        ])->assertRedirect();

        $this->assertTrue(SendingGuard::enabled(), 'أطفأ مديرٌ حدودَ الأمان');
        $this->assertTrue(SendingGuard::clientsOnly(), 'ألغى مديرٌ قصرَ المراسلة على الموكّلين');
        $this->assertFalse(WhatsAppSettings::inboxVisible(), 'أظهر مديرٌ صندوقَ الوارد');
    }

    /** والأرقامُ مقفلةٌ كالمفاتيح: سقفٌ يُرفع يُبطل الحمايةَ كإطفائها. */
    public function test_an_admin_cannot_raise_the_caps_or_shorten_the_gap(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('settings.whatsapp.update'), [
            'wa_guard_per_hour' => 200,
            'wa_guard_per_day' => 1000,
            'wa_guard_min_gap_s' => 3,
            'wa_guard_quiet_from' => 0,
            'wa_guard_quiet_to' => 0,
        ])->assertRedirect();

        $this->assertSame(SendingGuard::DEFAULT_PER_HOUR, SendingGuard::perHour());
        $this->assertSame(SendingGuard::DEFAULT_PER_DAY, SendingGuard::perDay());
        $this->assertSame(SendingGuard::DEFAULT_MIN_GAP, SendingGuard::minGap());

        // والصمتُ ما زال قائماً — لم يُلغَ بمساواة الساعتين
        $this->travelTo(now()->setTime(3, 0));
        $this->assertNotNull(SendingGuard::delayFor($this->queued()));
    }

    /** والحدُّ اليومي مئةٌ — والمثبِّتُ يمسك تغييرَه بلا قصد. */
    public function test_the_agreed_limits_are_what_the_office_runs_on(): void
    {
        $this->assertSame(100, SendingGuard::DEFAULT_PER_DAY, 'تغيّر الحدُّ اليومي المتّفق عليه');
        $this->assertSame(15, SendingGuard::DEFAULT_PER_HOUR);
        $this->assertSame(15, SendingGuard::DEFAULT_MIN_GAP);
        $this->assertSame(21, SendingGuard::DEFAULT_QUIET_FROM);
        $this->assertSame(8, SendingGuard::DEFAULT_QUIET_TO);

        // ومئةٌ في اليوم تُبلَغ فعلاً: نافذةُ النهار (٨ ← ٢١) بسقف
        // خمسَ عشرةَ في الساعة تسع مئةً وخمساً وتسعين
        $daylightHours = SendingGuard::DEFAULT_QUIET_FROM - SendingGuard::DEFAULT_QUIET_TO;

        $this->assertGreaterThanOrEqual(
            SendingGuard::DEFAULT_PER_DAY,
            $daylightHours * SendingGuard::DEFAULT_PER_HOUR,
            'السقفُ اليومي لا يُبلغ ضمن ساعات النهار — حدٌّ لا معنى له',
        );
    }

    /** والمطوّرُ يملكها — هو من يقرّر. */
    public function test_a_developer_can_change_them(): void
    {
        $dev = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $this->actingAs($dev)->post(route('settings.whatsapp.update'), [
            'wa_inbox_visible' => '1',
            'wa_guard_per_day' => 90,
        ])->assertRedirect();

        $this->assertTrue(WhatsAppSettings::inboxVisible());
        $this->assertSame(90, SendingGuard::perDay());
    }

    /**
     * والحدودُ تعود إلى وضعها الصحيح على مكتبٍ أطفأها قبل القفل.
     *
     * وقع هذا: حفظةُ إعداداتٍ واحدة كتبت `0` قبل أن تُقفل، ثمّ قُفلت
     * فصار الرقمُ يعمل بلا حدود ولا أحدَ في المكتب يستطيع إعادتها.
     */
    public function test_the_repair_migration_restores_the_safe_values(): void
    {
        Setting::set(SendingGuard::KEY_ENABLED, '0', 'whatsapp');
        Setting::set(SendingGuard::KEY_CLIENTS_ONLY, '0', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');

        $this->assertFalse(SendingGuard::enabled());

        require database_path('migrations/2026_09_01_100003_restore_whatsapp_safety_defaults.php');
        (include database_path('migrations/2026_09_01_100003_restore_whatsapp_safety_defaults.php'))->up();

        $this->assertTrue(SendingGuard::enabled(), 'بقيت الحدودُ مطفأةً بعد الإصلاح');
        $this->assertTrue(SendingGuard::clientsOnly());
        $this->assertFalse(WhatsAppSettings::inboxVisible());
    }

    // ── الرقم العُماني ──────────────────────────────────────────

    /** خمسُ صورٍ لرقمٍ واحد تخرج كلُّها بمفتاح ٩٦٨. */
    public function test_every_local_form_of_an_omani_number_resolves_to_one(): void
    {
        foreach (['91234567', '+968 9123 4567', '00968 91234567', '968-9123-4567', '091234567'] as $written) {
            $this->assertSame(
                '96891234567',
                WhatsAppContact::normalizeWaId($written),
                'لم يُعرف «' . $written . '» رقماً عُمانياً',
            );
        }
    }

    /** ورقمٌ بمفتاح دولةٍ أخرى يمرّ كما هو — لا يُقحَم عليه ٩٦٨. */
    public function test_a_foreign_number_keeps_its_own_country_code(): void
    {
        $this->assertSame('971501234567', WhatsAppContact::normalizeWaId('+971 50 123 4567'));
        $this->assertSame('966551234567', WhatsAppContact::normalizeWaId('00966 55 123 4567'));
    }

    /** والإشعاراتُ الآلية تعمل والصندوقُ مخفيّ. */
    public function test_automatic_notifications_work_while_the_inbox_is_hidden(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.OK']]], 200)]);

        $this->assertFalse(WhatsAppSettings::inboxVisible());

        $message = $this->queued('إشعارٌ آلي');
        (new SendWhatsAppMessage($message->id))->handle();

        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->fresh()->status);
    }

    /**
     * ═══ اللغم: خانةٌ تعرض المتدرّجَ فتحفظه مكان المضبوط ═══
     *
     * أثناء تدرّج ما بعد الاقتران كانت خانتا «في اليوم/في الساعة»
     * تعرضان القيمةَ المتدرّجة (٢١ لا ١٠٠). فإن حفظ المطوّرُ الصفحةَ
     * لأيّ سببٍ كُتب ٢١ مكان المئة نهائياً — وتقلّص السقفُ مع كلّ
     * حفظةٍ تالية. الخانةُ تعرض المضبوطَ، والتدرّجُ يُقال جملةً.
     */
    public function test_the_limit_fields_show_the_configured_not_the_warmed_values(): void
    {
        \App\Models\Setting::set(\App\Services\WhatsApp\SendingGuard::KEY_PER_DAY, '100', 'whatsapp');
        \App\Models\Setting::set(\App\Services\WhatsApp\SendingGuard::KEY_PER_HOUR, '15', 'whatsapp');

        // اقترن أمس: النافذُ أقلُّ من المضبوط
        \App\Models\Setting::set(\App\Services\WhatsApp\SendingGuard::KEY_PAIRED_AT, now()->subDay()->toIso8601String(), 'whatsapp');

        $this->assertTrue(\App\Services\WhatsApp\SendingGuard::warmingUp());
        $this->assertLessThan(100, \App\Services\WhatsApp\SendingGuard::perDay());

        $developer = \App\Models\User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $html = $this->actingAs($developer)->get(route('settings.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/name="wa_guard_per_day"[^>]*value="100"/',
            $html,
            'خانةُ اليوم لا تعرض المضبوطَ — حفظُها يكتب المتدرّجَ مكانه',
        );
        $this->assertStringContainsString('تدرّج ما بعد الاقتران', $html, 'التدرّجُ لا يُقال جملةً');
    }

    /**
     * ردُّ المحامي الثاني لا يُحجَز — والفجوةُ تبقى على الآليّ.
     *
     * ═══ العطل الذي وُضع له ═══
     *
     * الفجوةُ حارسٌ ضدّ الرشّ الآليّ. وكانت تُطبَّق على يد الإنسان
     * أيضاً: يكتب المحامي رسالتين متتاليتين فتُحجَز الثانيةُ ستَّ
     * عشرةَ ثانيةً إلى أربعٍ وعشرين، والموكّلُ ينتظر والمحامي يظنّها
     * أخفقت. وأسوأُ منه أنّ آخِرَ صادرٍ يُقاس على المكتب كلِّه: إشعارٌ
     * آليٌّ لموكّلٍ كان يؤخّر ردَّ المحامي على موكّلٍ آخر.
     */
    public function test_a_humans_second_reply_is_not_held_by_the_gap(): void
    {
        $conversation = $this->conversation;

        // إشعارٌ آليٌّ خرج للتوّ — يقيس عليه الحارسُ فجوتَه
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'إشعار آليّ',
            'status' => WhatsAppMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $byHuman = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'وهذه تكملةُ كلامي',
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sent_by' => \App\Models\User::factory()->create()->id,
        ]);

        $this->assertNull(SendingGuard::delayFor($byHuman), 'ردُّ إنسانٍ حُجز بفجوةٍ وُضعت للآليّ');

        // وعلى الآليّ تبقى: هو الذي يُقرأ رشّاً فيُحظر الرقم
        $auto = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'إشعار آليّ ثانٍ',
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);

        $this->assertNotNull(SendingGuard::delayFor($auto), 'الفجوةُ سقطت عن الإرسال الآليّ');
    }

    /** والسقوفُ تبقى على يد الإنسان — لا تتحوّل قناةَ بثّ. */
    public function test_a_human_still_obeys_the_hourly_ceiling(): void
    {
        $conversation = $this->conversation;
        $user = \App\Models\User::factory()->create();

        for ($i = 0; $i < SendingGuard::perHour(); $i++) {
            WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => WhatsAppMessage::OUT,
                'type' => 'text',
                'body' => 'رسالة ' . $i,
                'status' => WhatsAppMessage::STATUS_SENT,
                'sent_at' => now()->subMinutes(2),
            ]);
        }

        $extra = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => 'واحدةٌ فوق السقف',
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sent_by' => $user->id,
        ]);

        $this->assertNotNull(SendingGuard::delayFor($extra), 'السقفُ الساعيّ سقط عن يد الإنسان');
    }
}
