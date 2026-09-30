<?php

namespace App\Support;

/**
 * مقارنةُ شهرٍ بشهر — رقمٌ يعني شيئاً.
 *
 * ═══ ما وقع ═══
 *
 * «إجمالي القضايا 160 ▲ +1350٪» و«جديد هذا الشهر 29 — +1350٪ من الشهر
 * السابق»: قسمةُ (29 − 2) على 2 صحيحةٌ حسابيّاً وفارغةٌ معنىً — قضيّتان
 * في الشهر الماضي لا تصلحان أساساً لنسبة. والشارةُ على بطاقة الإجمالي
 * كانت تحمل نسبةَ الجديد لا نموَّ الإجمالي.
 *
 * ═══ القاعدة ═══
 *
 * - نسبةٌ لا تُحسب على أساسٍ دون خمسة: تُقال بالعدد «مقابل 2 الشهر السابق».
 * - نموُّ الإجمالي = الجديدُ على ما كان قبله، لا على جديدِ الشهر الماضي.
 */
final class Trend
{
    /** دون هذا الأساس لا نسبة — تُقال بالعدد. */
    public const MIN_BASE = 5;

    /** نسبةُ التغيّر بين شهرين — null حين يكون الأساسُ أصغرَ من أن يُقاس به. */
    public static function percent(int $now, int $prev): ?int
    {
        if ($prev < self::MIN_BASE) {
            return null;
        }

        return (int) round((($now - $prev) / $prev) * 100);
    }

    /** نموُّ الإجمالي هذا الشهر: الجديدُ على الرصيد الذي سبقه. */
    public static function growthOfTotal(int $total, int $new): ?int
    {
        $before = $total - $new;

        if ($before < self::MIN_BASE || $new <= 0) {
            return null;
        }

        return (int) round(($new / $before) * 100);
    }

    /** نصُّ المقارنة كما يُقرأ: نسبةٌ حين تصحّ، وإلا العدد. */
    public static function label(int $now, int $prev): string
    {
        $percent = self::percent($now, $prev);

        if ($percent !== null) {
            return ($percent >= 0 ? '+' : '') . $percent . '٪ ' . __('app.from_last_month');
        }

        return __('app.vs_last_month_count', ['n' => $prev]);
    }
}
