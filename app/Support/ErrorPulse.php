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
