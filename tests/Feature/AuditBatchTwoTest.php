<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalLink;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientPortal\PortalLinks;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * الدفعةُ الثانية: أوراكلُ البوابة، وتضخيمُ الرسائل، وتسريباتٌ أصغر.
 */
class AuditBatchTwoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
    }

    private function client(string $name = 'موكّل', string $nid = '13572468', string $phone = '96891234567'): Client
    {
        return Client::create([
            'name' => $name, 'type' => 'individual',
            'national_id' => $nid, 'phone' => $phone,
        ]);
    }

    // ───────────────────────── أوراكلُ رقم الهويّة

    /**
     * هويّةٌ يعرفها المكتبُ وأخرى لا يعرفها: الشاشةُ واحدة.
     *
     * الرسالةُ كانت واحدةً والحالةُ ليست: التحدّي يُكتب عند المعرفة
     * وحدَها، فالصفحةُ التالية تُظهر الخطوةَ الثانية بتلميحٍ يكشف
     * أوّلَ أربعةِ أرقامٍ من الهاتف — ويؤكّد أنّ صاحبَ الهويّة موكّلٌ
     * في هذا المكتب، وهي علاقةٌ سرّيّةٌ في ذاتها.
     */
    public function test_a_known_and_an_unknown_national_id_look_identical(): void
    {
        $this->client(nid: '13572468', phone: '96891234567');

        $shapes = [];

        foreach (['13572468', '99999999'] as $nid) {
            $this->flushSession();
            $this->post(route('client.access.lookup'), ['national_id' => $nid]);

            $html = $this->get(route('client.access'))->assertOk()->getContent();

            // شكلُ الصفحة: أفيها خطوةُ الرمز؟ أفيها تلميحٌ مقنَّع؟
            $shapes[$nid] = [
                'step2' => str_contains($html, 'digits'),
                'masked' => (bool) preg_match('/\d{4}•{4}/u', $html),
            ];
        }

        $this->assertSame(
            $shapes['13572468'],
            $shapes['99999999'],
            'الشاشةُ تفرّق بين هويّةٍ يعرفها المكتبُ وأخرى لا يعرفها',
        );
        $this->assertTrue($shapes['99999999']['step2'], 'الهويّةُ المجهولة لا تصل إلى الخطوة الثانية');
    }

    /** والتحدّي الشكليُّ لا يفتح شيئاً مهما جُرّب. */
    public function test_the_decoy_challenge_can_never_succeed(): void
    {
        $this->post(route('client.access.lookup'), ['national_id' => '99999999']);

        foreach (['000', '123', '567'] as $guess) {
            $this->post(route('client.access.verify'), ['digits' => $guess]);
        }

        $this->get(route('client.portal.home'))->assertRedirect();
    }

    /**
     * ورسالةُ سقوطه هي رسالةُ سقوط الحقيقيّ حرفاً بحرف.
     *
     * كان الشكليُّ يسقط في فرع «انتهت المهلة» لأنّ isset تعدّ null
     * غياباً، والحقيقيُّ في «تعذّر التحقّق» — رسالتان مختلفتان، أي
     * الأوراكلُ نفسُه الذي أُغلق في الخطوة الأولى.
     */
    public function test_the_decoy_fails_with_the_very_same_message(): void
    {
        $this->client(nid: '13572468', phone: '96891234567');

        $this->post(route('client.access.lookup'), ['national_id' => '13572468']);
        $this->post(route('client.access.verify'), ['digits' => '000']);
        $real = session('portal_error');

        $this->flushSession();
        \Illuminate\Support\Facades\RateLimiter::clear('client-portal:lookup:127.0.0.1');

        $this->post(route('client.access.lookup'), ['national_id' => '99999999']);
        $this->post(route('client.access.verify'), ['digits' => '000']);
        $decoy = session('portal_error');

        $this->assertNotNull($real);
        $this->assertSame($real, $decoy, 'رسالةُ الشكليّ تفرّقه عن الحقيقيّ');
    }

    /** وتلميحُه ثابتٌ للرقم نفسِه — تقلّبُه كان سيفضحه. */
    public function test_the_decoy_hint_is_stable_for_the_same_id(): void
    {
        $hints = [];

        for ($i = 0; $i < 2; $i++) {
            $this->flushSession();
            $this->post(route('client.access.lookup'), ['national_id' => '77777777']);
            $html = $this->get(route('client.access'))->getContent();

            preg_match('/(\d{4}•{4})/u', $html, $m);
            $hints[] = $m[1] ?? null;
        }

        $this->assertNotNull($hints[0], 'لا تلميحَ في التحدّي الشكليّ');
        $this->assertSame($hints[0], $hints[1], 'التلميحُ الشكليُّ يتقلّب فيُعرف أنّه شكليّ');
    }

    // ───────────────────────── تضخيمُ رسائل الرمز

    /**
     * سقفٌ يوميٌّ لكلّ رقم فوق مهلة الدقيقتين.
     *
     * الدقيقتان تحدّان السرعةَ لا العدد: من يعرف أرقامَ الموكّلين كان
     * يدفع رسالةً إلى كلٍّ منهم كلَّ دقيقتين بلا نهاية — مضايقةٌ لهم،
     * وطريقٌ إلى خفض تقييم رقم المكتب عند واتساب.
     */
    public function test_the_code_has_a_daily_cap_per_number(): void
    {
        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');
        Setting::set(\App\Support\WhatsAppSettings::KEY_EVO_STATE, 'open', 'whatsapp');

        Http::fake(fn ($r) => str_contains($r->url(), '/instance/connectionState/')
            ? Http::response(['instance' => ['state' => 'open']], 200)
            : Http::response(['key' => ['id' => 'X']], 200));

        $this->client(phone: '96891234567');

        // خمسٌ تمرّ، والسادسةُ تُردّ — مع تخطّي مهلة الدقيقتين بينها
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('client.access.otp'), ['phone' => '96891234567'])
                ->assertSessionHas('portal_notice');
            $this->travel(121)->seconds();
        }

        $this->post(route('client.access.otp'), ['phone' => '96891234567'])
            ->assertSessionHas('portal_error');

        $key = 'portal_otp_day:' . hash_hmac('sha256', Client::normalizePhone('96891234567'), (string) config('app.key'));
        $this->assertSame(5, (int) Cache::get($key, 0), 'العدّادُ تجاوز السقف');
    }

    /** والسقفُ يُعدّ للأرقام غيرِ المسجَّلة أيضاً — وإلا فُرّق بينها. */
    public function test_the_daily_cap_counts_unknown_numbers_too(): void
    {
        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');
        Setting::set(\App\Support\WhatsAppSettings::KEY_EVO_STATE, 'open', 'whatsapp');

        Http::fake(fn ($r) => Http::response(['instance' => ['state' => 'open']], 200));

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('client.access.otp'), ['phone' => '96899887766']);
            $this->travel(121)->seconds();
        }

        $key = 'portal_otp_day:' . hash_hmac('sha256', Client::normalizePhone('96899887766'), (string) config('app.key'));
        $this->assertSame(5, (int) Cache::get($key, 0), 'الرقمُ غيرُ المسجَّل لا يُعدّ — فرقٌ يُستدلّ به');
    }

    // ───────────────────────── بوّابة /my

    /**
     * الموكّلُ يرى ما وُسم له صراحةً لا كلَّ ما وُسم «للجميع».
     *
     * «all» تعني «كلُّ الفريق» لا «الموكّل». فكان كلُّ مستندٍ عامٍّ في
     * قضاياه يصله بلا قرارٍ من أحد — مسوّداتٌ ومذكّراتٌ لم تُقدَّم بعد.
     */
    public function test_the_my_portal_shows_only_documents_marked_for_the_client(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $client = $this->client();
        $client->update(['user_id' => $user->id]);

        $case = LegalCase::create([
            'case_number' => 'ق/1', 'office_case_number' => 'ق/1', 'title' => 'قضية', 'description' => 'و',
            'type' => 'مدني', 'court' => 'م', 'opponent' => 'خ', 'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'created_by' => $user->id, 'opened_at' => now(),
        ]);

        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        Document::create([
            'case_id' => $case->id, 'uploaded_by' => $staff->id, 'title' => 'مسوّدة داخلية',
            'file_path' => 'documents/a.pdf', 'file_type' => 'pdf', 'file_size' => 10,
            'access_level' => 'all', 'client_visible' => false,
        ]);
        Document::create([
            'case_id' => $case->id, 'uploaded_by' => $staff->id, 'title' => 'نسخة الحكم',
            'file_path' => 'documents/b.pdf', 'file_type' => 'pdf', 'file_size' => 10,
            'access_level' => 'all', 'client_visible' => true,
        ]);

        $html = $this->actingAs($user)->get(route('client.documents'))->assertOk()->getContent();

        $this->assertStringContainsString('نسخة الحكم', $html);
        $this->assertStringNotContainsString('مسوّدة داخلية', $html, 'مستندٌ غيرُ موسومٍ للموكّل وصله');
        $this->assertStringNotContainsString($staff->name, $html, 'اسمُ الموظّف الرافع في بوابة الموكّل');
    }

    // ───────────────────────── روابطُ البوابة

    /**
     * تغيّرُ الهاتف يُبطل الروابط المرسلةَ إلى الرقم القديم.
     *
     * revokeAllFor كانت مكتوبةً لهذا ولا منادي لها: خطٌّ يُعاد بيعُه
     * يفتح بوابةَ صاحبه السابق بقيّةَ مدّة الرابط — أسبوعاً افتراضاً.
     */
    public function test_changing_the_phone_revokes_outstanding_links(): void
    {
        $client = $this->client(phone: '96891234567');
        PortalLinks::for($client, 'home');

        $this->assertSame(1, ClientPortalLink::whereNull('revoked_at')->count());

        $client->update(['phone' => '96897654321']);

        $this->assertSame(0, ClientPortalLink::whereNull('revoked_at')->count(), 'رابطٌ بقي حيّاً بعد تغيّر الهاتف');
    }

    /** وحذفُ الموكّل كذلك. */
    public function test_deleting_a_client_revokes_their_links(): void
    {
        $client = $this->client();
        PortalLinks::for($client, 'home');

        $client->delete();

        $this->assertSame(0, ClientPortalLink::whereNull('revoked_at')->count(), 'رابطٌ بقي حيّاً بعد حذف صاحبه');
    }

    // ───────────────────────── تفاصيلُ أصغر

    /** سياسةُ عزل المرفق لا يستبدلها وسيطُ الترويسات. */
    public function test_the_attachment_sandbox_policy_survives_the_middleware(): void
    {
        $middleware = (string) file_get_contents(app_path('Http/Middleware/SecurityHeaders.php'));

        $this->assertStringContainsString(
            "has('Content-Security-Policy')",
            $middleware,
            'الوسيطُ يدوس سياسةَ المحتوى التي وضعها المتحكّم',
        );
    }

    /** واسمُ ملفّ القضية لا يُبنى بلصقٍ في الترويسة. */
    public function test_the_case_file_name_is_not_pasted_into_a_header(): void
    {
        $code = (string) file_get_contents(app_path('Http/Controllers/CaseFileController.php'));

        $this->assertStringNotContainsString("'Content-Disposition' =>", $code, 'ترويسةٌ مبنيّةٌ يدوياً');
        $this->assertStringContainsString('streamDownload', $code);
    }

    /** و.env.example يُشحن على وضع الإنتاج. */
    public function test_the_shipped_env_example_is_production_safe(): void
    {
        $env = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringNotContainsString('APP_DEBUG=true', $env);
        $this->assertStringContainsString('APP_ENV=production', $env);
    }
}
