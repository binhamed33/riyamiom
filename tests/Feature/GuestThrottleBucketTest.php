<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لكلّ بابِ زوّارٍ دلوُه الخاصّ في الخانق.
 *
 * ═══ الثغرة ═══
 *
 * ThrottleRequests يبني مفتاحَه من sha1(النطاق|العنوان) والبادئةُ فارغةٌ
 * افتراضاً، فكلُّ مسارات الزوّار تتشارك عدّاداً واحداً — والمهلةُ تُثبَّت
 * بأوّل من يلمسه. طلبٌ واحدٌ على ويبهوكٍ مهلتُه دقيقةٌ كان يحوّل «٢٠ في
 * عشر دقائق» إلى «٢٠ في دقيقة» لبقيّة الأبواب. الوسيطُ الخامس هو البادئة.
 */
class GuestThrottleBucketTest extends TestCase
{
    use RefreshDatabase;

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
            'client.access.otp', 'client.access.otp.verify', 'client.access.otp.reset',
            'client.link.open', 'marketing.register.store', 'whatsapp.webhook.receive',
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
