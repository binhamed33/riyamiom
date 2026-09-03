<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * الرقمُ السرّيُّ ثلاثةُ أرقام — والسقفُ يجب أن يكون على الحساب.
 *
 * ═══ الثغرة ═══
 *
 * حدُّ الخمسِ محاولاتٍ كان مربوطاً برمز التحدّي، والتحدّي يُولَد
 * جديداً مع كلّ طلبِ هويّة. فمن خمّن خمساً عاد إلى الخطوة الأولى وأخذ
 * خمساً أخرى — بلا نهاية. أي أنّه لم يكن قفلاً على الحساب بل مطبّاً،
 * وألفُ احتمالٍ تُمشى في ساعتين.
 *
 * ═══ والدلوُ المشترك ═══
 *
 * ThrottleRequests يبني مفتاحَه من sha1(النطاق|العنوان) والبادئةُ
 * فارغةٌ افتراضاً، فكلُّ مسارات الزوّار تتشارك عدّاداً واحداً —
 * والمهلةُ تُثبَّت بأوّل من يلمسه. طلبٌ واحدٌ على ويبهوكٍ مهلتُه دقيقةٌ
 * كان يحوّل «٢٠ في عشر دقائق» إلى «٢٠ في دقيقة» لبقيّة الأبواب.
 */
class PortalBruteForceTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');

        $this->client = Client::create([
            'name' => 'سالم بن علي الريامي',
            'phone' => '96891234567',
            'national_id' => '13572468',
            'type' => 'individual',
        ]);

        RateLimiter::clear('client-portal:verify-client:' . $this->client->id);
    }

    /**
     * محاولةُ تخمينٍ واحدة: هويّةٌ ثمّ ثلاثةُ أرقامٍ خاطئة.
     *
     * ومن عنوانٍ جديدٍ في كلّ مرّة — هكذا يعمل المهاجمُ فعلاً. حدُّ
     * الخطوة الأولى على العنوان (٨/١٠د)، فهو لا يحدّه؛ الذي يحدّه
     * سقفُ الموكّل وحدَه، وهو المفحوص هنا.
     */
    private function guess(int $round, string $digits): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.' . (($round % 200) + 1)]);

        $this->post(route('client.access.lookup'), ['national_id' => '13572468']);
        $this->post(route('client.access.verify'), ['digits' => $digits]);
    }

    /**
     * تحدٍّ جديدٌ لا يُعيد الرصيد: السقفُ على الموكّل لا على التحدّي.
     *
     * قبل الإصلاح كان هذا الاختبار ينجح في الدخول: كلُّ خمسٍ يتبعها
     * تحدٍّ جديدٌ برصيدٍ كامل.
     */
    public function test_a_new_challenge_does_not_hand_back_more_guesses(): void
    {
        // ٢٢ محاولةً خاطئة من عناوينَ مختلفة — أكثرُ من سقف الموكّل (٢٠)
        for ($i = 0; $i < 22; $i++) {
            $this->guess($i, str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }

        $this->assertGreaterThanOrEqual(
            20,
            RateLimiter::attempts('client-portal:verify-client:' . $this->client->id),
            'التخميناتُ لم تتراكم على الموكّل — السقفُ ما زال على التحدّي',
        );

        // والصحيحةُ بعدها تُردّ: الحسابُ مقفلٌ لا التحدّي
        $this->guess(99, '567');
        $this->get(route('client.portal.home'))->assertRedirect('/client-access');
    }

    /** والسقفُ يبقى دون العشرين: صاحبُ الرقم يدخل من محاولته الأولى. */
    public function test_the_owner_still_gets_in(): void
    {
        $this->post(route('client.access.lookup'), ['national_id' => '13572468']);
        $this->post(route('client.access.verify'), ['digits' => '567'])
            ->assertRedirect(route('client.portal.home'));

        $this->get(route('client.portal.home'))->assertOk();
    }

    /** ونجاحُ الدخول يمسح رصيدَ التخمين — لا يُعاقَب بمحاولاتٍ سبقت. */
    public function test_a_successful_login_clears_the_account_counter(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->guess($i, '00' . $i);
        }

        $this->post(route('client.access.lookup'), ['national_id' => '13572468']);
        $this->post(route('client.access.verify'), ['digits' => '567'])
            ->assertRedirect(route('client.portal.home'));

        $this->assertSame(
            0,
            RateLimiter::attempts('client-portal:verify-client:' . $this->client->id),
            'رصيدُ التخمين لم يُمسح بعد دخولٍ ناجح',
        );
    }

    /**
     * ولكلّ بابٍ دلوُه: لا يُخفَّض حدُّ بابٍ بلمس بابٍ آخر.
     *
     * يُفحص البناءُ لا السلوك: بيئةُ الاختبار تعطّل الخانقَ أصلاً،
     * والخللُ كان في غياب البادئة لا في منطقٍ يُستدعى.
     */
    public function test_every_guest_route_has_its_own_throttle_bucket(): void
    {
        $routes = app('router')->getRoutes();
        $buckets = [];

        foreach ([
            'client.access.lookup', 'client.access.verify', 'client.access.otp',
            'client.access.otp.verify', 'client.access.otp.reset', 'client.link.open',
            'marketing.register.store', 'whatsapp.webhook.receive',
        ] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "لا مسارَ باسم {$name}");

            $throttle = collect($route->gatherMiddleware())
                ->first(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));

            $this->assertNotNull($throttle, "{$name}: بلا خانق");

            $parts = explode(',', substr($throttle, strlen('throttle:')));
            $this->assertCount(3, $parts, "{$name}: الخانقُ بلا بادئة — يتشارك الدلوَ مع غيره");

            $prefix = $parts[2];
            $clash = $buckets[$prefix] ?? null;
            $this->assertNull($clash, "{$name} و{$clash} يتشاركان الدلو «{$prefix}»");
            $buckets[$prefix] = $name;
        }
    }
}
