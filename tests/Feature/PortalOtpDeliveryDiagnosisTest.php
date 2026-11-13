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

    /** رقمٌ لا يطابق أحداً: لا رمز — ويُكتب السببُ للمكتب بلا رقمٍ ولا اسم. */
    public function test_an_unregistered_number_leaves_a_reason_in_the_office_log(): void
    {
        Log::spy();

        $result = PortalOtp::request('95550000');

        $this->assertTrue($result['ok'], 'الردُّ للزائر محايدٌ كما كان');
        $this->assertSame([], $this->destinations());
        Log::shouldHaveReceived('info')->once()->withArgs(
            fn ($m) => str_contains($m, 'matches 0 client record(s)') && !str_contains($m, '95550000')
        );
    }

    /** ورقمٌ مسجَّلٌ لموكّلَين: لا رمز — والسجلُّ يقول إنّهما اثنان. */
    public function test_a_shared_number_is_named_as_shared(): void
    {
        $this->client('الأب', '91234567');
        $this->client('شركةُ الأب', '+968 9123 4567');
        Log::spy();

        PortalOtp::request('91234567');

        $this->assertSame([], $this->destinations());
        Log::shouldHaveReceived('info')->once()->withArgs(fn ($m) => str_contains($m, 'matches 2 client record(s)'));
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
        Log::shouldHaveReceived('warning')->withArgs(
            fn ($m) => str_contains($m, 'WhatsApp refused the code for client #' . $client->id)
                && str_contains($m, 'ليس على واتساب')
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
            ->expectsOutputToContain('مسجَّلٌ لـ2 موكّلين')
            ->assertExitCode(1);

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

    /** الجاهزيّةُ تُحسب بالهاتف لا بالهويّة: بلا هاتفٍ أو برقمٍ مشترك = لا يصله رمز. */
    public function test_readiness_counts_who_can_actually_receive_a_code(): void
    {
        $this->client('جاهز', '91234567');
        $this->client('أخوان', '92223333');
        $this->client('أخوان ٢', '92223333');
        Client::create(['name' => 'بلا هاتف', 'type' => 'individual']);

        \Illuminate\Support\Facades\Artisan::call('portal:readiness', ['--json' => true]);
        $summary = json_decode(trim(\Illuminate\Support\Facades\Artisan::output()), true);

        $this->assertSame(4, $summary['clients_total']);
        $this->assertSame(1, $summary['clients_ready']);
        $this->assertSame(3, $summary['clients_blocked']);
        $this->assertSame(1, $summary['missing_phone']);
        $this->assertSame(2, $summary['shared_phone']);
        $this->assertSame(0, $summary['missing_national_id']);
    }
}
