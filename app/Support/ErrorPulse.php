<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * نبض الأخطاء: كم خطأً وقع في المكتب اليوم، ومن أي نوع وعلى أي مسار.
 *
 * ═══ ما يُرسَل وما لا يُرسَل ═══
 *
 * يُرسَل إلى اللوحة العددُ ونوعُ الاستثناء والمسارُ والوقت — لا نصّ
 * الخطأ. لأنّ نصّ خطأ قاعدة البيانات قد يحمل اسم موكّل أو رقم قضية
 * («Duplicate entry 'أحمد الريامي'»)، وبيانات المكتب لا تغادر خادمه.
 * النوع والمسار يكفيان ليعرف المشرف أنّ شيئاً يتعطّل وأين — والتفصيل
 * يبقى في سجلّ المكتب لمن يملك الدخول إليه.
 */
class ErrorPulse
{
    /** لا يُقرأ إلا ذيل السجلّ: قد يبلغ عشرات الميغابايت. */
    private const TAIL_BYTES = 2_000_000;

    /**
     * @return array{count:int,last_type:?string,last_route:?string,last_at:?string}
     */
    public static function summary(?Carbon $since = null): array
    {
        $since ??= now()->subDay();
        $empty = ['count' => 0, 'last_type' => null, 'last_route' => null, 'last_at' => null];

        $log = storage_path('logs/laravel.log');

        if (!is_file($log) || !is_readable($log)) {
            return $empty;
        }

        try {
            $handle = fopen($log, 'r');

            if ($handle === false) {
                return $empty;
            }

            fseek($handle, max(0, filesize($log) - self::TAIL_BYTES));
            $tail = stream_get_contents($handle);
            fclose($handle);
        } catch (\Throwable) {
            // سجلٌّ متعذّر القراءة لا يُسقط النبضة — غيابُ الخبر ليس خطأً
            return $empty;
        }

        $count = 0;
        $last = null;

        foreach (explode("\n", (string) $tail) as $line) {
            if (!str_contains($line, '.ERROR:')) {
                continue;
            }

            if (!preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
                continue;
            }

            if (!Carbon::parse($m[1])->gte($since)) {
                continue;
            }

            $count++;
            $last = ['at' => $m[1], 'line' => $line];
        }

        if ($last === null) {
            return $empty;
        }

        return [
            'count' => $count,
            'last_type' => self::exceptionType($last['line']),
            'last_route' => self::route($last['line']),
            'last_at' => $last['at'],
        ];
    }

    /**
     * الأخطاءُ مجموعةً بنوعها وموضعها — لتُقرأ وتُصلَح.
     *
     * ═══ لماذا يُقرأ السجلُّ هكذا لا بالعين ═══
     *
     * ‏«ما أريد ولا خطأ» يبدأ بمعرفة ما الأخطاء. وفتحُ laravel.log
     * بالعين يعطي أسطراً بمقدار عشرات الميغابايت، فيها الخطأُ الواحد
     * مكرَّراً ألفَ مرّة، ونصوصُ الأخطاء تحمل ما تحمل.
     *
     * وهذا يجمعها: نوعُ الاستثناء × المسار × الملفّ والسطر، وكم مرّة.
     * فخمسةَ عشرَ سطراً تقول ما تقوله عشرةُ آلاف.
     *
     * ═══ وما لا يُطبَع ═══
     *
     * نصُّ الخطأ. فرسالةُ خطأ قاعدة البيانات تحمل ما في الصفّ:
     * ‏«Duplicate entry 'أحمد الريامي' for key 'clients_phone'». والنوعُ
     * والموضعُ يكفيان للإصلاح — والتفصيلُ يبقى في الخادم لمن يملكه.
     *
     * @return list<array{count:int,type:string,route:?string,origin:?string,last_at:string}>
     */
    public static function breakdown(?Carbon $since = null, int $limit = 15): array
    {
        $since ??= now()->subDay();
        $groups = [];

        foreach (self::errorLines($since) as [$at, $line]) {
            $type = self::exceptionType($line);
            $route = self::route($line);
            $origin = self::origin($line);

            $key = $type . '|' . ($route ?? '') . '|' . ($origin ?? '');

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'count' => 0,
                    'type' => $type,
                    'route' => $route,
                    'origin' => $origin,
                    'last_at' => $at,
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['last_at'] = $at;
        }

        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice(array_values($groups), 0, max(1, $limit));
    }

