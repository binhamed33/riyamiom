<?php

namespace App\Support;

/**
 * العدد في العربية لا يُلحق بالمعدود كما في الإنجليزية.
 *
 * «منذ 6 يوماً» خطأ يقرأه المحامي فيظنّ النظام أعجميّاً. الصواب أن
 * الاثنين مثنّى، وما بين الثلاثة والعشرة جمعُ قِلّة، وما فوقها مفردٌ
 * منصوب. هذا الصنف يقول ذلك مرةً واحدة لكل موضع يحتاجه.
 */
class ArabicCount
{
    /**
     * @param  string  $one     يوم
     * @param  string  $two     يومان
     * @param  string  $few     أيام      (٣ إلى ١٠)
     * @param  string  $many    يوماً     (١١ فأكثر)
     */
    public static function of(int $n, string $one, string $two, string $few, string $many): string
    {
        $n = abs($n);

        return match (true) {
            $n === 0 => '0 ' . $many,
            $n === 1 => $one,
            $n === 2 => $two,
            $n <= 10 => $n . ' ' . $few,
            default  => $n . ' ' . $many,
        };
    }

    /** يوم · يومان · ٣ أيام · ١١ يوماً */
    public static function days(int $n): string
    {
        // «منذ 0 يوماً» لا تُقال. اليوم نفسه يُقال عنه «أقل من يوم».
        if ($n === 0) {
            return 'أقل من يوم';
        }

        return self::of($n, 'يوم واحد', 'يومان', 'أيام', 'يوماً');
    }
}
