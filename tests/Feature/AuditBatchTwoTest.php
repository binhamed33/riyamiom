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
            : Http::response(['key' => ['id' => 'X-' . uniqid()]], 200));

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

    /**
     * رقمٌ غيرُ مسجَّل يُقال له ذلك صراحةً — بقرار المكتب — ولا يُعدّ ولا يُراسَل.
     *
     * كان يسمع الجملةَ نفسَها التي يسمعها المسجَّل، فيقف موكّلٌ كتب رقماً غيرَ
     * الذي في ملفّه ينتظر رمزاً لن يصل، والمكتبُ نفسُه لا يعرف لماذا.
     */
    public function test_an_unknown_number_is_told_so_and_never_counted(): void
    {
        config()->set('whatsapp.default', 'evolution');
        config()->set('whatsapp.evolution.base_url', 'http://127.0.0.1:18080');
        config()->set('whatsapp.evolution.api_key', 'test-key-0123456789');
        Setting::set(\App\Support\WhatsAppSettings::KEY_EVO_STATE, 'open', 'whatsapp');

        Http::fake(fn ($r) => Http::response(['instance' => ['state' => 'open']], 200));

        $this->post(route('client.access.otp'), ['phone' => '96899887766'])
            ->assertSessionHas('portal_error', fn ($m) => str_contains($m, 'غيرُ مسجَّلٍ لدى المكتب'))
            ->assertSessionMissing('portal_otp_phone');

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/message/sendText/'));

        $key = 'portal_otp_day:' . hash_hmac('sha256', Client::normalizePhone('96899887766'), (string) config('app.key'));
        $this->assertSame(0, (int) Cache::get($key, 0));
    }

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