    /**
     * أسطرُ الخطأ في النافذة — مصدرٌ واحدٌ للقراءة يستعمله الملخّصُ
     * والتفصيل، فلا تفترق قراءتان عن سجلٍّ واحد.
     *
     * @return list<array{0:string,1:string}>
     */
    private static function errorLines(Carbon $since): array
    {
        $log = storage_path('logs/laravel.log');

        if (!is_file($log) || !is_readable($log)) {
            return [];
        }

        try {
            $handle = fopen($log, 'r');

            if ($handle === false) {
                return [];
            }

            fseek($handle, max(0, filesize($log) - self::TAIL_BYTES));
            $tail = stream_get_contents($handle);
            fclose($handle);
        } catch (\Throwable) {
            return [];
        }

        $rows = [];

        foreach (explode("\n", (string) $tail) as $line) {
            if (!str_contains($line, '.ERROR:')) {
                continue;
            }

            if (!preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
                continue;
            }

            if (!Carbon::parse($m[1])->gte($since)) {
                continue;
            }

            $rows[] = [$m[1], $line];
        }

        return $rows;
    }

    /**
     * الملفُّ والسطرُ اللذان رُمي منهما — وهو ما يُفتح لإصلاحه.
     *
     * والمسارُ في سياق لارافل مُهرَّبٌ بـJSON (‎\/home\/...‎)، فتُزال
     * الشُّرَطُ المائلة العكسيّة قبل المطابقة وإلّا لم يُطابَق شيء.
     * ويُقصّ إلى ما بعد جذر التطبيق: ‎/home/riyami/htdocs/office.riyami.om/‎
     * لا يفيد قارئاً، و‎app/Http/Controllers/CaseController.php:412‎ يفيد.
     */
    private static function origin(string $line): ?string
    {
        $clean = str_replace('\\/', '/', $line);

        if (!preg_match('#(/(?:app|routes|database|resources|config)/[A-Za-z0-9_/.\-]+\.php)[^0-9]{0,3}(\d+)#', $clean, $m)) {
            return null;
        }

        return ltrim($m[1], '/') . ':' . $m[2];
    }

    /** اسم صنف الاستثناء وحده — لا رسالته. */
    private static function exceptionType(string $line): string
    {
        if (preg_match('/"exception":"\[object\] \(([A-Za-z0-9_\\\\]+)/', $line, $m)) {
            return self::shortClass($m[1]);
        }

        if (preg_match('/([A-Za-z][A-Za-z0-9_]*\\\\[A-Za-z0-9_\\\\]*(?:Exception|Error))/', $line, $m)) {
            return self::shortClass($m[1]);
        }

        return 'Error';
    }

    private static function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return mb_substr((string) end($parts), 0, 60);
    }

    /**
     * المسار إن ذُكر في السطر — يدلّ على الشاشة المتعطّلة.
     *
     * النقطة مسموحة داخل المسار (‎/file.pdf‎) وممنوعة في آخره: جملة
     * «for route /cases/9.» كانت تُقرأ ‎/cases/9.‎ فيُعرض في اللوحة
     * مسارٌ لا وجود له.
     */
    private static function route(string $line): ?string
    {
        foreach ([
            '#\b(?:GET|POST|PUT|PATCH|DELETE)\s+(/[A-Za-z0-9/_\-\.]{0,60})#',
            '#for route (/[A-Za-z0-9/_\-\.]{0,60})#',
        ] as $pattern) {
            if (preg_match($pattern, $line, $m)) {
                $route = rtrim($m[1], '.');

                return $route !== '' ? $route : null;
            }
        }

        return null;
    }
}
