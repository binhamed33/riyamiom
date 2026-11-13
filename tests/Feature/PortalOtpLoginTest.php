<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * دخولُ البوابة برمز واتساب — سريعٌ، محدودٌ، ولا يكشف شيئاً.
 *
 * ═══ ما تحرسه ═══
 *
 * ١) السرعة: الرمزُ يُرسَل في الطلب نفسِه مباشرةً — لا طابورَ يؤخّره
 *    دقيقةً ولا حارسَ إيقاعٍ يحجزه إلى الصباح: صاحبُه طلبه الآن.
 * ٢) الدقيقتان: رمزٌ ثم لا رمزَ قبل مرور مئةٍ وعشرين ثانية.
 * ٣) الكتمان: المسجَّلُ وغيرُ المسجَّل يسمعان الجملةَ نفسَها.
 * ٤) الحرق: رمزٌ يُستعمل مرّةً، وخمسُ محاولاتٍ ثم قفل.
 * ٥) الجلسة: التحقّق يقرأ هاتفَ الجلسة لا هاتفاً يرسله الزائر.
 */
class PortalOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');

        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        Setting::set(WhatsAppSettings::KEY_EVO_STATE, 'open', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->toIso8601String(), 'whatsapp');

        $this->client = Client::create([
            'name' => 'سالم بن علي الريامي',
            'phone' => '91234567',
            'national_id' => '13572468',
            'type' => 'individual',
        ]);
    }


    /** محاكاةُ الجسر: حالةٌ حيّة وإرسالٌ ناجح — في fake واحد. */
    private function fakeBridge(string $liveState = 'open'): void
    {
        Http::fake(function ($request) use ($liveState) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => $liveState]], 200);
            }

            return Http::response(['key' => ['id' => 'OTP-' . uniqid()]], 200);
        });
    }

    /**
     * الرمزُ يُقرأ من الرسالة الخارجة لا من صفّ السجلّ.
     *
     * السجلُّ يحفظ بديلاً بلا رمز عمداً: صندوقُ الوارد لا يفحص الصلاحيةَ
     * لكلّ محادثةٍ على حدة، فمن يملك whatsapp.view كان يقرأ رمزَ أيّ
     * موكّلٍ ويدخل بوابتَه. (انظر AuditBatchThreeTest)
     */
    private function code(): string
    {
        foreach (Http::recorded()->reverse() as [$request, $response]) {
            if (!str_contains($request->url(), '/message/sendText/')) {
                continue;
            }

            if (preg_match('/\b(\d{6})\b/u', json_encode($request->data(), JSON_UNESCAPED_UNICODE), $m)) {
                return $m[1];
            }
        }

        return '';
    }

    /** الرحلةُ كاملة: هاتف ⇐ رمزٌ يصل فوراً ⇐ دخول. */
    public function test_a_client_logs_in_with_a_whatsapp_code(): void
    {
        $this->fakeBridge();

        $this->post(route('client.access.otp'), ['phone' => '91234567'])->assertRedirect();

        // أُرسل في الطلب نفسِه — لا «في الانتظار» ولا طابور
        $message = WhatsAppMessage::latest('id')->firstOrFail();
        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->status, 'الرمزُ لم يخرج فوراً');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/message/sendText/'));

        $this->post(route('client.access.otp.verify'), ['code' => $this->code()])
            ->assertRedirect(route('client.portal.home'));

        $this->get(route('client.portal.home'))->assertOk();
    }

    /** الرمزُ يتخطّى الصمتَ الليلي: صاحبُه طلبه الآن وينتظره. */
    public function test_the_code_ignores_quiet_hours(): void
    {
        $this->travelTo(now()->setTime(23, 30));
        $this->fakeBridge();

        $this->post(route('client.access.otp'), ['phone' => '91234567']);

        $message = WhatsAppMessage::latest('id')->firstOrFail();
        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->status, 'حُجز الرمزُ إلى الصباح');
        $this->assertNull($message->hold_until);
    }

    /** «من otp إلى otp دقيقتان» — حرفياً. */
    public function test_a_second_code_waits_two_minutes(): void
    {
        $this->fakeBridge();

        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->assertSame(1, WhatsAppMessage::count());

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_error');
        $this->assertSame(1, WhatsAppMessage::count(), 'أُرسل رمزٌ ثانٍ قبل الدقيقتين');

        $this->travel(121)->seconds();

        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->assertSame(2, WhatsAppMessage::count());
    }

    /**
     * رقمٌ غيرُ مسجَّل: يُقال له ذلك، ويبقى عند حقل الرقم، ولا يُرسَل شيء.
     *
     * (بقرار المكتب: كان الردُّ واحداً للمسجَّل وغيره، فوقف موكّلٌ كتب رقماً غيرَ
     * الذي في ملفّه أمام «أدخل الرمز» ينتظر ما لن يصل.)
     */
    public function test_an_unknown_phone_is_told_it_is_not_registered(): void
    {
        $this->fakeBridge();

        $this->post(route('client.access.otp'), ['phone' => '99887766'])
            ->assertRedirect(route('client.access'))
            ->assertSessionHas('portal_error', fn ($m) => str_contains($m, 'غيرُ مسجَّلٍ لدى المكتب'))
            ->assertSessionMissing('portal_otp_phone');

        $this->assertSame(0, WhatsAppMessage::count(), 'أُرسل شيءٌ لرقمٍ غير مسجَّل');

        // والصفحةُ تعرض السببَ فوق حقل الرقم — لا خطوةَ «أدخل الرمز»
        $html = $this->get(route('client.access'))->getContent();
        $this->assertStringContainsString('أرسل الرمز', $html);
        $this->assertStringNotContainsString('رمز التحقّق', $html);
    }

    /** والمسجَّلُ يُقال له إنّ الرمزَ خرج إلى واتساب رقمه. */
    public function test_a_registered_phone_is_told_the_code_was_sent(): void
    {
        $this->fakeBridge();

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_notice', 'أرسلنا رمزَ الدخول إلى واتساب رقمك.')
            ->assertSessionHas('portal_otp_phone', '91234567');

        $this->assertSame(1, WhatsAppMessage::count());
    }

    /**
     * واتساب رفض الإرسال: يُقال السببُ، ويبقى الموكّلُ عند حقل الرقم، والمحاولةُ
     * التالية فوريّة — رمزٌ لم يخرج لا يحجز الدقيقتين.
     */
    public function test_a_refused_send_is_shown_with_its_reason_and_can_be_retried_at_once(): void
    {
        $refuse = true;

        Http::fake(function ($request) use (&$refuse) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            return $refuse
                ? Http::response(['status' => 400, 'response' => ['message' => [['exists' => false, 'number' => 'x']]]], 400)
                : Http::response(['key' => ['id' => 'OTP-' . uniqid()]], 200);
        });

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_error', fn ($m) => str_contains($m, 'ليس على واتساب'))
            ->assertSessionMissing('portal_otp_phone');

        // رفضٌ يقينيّ لا يُحسب في سقف اليوم
        $dayKey = 'portal_otp_day:' . hash_hmac('sha256', Client::normalizePhone('91234567'), (string) config('app.key'));
        $this->assertSame(0, (int) \Illuminate\Support\Facades\Cache::get($dayKey, 0));

        $refuse = false;

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_notice')
            ->assertSessionHas('portal_otp_phone', '91234567');

        $this->assertSame(1, (int) \Illuminate\Support\Facades\Cache::get($dayKey, 0));
    }

    /** خمسُ محاولاتٍ خاطئة تقفل الرمز — والصحيحُ بعدها لا يمرّ. */
    public function test_five_wrong_attempts_burn_the_code(): void
    {
        $this->fakeBridge();

        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $code = $this->code();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('client.access.otp.verify'), ['code' => '000000']);
        }

        $this->post(route('client.access.otp.verify'), ['code' => $code])
            ->assertSessionHas('portal_error');

        $this->get(route('client.portal.home'))->assertRedirect();
    }

    /** والرمزُ يُحرق بالاستعمال: مرّةٌ تفتح، والثانية تُرَدّ. */
    public function test_a_code_works_exactly_once(): void
    {
        $this->fakeBridge();

        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $code = $this->code();

        $this->post(route('client.access.otp.verify'), ['code' => $code])
            ->assertRedirect(route('client.portal.home'));

        // جلسةٌ جديدة تحاول بالرمز المحروق
        $this->post(route('client.access.logout'));
        $this->post(route('client.access.otp.verify'), ['code' => $code])
            ->assertSessionHas('portal_error');
    }

    /**
     * رقمٌ يتشاركه ملفّان: يصله الرمز، ثمّ يختار ملفَّه — ويبدّل بينهما لاحقاً.
     * ولا يُفتح بالاختيار ملفٌّ ليس على رقمه ولو عُرف معرّفُه.
     */
    public function test_a_shared_phone_chooses_its_file_after_the_code(): void
    {
        $second = Client::create([
            'name' => 'شركةُ سالم الريامي',
            'phone' => '91234567',
            'type' => 'company',
        ]);
        $stranger = Client::create(['name' => 'موكّلٌ آخر', 'phone' => '95550000', 'type' => 'individual']);

        $this->fakeBridge();

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_otp_phone', '91234567');

        $this->post(route('client.access.otp.verify'), ['code' => $this->code()])
            ->assertRedirect(route('client.access.choose'));

        $this->assertNull(session('client_access_id'), 'فُتح ملفٌّ قبل أن يختار');

        $page = $this->get(route('client.access.choose'))->assertOk()->getContent();
        $this->assertStringContainsString('سالم بن علي الريامي', $page);
        $this->assertStringContainsString('شركةُ سالم الريامي', $page);
        $this->assertStringNotContainsString('موكّلٌ آخر', $page);

        // ملفٌّ ليس على الرقم: لا يُفتح
        $this->post(route('client.access.choose.store'), ['client_id' => $stranger->id])
            ->assertRedirect(route('client.access'));
        $this->assertNull(session('client_access_id'));

        $this->post(route('client.access.choose.store'), ['client_id' => $second->id])
            ->assertRedirect(route('client.portal.home'));
        $this->assertSame($second->id, session('client_access_id'));

        // ويبدّل إلى ملفّه الآخر دون رمزٍ جديد
        $this->assertStringContainsString('تبديل الملفّ', $this->get(route('client.portal.home'))->getContent());

        $this->post(route('client.access.choose.store'), ['client_id' => $this->client->id])
            ->assertRedirect(route('client.portal.home'));
        $this->assertSame($this->client->id, session('client_access_id'));
    }

    /**
     * ملفٌّ نقل المكتبُ عنه الرقمَ بعد التحقّق: يختفي من الاختيار ولا يُفتح —
     * الصلاحيةُ ملكيّةُ الهاتف، والهاتفُ لم يعد عليه.
     */
    public function test_a_file_moved_off_the_number_after_verification_cannot_be_chosen(): void
    {
        $second = Client::create(['name' => 'شركةُ سالم الريامي', 'phone' => '91234567', 'type' => 'company']);
        $third = Client::create(['name' => 'ابنُ سالم', 'phone' => '91234567', 'type' => 'individual']);

        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->post(route('client.access.otp.verify'), ['code' => $this->code()])
            ->assertRedirect(route('client.access.choose'));

        $third->update(['phone' => '95550000']);

        $this->assertStringNotContainsString('ابنُ سالم', $this->get(route('client.access.choose'))->getContent());

        $this->post(route('client.access.choose.store'), ['client_id' => $third->id])
            ->assertRedirect(route('client.access'));
        $this->assertNull(session('client_access_id'));

        $this->post(route('client.access.choose.store'), ['client_id' => $second->id])
            ->assertRedirect(route('client.portal.home'));
    }

    /** ودخولٌ بملفٍّ واحدٍ بعد اختياراتِ رقمٍ آخر في الجلسة نفسِها لا يرث تلك الاختيارات. */
    public function test_a_later_single_file_login_does_not_inherit_old_choices(): void
    {
        Client::create(['name' => 'شركةُ سالم الريامي', 'phone' => '91234567', 'type' => 'company']);
        $other = Client::create(['name' => 'موكّلٌ آخر', 'phone' => '95550000', 'type' => 'individual']);

        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->post(route('client.access.otp.verify'), ['code' => $this->code()]);

        $this->travel(3)->minutes();
        $this->post(route('client.access.otp'), ['phone' => '95550000']);
        $this->post(route('client.access.otp.verify'), ['code' => $this->code()])
            ->assertRedirect(route('client.portal.home'));

        $this->assertSame($other->id, session('client_access_id'));
        $this->assertStringNotContainsString('تبديل الملفّ', $this->get(route('client.portal.home'))->getContent());
        $this->post(route('client.access.choose.store'), ['client_id' => $this->client->id])
            ->assertRedirect(route('client.access'));
        $this->assertSame($other->id, session('client_access_id'));
    }

    /** لم يبقَ على الرقم إلا ملفٌّ واحد قبل أن يختار: يُفتح مباشرةً — لا «اطلب رمزاً جديداً». */
    public function test_the_picker_opens_the_last_remaining_file_directly(): void
    {
        $second = Client::create(['name' => 'شركةُ سالم الريامي', 'phone' => '91234567', 'type' => 'company']);

        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->post(route('client.access.otp.verify'), ['code' => $this->code()])
            ->assertRedirect(route('client.access.choose'));

        $second->update(['phone' => '95550000']);

        $this->get(route('client.access.choose'))->assertRedirect(route('client.portal.home'));
        $this->assertSame($this->client->id, session('client_access_id'));
    }

    /** ومن دخل ثمّ فتح «تبديل الملفّ» يرى زرَّ الخروج — هاتفٌ مشتركٌ لا تبقى عليه جلسةٌ مفتوحة. */
    public function test_the_switch_page_offers_logout(): void
    {
        Client::create(['name' => 'شركةُ سالم الريامي', 'phone' => '91234567', 'type' => 'company']);

        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->post(route('client.access.otp.verify'), ['code' => $this->code()]);
        $this->post(route('client.access.choose.store'), ['client_id' => $this->client->id]);

        $html = $this->get(route('client.access.choose'))->assertOk()->getContent();
        $this->assertStringContainsString('تسجيل الخروج', $html);
        $this->assertStringContainsString(route('client.access.logout'), $html);
    }

    /** وصفحةُ الاختيار لا تُفتح بلا رمزٍ ثبت. */
    public function test_the_file_picker_needs_a_verified_code(): void
    {
        $this->get(route('client.access.choose'))->assertRedirect(route('client.access'));

        $this->post(route('client.access.choose.store'), ['client_id' => $this->client->id])
            ->assertRedirect(route('client.access'));
        $this->assertNull(session('client_access_id'));
    }

    /** ورقمٌ على ملفٍّ واحد يدخل مباشرةً بلا صفحة اختيار. */
    public function test_a_single_file_number_skips_the_picker(): void
    {
        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);

        $this->post(route('client.access.otp.verify'), ['code' => $this->code()])
            ->assertRedirect(route('client.portal.home'));

        $this->assertSame($this->client->id, session('client_access_id'));
        $this->assertStringNotContainsString('تبديل الملفّ', $this->get(route('client.portal.home'))->getContent());
    }

    /** ومكتبٌ غيرُ مربوطٍ يقولها صراحةً بدل صمتٍ محيّر. */
    public function test_an_unconnected_office_says_so(): void
    {
        $this->fakeBridge('close');

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_error');
    }

    /**
     * ═══ العطل الذي ظهر على الشاشة ═══
     *
     * الذاكرةُ تقول «close» قديمةً والجسرُ مفتوحٌ يرسل — فرُدّ
     * الموكّل بـ«غير متاح» وكلُّ شيءٍ سليم. البوابةُ تسأل الجسرَ
     * حيّاً، وتشفي الذاكرةَ بما سمعت.
     */
    public function test_a_stale_closed_cache_does_not_refuse_a_live_bridge(): void
    {
        Setting::set(WhatsAppSettings::KEY_EVO_STATE, 'close', 'whatsapp');
        $this->fakeBridge('open');

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_notice');

        $this->assertSame(1, WhatsAppMessage::count(), 'ذاكرةٌ كاذبة ردّت موكّلاً والجسر مفتوح');
        $this->assertSame('open', WhatsAppSettings::evolutionState(), 'الذاكرةُ لم تُشفَ بجواب الجسر');
    }

    /** والواجهةُ تعرض بابَ الرمز وحدَه — لا دخولَ برقم الهويّة. */
    public function test_the_login_page_offers_only_the_whatsapp_door(): void
    {
        $html = $this->get(route('client.access'))->assertOk()->getContent();

        // بابُ الرمز: حقلُ الهاتف وزرُّ الإرسال
        $this->assertStringContainsString('أرسل الرمز', $html);
        $this->assertStringContainsString('رقم الهاتف', $html);

        // ولا أثرَ لبابِ الهويّة: لا حقلٌ ولا رابطٌ إليه
        $this->assertStringNotContainsString('national_id', $html);
        $this->assertStringNotContainsString('الدخول برقم الهويّة', $html);
    }

    /** وطلبُ الرمز يُبقي الجلسةَ على خطوة إدخاله. */
    public function test_requesting_a_code_moves_to_the_code_step(): void
    {
        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);

        $html = $this->get(route('client.access'))->assertOk()->getContent();
        $this->assertStringContainsString('رمز التحقّق', $html);
    }

    /** «تغيير الرقم» يعيد إلى حقل الهاتف — رقمٌ خاطئٌ لم يعد سجناً. */
    public function test_change_number_returns_to_the_phone_field(): void
    {
        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);

        $this->post(route('client.access.otp.reset'))
            ->assertRedirect(route('client.access', ['otp' => 1]))
            ->assertSessionMissing('portal_otp_phone');

        $html = $this->get(route('client.access', ['otp' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('أرسل الرمز', $html, 'بقي محبوساً أمام حقل الرمز');
    }

    /**
     * رقمٌ طُلب له رمزٌ قبل ساعات: الصفحةُ تعود إلى حقل الرقم لا إلى «أدخل الرمز».
     *
     * الجلسةُ تعيش ساعات والرمزُ خمسَ دقائق؛ فكان من عاد بعدها يقف أمام حقل رمزٍ
     * لم يُرسَل في زيارته شيء.
     */
    public function test_an_old_code_request_does_not_reopen_the_code_step(): void
    {
        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);
        $this->assertStringContainsString('رمز التحقّق', $this->get(route('client.access'))->getContent());

        $this->travel(6)->minutes();

        $html = $this->get(route('client.access'))->getContent();
        $this->assertStringContainsString('أرسل الرمز', $html);
        $this->assertStringNotContainsString('رمز التحقّق', $html);
    }

    /** وتجاوزُ حدّ الطلبات يعيد الزائرَ إلى بوابته برسالة — لا إلى دخول الموظّفين. */
    public function test_a_throttled_visitor_stays_on_the_portal(): void
    {
        $this->withMiddleware();
        \Illuminate\Support\Facades\RateLimiter::clear('portal-otp');

        $this->fakeBridge();
        $last = null;

        for ($i = 0; $i < 7; $i++) {
            $last = $this->post(route('client.access.otp'), ['phone' => '9555000' . $i]);
        }

        $last->assertRedirect(route('client.access'))
            ->assertSessionHas('portal_error', fn ($m) => str_contains($m, 'طلباتٌ كثيرة'));
    }

    /** طلبٌ ثانٍ قبل الدقيقتين لرقمه نفسِه: يبقى عند خطوة الرمز ليُدخل ما وصله. */
    public function test_an_early_resend_keeps_the_code_step(): void
    {
        $this->fakeBridge();
        $this->post(route('client.access.otp'), ['phone' => '91234567']);

        $this->post(route('client.access.otp'), ['phone' => '91234567'])
            ->assertSessionHas('portal_error', fn ($m) => str_contains($m, 'انتظر دقيقتين'))
            ->assertSessionHas('portal_otp_phone', '91234567');

        $this->assertStringContainsString('رمز التحقّق', $this->get(route('client.access'))->getContent());
    }
}

