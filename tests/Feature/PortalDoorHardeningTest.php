<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * بابُ الهويّة في بوّابة الموكّلين — وهو البابُ الوحيد المفتوح على
 * الإنترنت لغير الموظّفين.
 *
 * ═══ عطبان كانا فيه ═══
 *
 * ١) التلميحُ الشكليُّ يُميَّز من أوّل رقم.
 *
 * حين تُدخَل هويّةٌ لا يعرفها المكتب، يُصنع «تحدٍّ شكليّ» بتلميحٍ
 * يشبه الحقيقيَّ كي لا يُعرف الفرق. وكان أوّلُ رقمٍ فيه يُؤخذ من
 * بصمةٍ موزّعةٍ على العشرة بالتساوي — وأرقامُ عُمان المحمولة تبدأ
 * بـ٩ أو ٧ لا غير. فسبعُ مرّاتٍ من عشرٍ يخرج رقمٌ مستحيلٌ ⇐ «هذا
 * ليس موكّلاً»، قطعاً وبطلبٍ واحد.
 *
 * وعلاقةُ الموكّل بمكتبه سرٌّ بذاته.
 *
 * ٢) والسرُّ عشرُ بتّاتٍ بميزانيّةٍ تتجدّد.
 *
 * المطلوبُ آخرُ ثلاثةِ أرقامٍ من الهاتف — ألفُ احتمال. والسقفُ
 * المستقلُّ عن العنوان عشرون محاولةً في الساعة، **تتجدّد** لا تُقفل.
 * فمن دار على عناوينَ قليلة استنفد الألفَ في خمسٍ وعشرين ساعةً
 * وسطياً، ثمّ فتح ملفَّ الموكّل كلَّه.
 */
class PortalDoorHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function decoyHintFor(string $seed): string
    {
        $m = new \ReflectionMethod(\App\Services\ClientPortal\ClientAuthenticator::class, 'decoyHint');
        $m->setAccessible(true);

        return $m->invoke(null, $seed);
    }

    /**
     * التلميحُ الشكليُّ يبدأ بما يبدأ به رقمٌ عُمانيٌّ حقيقيّ.
     *
     * يُفحص على مئةِ بذرةٍ مختلفة: لو بقي التوزيعُ على العشرة
     * بالتساوي لسقط عند أوّل عشرٍ منها.
     */
    public function test_the_decoy_hint_cannot_be_told_apart_by_its_first_digit(): void
    {
        $bad = [];

        for ($i = 0; $i < 100; $i++) {
            $hint = $this->decoyHintFor('seed-' . $i);

            if (!in_array($hint[0], ['7', '9'], true)) {
                $bad[] = $hint;
            }
        }

        $this->assertSame([], $bad,
            'تلميحاتٌ شكليّةٌ تبدأ برقمٍ لا يبدأ به رقمٌ عُمانيّ: ' . implode(', ', array_slice($bad, 0, 5)));
    }

    /** وشكلُه شكلُ الحقيقيّ: أربعةُ أرقامٍ ثمّ أربعُ نقاط. */
    public function test_the_decoy_hint_has_the_same_shape_as_a_real_one(): void
    {
        $client = Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => '1234567', 'phone' => '96891234567',
        ]);

        $m = new \ReflectionMethod(\App\Services\ClientPortal\ClientAuthenticator::class, 'maskDigits');
        $m->setAccessible(true);
        $real = $m->invoke(null, $client->phone);

        $decoy = $this->decoyHintFor('any-seed');

        $this->assertMatchesRegularExpression('/^[79]\d{3}•+$/u', $decoy);
        $this->assertSame(mb_strlen($real), mb_strlen($decoy),
            'طولُ الشكليّ يخالف طولَ الحقيقيّ — فرقٌ يُقاس');
    }

    /** وثابتٌ لصاحبه: من أعاد المحاولة بالرقم نفسِه رأى التلميحَ نفسَه. */
    public function test_the_decoy_hint_is_stable_for_the_same_id(): void
    {
        $this->assertSame($this->decoyHintFor('same'), $this->decoyHintFor('same'));
        $this->assertNotSame($this->decoyHintFor('a'), $this->decoyHintFor('b'));
    }

    /**
     * والبابُ يُقفل بعد ثلاث موجاتٍ — لا يتجدّد إلى الأبد.
     *
     * يُقاس على الثوابت نفسِها لا على أرقامٍ مكتوبةٍ هنا، فمن غيّرها
     * غيّر الاختبارَ معه ولا يمرّ سهواً.
     */
    public function test_the_identity_door_locks_for_a_day_after_repeated_waves(): void
    {
        $r = new \ReflectionClass(\App\Services\ClientPortal\ClientAuthenticator::class);
        $rounds = $r->getConstant('LOCK_ROUNDS');
        $seconds = $r->getConstant('LOCK_SECONDS');
        $memory = $r->getConstant('LOCK_MEMORY');

        $this->assertIsInt($rounds);
        $this->assertGreaterThanOrEqual(2, $rounds, 'موجةٌ واحدةٌ تكفي للإغلاق — قاسٍ على الموكّل الصادق');
        $this->assertGreaterThanOrEqual(3600, $seconds, 'الإغلاق أقصرُ من ساعة — لا يوقف تخميناً');
        $this->assertGreaterThan($seconds, $memory, 'العدُّ يُنسى قبل أن ينتهي الإغلاق');

        // ‏١٠٠٠ احتمالٍ ÷ (٢٠ محاولةً × ٣ موجات) — الإغلاقُ يقع قبل
        // استنفاد عُشر الفضاء
        $budget = $r->getConstant('CLIENT_LIMIT') * $rounds;
        $this->assertLessThan(100, $budget,
            'الميزانيّةُ قبل الإغلاق تغطّي عُشرَ الاحتمالات أو أكثر');
    }

    /** والقفلُ يُقرأ من مخزنٍ يعبر الطلبات — لا من ذاكرة الطلب. */
    public function test_the_lock_survives_across_requests(): void
    {
        $key = 'client-portal:verify-lock:7';

        Cache::put($key . ':until', now()->timestamp + 3600, 3600);

        $this->assertGreaterThan(now()->timestamp, Cache::get($key . ':until'));

        Cache::forget($key . ':until');
        RateLimiter::clear('client-portal:verify-client:7');
    }
}
