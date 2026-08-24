<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAttempt;
use App\Models\Setting;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ClientPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('client-portal:lookup:127.0.0.1');
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
    }

    private function client(array $overrides = []): Client
    {
        return Client::factory()->create(array_merge([
            'name' => 'محمد بن سالم',
            'national_id' => '1234567890',
            'phone' => '+968 91234567',
        ], $overrides));
    }

    private function signIn(Client $client): void
    {
        $this->post(route('client.access.lookup'), ['national_id' => $client->national_id]);
        $this->post(route('client.access.verify'), ['digits' => '567']);
    }

    public function test_a_client_signs_in_with_national_id_then_last_three_phone_digits(): void
    {
        $client = $this->client();

        $this->get(route('client.access'))->assertOk()->assertSee(__('portal.login.national_id'));

        // الخطوة الأولى لا تُدخل أحداً — تفتح التحدّي فقط
        $this->post(route('client.access.lookup'), ['national_id' => '1234567890'])
            ->assertRedirect(route('client.access'));
        $this->assertNull(session('client_access_id'));

        // الخطوة الثانية هي التي تُدخل
        $this->post(route('client.access.verify'), ['digits' => '567'])
            ->assertRedirect(route('client.portal.home'));

        $this->assertSame($client->id, session('client_access_id'));
        $this->get(route('client.portal.home'))->assertOk()->assertSee('محمد بن سالم');
    }

    /** نصّ شارة التلميح كما يقرؤه الموكّل على الشاشة. */
    private function hintBadge(): string
    {
        $html = $this->get(route('client.access'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/p-badge mute/', $html, 'شارة التلميح غائبة');
        preg_match('/p-badge mute[^>]*>([^<]*)</u', $html, $m);

        return trim($m[1] ?? '');
    }

    /**
     * كانت شاشة الخطوة الثانية تطبع «••••• 567» — و«567» هي بعينها ما
     * تطلبه. فمن عرف رقم الهوية وحده يقرأ الجواب ويدخل، ورقمُ الهوية
     * يُكتب في العقود ويُعطى لجهات كثيرة فليس سرّاً يُبنى عليه دخول.
     */
    public function test_the_verification_screen_never_prints_the_digits_it_asks_for(): void
    {
        $this->client(['phone' => '+968 91234567']);
        $this->post(route('client.access.lookup'), ['national_id' => '1234567890']);

        $badge = $this->hintBadge();

        $this->assertStringNotContainsString('567', $badge, 'الأرقام المطلوبة مطبوعة على الشاشة');
        $this->assertStringNotContainsString('4567', $badge, 'ما يضيّق التخمين إليها مطبوع كذلك');
        $this->assertSame('9123••••', $badge);
    }

    /**
     * التلميح يحجب الأربعة الأخيرة مهما كانت صيغة الرقم، ولا يتبدّل
     * على العميل نفسه بين رقمٍ محفوظ بمفتاح الدولة وآخر بدونه.
     */
    public function test_the_hint_hides_the_last_four_digits_in_every_shape(): void
    {
        foreach ([
            '+968 91234567' => '9123••••',
            '96891234567' => '9123••••',
            '91234567' => '9123••••',
            '12345' => '1••••',
            '456' => '•••',
        ] as $phone => $expected) {
            $client = Client::factory()->create([
                'national_id' => (string) random_int(1000000, 9999999),
                'phone' => $phone,
            ]);

            $this->post(route('client.access.lookup'), ['national_id' => $client->national_id]);

            $badge = $this->hintBadge();
            $secret = substr(preg_replace('/\D+/', '', $phone), -3);

            $this->assertSame($expected, $badge, "التلميح خاطئ للرقم {$phone}");
            $this->assertStringNotContainsString($secret, $badge, "السرّ ظاهر في تلميح {$phone}");

            $this->post(route('client.access.logout'));
            RateLimiter::clear('client-portal:lookup:127.0.0.1');
        }
    }

    public function test_the_second_step_cannot_be_skipped(): void
    {
        $this->client();

        // بلا تحدٍّ قائم لا ينفع التحقّق مهما كانت الأرقام
        $this->post(route('client.access.verify'), ['digits' => '567'])
            ->assertRedirect(route('client.access'));

        $this->assertNull(session('client_access_id'));
        $this->get(route('client.portal.home'))->assertRedirect(route('client.access'));
    }

    public function test_wrong_digits_are_refused_and_reveal_nothing(): void
    {
        $this->client();

        $this->post(route('client.access.lookup'), ['national_id' => '1234567890']);
        $this->post(route('client.access.verify'), ['digits' => '999'])
            ->assertSessionHas('portal_error', __('portal.login.failed'));

        $this->assertNull(session('client_access_id'));
    }

    /**
     * جوهر الأمان هنا: رد الهوية الخاطئة ورد الهوية الصحيحة بهاتف خاطئ
     * يجب أن يكونا واحداً، وإلا صار رقم الهوية قابلاً للتعداد.
     */
    public function test_an_unknown_id_and_a_wrong_phone_give_the_very_same_answer(): void
    {
        $this->client();

        $unknown = $this->post(route('client.access.lookup'), ['national_id' => '0000000000']);
        $unknownMessage = session('portal_error');

        session()->flush();
        RateLimiter::clear('client-portal:lookup:127.0.0.1');

        $this->post(route('client.access.lookup'), ['national_id' => '1234567890']);
        $this->post(route('client.access.verify'), ['digits' => '000']);
        $wrongPhoneMessage = session('portal_error');

        $this->assertSame(__('portal.login.failed'), $unknownMessage);
        $this->assertSame(__('portal.login.failed'), $wrongPhoneMessage);
        $this->assertSame($unknownMessage, $wrongPhoneMessage);
    }

    public function test_the_full_phone_number_never_reaches_the_browser(): void
    {
        $this->client(['phone' => '+968 91234567']);

        $this->post(route('client.access.lookup'), ['national_id' => '1234567890']);
        $html = $this->get(route('client.access'))->assertOk()->getContent();

        $this->assertStringNotContainsString('91234567', $html);
        $this->assertStringNotContainsString('9123456', $html);
    }

    public function test_repeated_guessing_is_throttled(): void
    {
        $this->client();

        // التحدّي الواحد لا يحتمل تخميناً مفتوحاً
        $this->post(route('client.access.lookup'), ['national_id' => '1234567890']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('client.access.verify'), ['digits' => '111']);
        }

        $this->post(route('client.access.verify'), ['digits' => '567']);

        // حتى الأرقام الصحيحة لا تنفع بعد استهلاك التحدّي
        $this->assertNull(session('client_access_id'));
    }

    public function test_lookup_attempts_are_rate_limited_per_address(): void
    {
        $this->client();

        for ($i = 0; $i < 8; $i++) {
            $this->post(route('client.access.lookup'), ['national_id' => '99999' . $i]);
        }

        $this->post(route('client.access.lookup'), ['national_id' => '1234567890']);

        // نقارن بالنص المترجَم لا بعربية مكتوبة يدوياً — الاختبارات تعمل
        // بالإنجليزية افتراضاً
        $this->assertSame(
            __('portal.login.locked', ['minutes' => 10]),
            (string) session('portal_error'),
        );
    }

    public function test_attempts_are_logged_without_storing_the_national_id(): void
    {
        $this->client();

        $this->post(route('client.access.lookup'), ['national_id' => '1234567890']);

        $attempt = ClientPortalAttempt::where('step', 'lookup')->firstOrFail();

        $this->assertNotNull($attempt->identifier_hash);
        $this->assertSame(64, strlen($attempt->identifier_hash));
        $this->assertStringNotContainsString('1234567890', $attempt->identifier_hash);
        $this->assertSame(hash('sha256', '1234567890'), $attempt->identifier_hash);
    }

    public function test_a_client_with_no_registered_phone_cannot_be_impersonated(): void
    {
        // بلا هاتف لا سبيل للتحقّق — ويُعامَل كغير موجود فلا يكشف الرد شيئاً
        $this->client(['phone' => '']);

        $this->post(route('client.access.lookup'), ['national_id' => '1234567890'])
            ->assertSessionHas('portal_error', __('portal.login.failed'));

        $this->post(route('client.access.verify'), ['digits' => '123']);
        $this->assertNull(session('client_access_id'));
    }

    public function test_logout_really_ends_the_session(): void
    {
        $client = $this->client();
        $this->signIn($client);
        $this->assertSame($client->id, session('client_access_id'));

        $this->post(route('client.access.logout'))->assertRedirect(route('client.access'));

        $this->assertNull(session('client_access_id'));
        $this->get(route('client.portal.home'))->assertRedirect(route('client.access'));
    }

    public function test_a_disabled_portal_locks_everyone_out(): void
    {
        $client = $this->client();
        $this->signIn($client);

        Setting::set(ClientPortal::KEY_ENABLED, '0', 'client_portal');

        $this->get(route('client.portal.home'))->assertRedirect(route('client.access'));
        $this->get(route('client.access'))->assertOk()->assertSee(__('portal.login.disabled'));
        $this->post(route('client.access.lookup'), ['national_id' => '1234567890'])->assertNotFound();
    }

    public function test_arabic_indic_digits_in_the_id_are_accepted(): void
    {
        $this->client(['national_id' => '1234567890']);

        $this->post(route('client.access.lookup'), ['national_id' => '١٢٣٤٥٦٧٨٩٠']);
        $this->post(route('client.access.verify'), ['digits' => '567'])
            ->assertRedirect(route('client.portal.home'));
    }
}
