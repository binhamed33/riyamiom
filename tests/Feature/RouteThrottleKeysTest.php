<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * كلُّ حدٍّ على مسارٍ داخل مجموعةٍ محدودة يحمل اسمَه.
 *
 * ‏throttle بلا بادئةٍ مفتاحُه المستخدمُ وحده، فيتقاسمه مع حدّ المجموعة: كان
 * ‎throttle:20,1‎ على «اطلب رمز واتساب» يعني «عشرون طلباً من أيّ صفحة»، و
 * ‎throttle:5,60‎ على طلب ترقية الباقة يُرفض بعد خمس صفحاتٍ في الدقيقة.
 */
class RouteThrottleKeysTest extends TestCase
{
    public function test_every_route_level_limit_inside_a_limited_group_has_its_own_key(): void
    {
        $shared = [];

        foreach (Route::getRoutes() as $route) {
            $throttles = array_values(array_filter(
                $route->gatherMiddleware(),
                static fn ($m): bool => is_string($m) && str_starts_with($m, 'throttle:'),
            ));

            if (count($throttles) < 2) {
                continue;
            }

            // بلا اسم: ‎throttle:N,M‎ (أو أقلّ) — مفتاحُه المستخدمُ وحده
            $unnamed = array_filter($throttles, static fn (string $m): bool => count(explode(',', substr($m, 9))) < 3);

            if (count($unnamed) > 1) {
                $shared[] = $route->uri() . ' → ' . implode(' + ', $unnamed);
            }
        }

        $this->assertSame([], $shared, "حدودٌ تتقاسم مفتاحاً واحداً:\n" . implode("\n", $shared));
    }
}
