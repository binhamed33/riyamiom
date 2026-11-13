<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Services\ClientPortal\PortalOtp;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * «شبكنا واتساب والرمزُ لا يصل» — والمكتبُ لا يعرف لماذا.
 *
 * ═══ ما كان ═══
 *
 * صفحةُ الدخول تردّ ردّاً واحداً مهما جرى، فلا يعرف الزائرُ أهو موكّلٌ أم لا —
 * وهذا مقصود. لكنّ المكتبَ نفسَه لم يكن يعرف: الرمزُ يُرسَل لمن يطابق رقمُه
 * موكّلاً واحداً بالضبط، فرقمٌ غيرُ مسجَّل أو مسجَّلٌ لموكّلَين لا يُرسَل له
 * شيء، بلا أثرٍ في أيّ مكان. وحقلُ هاتفٍ فيه رقمان كان يُرسَل رمزُه إلى
 * أرقام الرقمين ملتصقةً — رقمٍ لا وجودَ له.
 */
class PortalOtpDeliveryDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');

        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        Setting::set(WhatsAppSettings::KEY_EVO_STATE, 'open', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_CONNECTED_AT, now()->toIso8601String(), 'whatsapp');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            return Http::response(['key' => ['id' => 'OTP-' . uniqid()]], 200);
        });
    }

    private function client(string $name, string $phone): Client
    {
        return Client::create(['name' => $name, 'type' => 'individual', 'phone' => $phone]);
    }

    /** أرقامُ الوجهات التي ذهب إليها رمز. @return list<string> */
    private function destinations(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/message/sendText/'))
            ->map(fn ($pair) => (string) $pair[0]['number'])->values()->all();
    }

    /** حقلُ هاتفٍ فيه رقمان: الرمزُ يذهب إلى الرقم الذي طلبه الموكّل — لا إلى الرقمين ملتصقَين. */
    public function test_a_client_with_two_numbers_gets_the_code_on_the_one_he_typed(): void
    {
        $this->client('موكّلٌ برقمين', '99887766, 91234567');

        PortalOtp::request('91234567');

        $this->assertSame(['96891234567'], $this->destinations());
    }

    /** ورقمٌ دوليٌّ محفوظٌ بمفتاحه يُراسَل بمفتاحه — لا يُلصق به مفتاحُ عُمان. */
    public function test_an_international_number_keeps_its_country_code(): void
    {
        $this->client('موكّلٌ من الإمارات', '+971 50 623 3112');

        PortalOtp::request('0506233112');

        $this->assertSame(['971506233112'], $this->destinations());
    }

    /**
     * رقمان لشخصين يشتركان في آخر ثمانية أرقام (عُمانيّ وسعوديّ): كلٌّ يصله رمزُه
     * على هاتفه هو، ولا يرى ملفَّ الآخر. البصمةُ آخرُ ثمانية — والمطابقةُ بالرقم كاملاً.
     */
    public function test_two_phones_sharing_the_last_eight_digits_stay_apart(): void
    {
        $saudi = $this->client('موكّلٌ سعوديّ', '+966 591234567');
        $omani = $this->client('موكّلٌ عُمانيّ', '91234567');

        $this->assertSame([$omani->id], PortalOtp::resolve('91234567')['clients']->pluck('id')->all());
        $this->assertSame([$saudi->id], PortalOtp::resolve('+966591234567')['clients']->pluck('id')->all());
        $this->assertSame([$saudi->id], PortalOtp::resolve('0591234567')['clients']->pluck('id')->all(), 'الصيغةُ المحلّيّة السعوديّة');

        PortalOtp::request('91234567');
        $this->travel(3)->minutes();
        PortalOtp::request('+966591234567');

        $this->assertSame(['96891234567', '966591234567'], $this->destinations());
    }

    /** رقمٌ لا يطابق أحداً: لا رمز — ويُكتب السببُ للمكتب بلا رقمٍ ولا اسم. */
    public function test_an_unregistered_number_leaves_a_reason_in_the_office_log(): void
    {
        Log::spy();

        $result = PortalOtp::request('95550000');

        $this->assertFalse($result['ok']);
        $this->assertSame('unregistered', $result['reason']);
        $this->assertSame([], $this->destinations());
        Log::shouldHaveReceived('info')->once()->withArgs(
            fn ($m) => str_contains($m, 'matches no client record') && !str_contains($m, '95550000')
        );
    }

    /**
     * رقمٌ على ملفَّين (أبٌ وشركتُه): يصله الرمزُ مرّةً واحدة، والتحقّقُ يُعيد الملفَّين
     * ليختار — بقرار المكتب. كان يُردّ فيقف خارجَ البوابة كلُّ من سُجّل على ملفَّين.
     */
    public function test_a_shared_number_gets_one_code_that_opens_both_files(): void
    {
        $father = $this->client('الأب', '91234567');
        $company = $this->client('شركةُ الأب', '+968 9123 4567');
        $code = null;
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(function ($request) use (&$code) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            preg_match('/(\d{6})/', (string) $request['text'], $m);
            $code = $m[1] ?? null;

            return Http::response(['key' => ['id' => 'OTP-' . uniqid()]], 201);
        });

        $this->assertTrue(PortalOtp::request('91234567')['ok']);
        $this->assertSame(['96891234567'], $this->destinations());

        $verified = PortalOtp::verify('91234567', (string) $code);

        $this->assertTrue($verified['ok']);
        $this->assertSame([$father->id, $company->id], $verified['clients']->pluck('id')->all());
    }

    /** وملفٌّ نُقل عنه الرقمُ بين الإرسال والتحقّق لا يُفتح بذلك الرمز. */
    public function test_a_file_whose_number_changed_meanwhile_is_not_offered(): void
    {
        $father = $this->client('الأب', '91234567');
        $company = $this->client('شركةُ الأب', '91234567');
        $code = null;
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(function ($request) use (&$code) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            preg_match('/(\d{6})/', (string) $request['text'], $m);
            $code = $m[1] ?? null;

            return Http::response(['key' => ['id' => 'OTP-' . uniqid()]], 201);
        });

        PortalOtp::request('91234567');
        $company->update(['phone' => '95550000']);

        $this->assertSame([$father->id], PortalOtp::verify('91234567', (string) $code)['clients']->pluck('id')->all());
    }

    /** وإخفاقُ واتساب يُكتب بسببه — صفُّ الرسالة وحده لا يراه أحد (الواردُ مخفيٌّ افتراضاً). */
    public function test_a_refused_send_is_logged_with_its_reason(): void
    {
        $client = $this->client('سالم', '91234567');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            return Http::response(['status' => 400, 'response' => ['message' => [['exists' => false, 'number' => 'x']]]], 400);
        });
        Log::spy();

        PortalOtp::request('91234567');

        // كما يردّ Evolution فعلاً عن رقمٍ ليس على واتساب: كائنٌ لا نصّ — وكان قارئُ
        // الخطأ يرمي «Array to string conversion» فتبقى الرسالةُ «queued» بلا سبب
        $row = WhatsAppMessage::latest('id')->first();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $row->status);
        $this->assertStringContainsString('ليس على واتساب', (string) $row->error_title);
        // ‏error: الإنتاجُ يكتب السجلَّ من مستوى error فما فوق — warning كان يُسقط
        Log::shouldHaveReceived('error')->withArgs(
            fn ($m) => str_contains($m, 'WhatsApp did not take the code for client #' . $client->id)
                && str_contains($m, 'ليس على واتساب')
        );
    }

    /**
     * رقمُ واتساب المكتب نفسُه: الرسالةُ منه إليه تقع في «أنت» بلا تنبيه، فيظنّ من
     * يجرّب أنّ الرمزَ لم يُرسَل. يُقال ذلك ولا يُرسَل شيء.
     */
    public function test_the_office_own_number_is_named_not_messaged(): void
    {
        WhatsAppSettings::rememberIdentity('+968 9123 4567', 'مكتب');
        $this->client('المحامي نفسُه', '91234567');

        $result = PortalOtp::request('91234567');

        $this->assertFalse($result['ok']);
        $this->assertSame('own_number', $result['reason']);
        $this->assertSame([], $this->destinations());
    }

    /** ردٌّ عابرٌ من الجسر («Connection Closed»): محاولةٌ ثانية تُنقذ الرمز. */
    public function test_a_transient_refusal_is_retried_once(): void
    {
        $this->client('سالم', '91234567');
        $calls = 0;
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(function ($request) use (&$calls) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            return ++$calls === 1
                ? Http::response(['status' => 400, 'response' => ['message' => ['Error: Connection Closed']]], 400)
                : Http::response(['key' => ['id' => 'OTP-OK']], 201);
        });

        $result = PortalOtp::request('91234567');

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $calls);
        $this->assertSame(WhatsAppMessage::STATUS_SENT, WhatsAppMessage::latest('id')->value('status'));
    }

    /** ورقمٌ مكتوبٌ بالأرقام الهنديّة في ملفّ الموكّل يُراسَل — لا يصير فارغاً. */
    public function test_arabic_indic_digits_in_the_client_file_reach_whatsapp(): void
    {
        $this->client('سالم', '٩١٢٣٤٥٦٧');

        $this->assertTrue(PortalOtp::request('91234567')['ok']);
        $this->assertSame(['96891234567'], $this->destinations());
    }

    /**
     * و2xx بلا معرّف: قبلها الجسرُ ولا تأكيد — تُحسب مرّةً ولا تُعاد. عدُّها إخفاقاً
     * كان يُرسل الرمزَ مرّتين ويُسقط السقفَ والدقيقتين (مضايقةٌ بلا حدّ).
     */
    public function test_an_accepted_send_without_id_is_sent_once_and_counted(): void
    {
        $this->client('سالم', '91234567');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(fn ($request) => str_contains($request->url(), '/instance/connectionState/')
            ? Http::response(['instance' => ['state' => 'open']], 200)
            : Http::response(['status' => 'PENDING'], 201));

        $result = PortalOtp::request('91234567');

        $this->assertTrue($result['ok']);
        $this->assertSame('unconfirmed', $result['reason']);
        $this->assertCount(1, $this->destinations(), 'أُرسل الرمزُ مرّتين');
        $this->assertSame(1, $this->dayCount('91234567'));
        $this->assertSame('cooldown', PortalOtp::request('91234567')['reason'], 'الدقيقتان سقطتا');
    }

    /** ومهلةٌ بلا ردّ (قد تكون خرجت): لا تُعاد، وتُحسب، وتحجز الدقيقتين. */
    public function test_a_timed_out_send_is_not_resent_and_still_counts(): void
    {
        $this->client('سالم', '91234567');
        $sends = 0;
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(function ($request) use (&$sends) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            $sends++;

            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds');
        });

        $result = PortalOtp::request('91234567');

        $this->assertTrue($result['ok'], 'الرمزُ قد يكون وصل — الصفحةُ تبقى عند خطوة إدخاله');
        $this->assertSame('unconfirmed', $result['reason']);
        $this->assertSame(1, $sends, 'مهلةٌ بلا ردّ أُعيدت — قد يصل الرمزُ مرّتين');
        $this->assertSame(1, $this->dayCount('91234567'));
        $this->assertSame('cooldown', PortalOtp::request('91234567')['reason']);
    }

    /** وسببُ الجسر الخامُ لا يُعرض لزائرٍ مجهول — يبقى في السجلّ وصفّ الدفتر. */
    public function test_raw_bridge_errors_never_reach_the_visitor(): void
    {
        $this->client('سالم', '91234567');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(fn ($request) => str_contains($request->url(), '/instance/connectionState/')
            ? Http::response(['instance' => ['state' => 'open']], 200)
            : Http::response(['status' => 500, 'response' => ['message' => 'Invalid `this.prismaRepository.contact.findMany()` invocation in /evolution/dist/main.js']], 500));

        $result = PortalOtp::request('91234567');

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('prisma', mb_strtolower($result['message']));
        $this->assertStringNotContainsString('/evolution', $result['message']);
        $this->assertStringContainsString('prisma', mb_strtolower((string) WhatsAppMessage::latest('id')->value('error_title')));
    }

    /**
     * رفضٌ يقينيّ عند الطلب الثاني لا يُبطل الرمزَ الأوّل الذي في يد الموكّل.
     *
     * كان الرمزُ الجديدُ يُكتب قبل الإرسال ثمّ يُمحى عند الإخفاق — فيمحو معه الرمزَ
     * السابقَ الصالح، فيكتبه الموكّلُ فيُقال «انتهت صلاحيته».
     */
    public function test_a_refused_resend_keeps_the_code_already_delivered(): void
    {
        $this->client('سالم', '91234567');
        $refuse = false;
        $first = null;
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(function ($request) use (&$refuse, &$first) {
            if (str_contains($request->url(), '/instance/connectionState/')) {
                return Http::response(['instance' => ['state' => 'open']], 200);
            }

            if ($refuse) {
                return Http::response(['status' => 400, 'response' => ['message' => [['exists' => false]]]], 400);
            }

            preg_match('/(\d{6})/', (string) $request['text'], $m);
            $first = $m[1] ?? null;

            return Http::response(['key' => ['id' => 'OTP-1']], 201);
        });

        $this->assertTrue(PortalOtp::request('91234567')['ok']);
        $this->travel(130)->seconds();
        $refuse = true;

        $this->assertSame('send_failed', PortalOtp::request('91234567')['reason']);
        $this->assertTrue(PortalOtp::hasLiveCode('91234567'));
        $this->assertTrue(PortalOtp::verify('91234567', (string) $first)['ok'], 'الرمزُ الأوّل أُبطل برفض الثاني');
    }

    private function dayCount(string $phone): int
    {
        return (int) \Illuminate\Support\Facades\Cache::get(
            'portal_otp_day:' . hash_hmac('sha256', Client::normalizePhone($phone), (string) config('app.key')), 0
        );
    }

    // ───────────────────────── portal:otp-check

    /** الأمرُ يجيب عن الأحوال الثلاثة — ولا يُرسل رمزاً ولا رسالة. */
    public function test_the_check_command_explains_each_case_and_sends_nothing(): void
    {
        $this->client('سالم', '91234567');
        $this->client('أخوان', '92223333');
        $this->client('أخوان ٢', '92223333');

        // (توقّعٌ واحدٌ للسطر: توقّعان على سطرٍ واحد لا يُشبعهما نداءُ كتابةٍ واحد)
        $this->artisan('portal:otp-check', ['phone' => '91234567'])
            ->expectsOutputToContain('موكّلٌ واحد (#1) — يُرسل الرمزُ إلى واتساب +968••••4567')
            ->assertExitCode(0);

        $this->artisan('portal:otp-check', ['phone' => '95550000'])
            ->expectsOutputToContain('غيرُ مسجَّلٍ لأيّ موكّل')
            ->assertExitCode(1);

        $this->artisan('portal:otp-check', ['phone' => '92223333'])
            ->expectsOutputToContain('مسجَّلٌ على 2 ملفّات')
            ->assertExitCode(0);

        $this->assertSame([], $this->destinations());
        $this->assertSame(0, WhatsAppMessage::count());
    }

    /** ويعرض آخرَ ما جرى للرموز: الحالةُ والسبب — بلا رمزٍ ولا رقم. */
    public function test_the_check_command_shows_the_last_send_results(): void
    {
        $this->client('سالم', '91234567');
        PortalOtp::request('91234567');

        $this->artisan('portal:otp-check', ['phone' => '91234567'])
            ->expectsOutputToContain('خرج إلى واتساب')
            ->assertExitCode(0);
    }

    /** وواتساب غيرُ موصول يُقال — فهو أوّلُ ما يمنع الرمز. */
    public function test_the_check_command_names_a_disconnected_bridge(): void
    {
        $this->client('سالم', '91234567');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(fn () => Http::response(['instance' => ['state' => 'close']], 200));

        $this->artisan('portal:otp-check', ['phone' => '91234567'])
            ->expectsOutputToContain('غير موصول')
            ->assertExitCode(1);
    }

    // ───────────────────────── portal:readiness

    /** الجاهزيّةُ تُحسب بالهاتف لا بالهويّة: بلا هاتفٍ = لا يصله رمز. */
    public function test_readiness_counts_who_can_actually_receive_a_code(): void
    {
        $this->client('جاهز', '91234567');
        $this->client('أخوان', '92223333');
        $this->client('أخوان ٢', '92223333');
        Client::create(['name' => 'بلا هاتف', 'type' => 'individual']);

        \Illuminate\Support\Facades\Artisan::call('portal:readiness', ['--json' => true]);
        $summary = json_decode(trim(\Illuminate\Support\Facades\Artisan::output()), true);

        // الرقمُ المشترك لا يمنع الدخول (يختار صاحبُه ملفَّه) — يُعدّ للمكتب وحسب
        $this->assertSame(4, $summary['clients_total']);
        $this->assertSame(3, $summary['clients_ready']);
        $this->assertSame(1, $summary['clients_blocked']);
        $this->assertSame(1, $summary['missing_phone']);
        $this->assertSame(2, $summary['shared_phone']);
        $this->assertSame(0, $summary['missing_national_id']);
    }
}
