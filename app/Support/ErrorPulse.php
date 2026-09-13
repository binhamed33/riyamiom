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
     * @return array{count:int,last_type:?string,last_route:?string,last_at:?string,last_origin?:?string}
     */
    public static function summary(?Carbon $since = null): array
    {
        $since ??= now()->subDay();
        $empty = ['count' => 0, 'last_type' => null, 'last_route' => null, 'last_at' => null];

        $count = 0;
        $last = null;

        foreach (self::errorLines($since) as [$at, $line, $trace]) {
            $count++;
            $last = ['at' => $at, 'line' => $line, 'trace' => $trace];
        }

        if ($last === null) {
            return $empty;
        }

        return [
            'count' => $count,
            'last_type' => self::exceptionType($last['line']),
            'last_route' => self::route($last['line']),
            // ═══ الوقتُ بمنطقته لا عارياً ═══
            //
            // السجلُّ يكتب الساعةَ بتوقيت المكتب (مسقط)، وكانت تُرسل نصّاً
            // بلا منطقة، فتقرؤها اللوحةُ — وهي على UTC — على أنّها UTC:
            // أربعُ ساعاتٍ إلى الأمام، فتقول رسالةُ ديسكورد «آخرها بعد ٣
            // ساعات من الآن» عن خطأٍ وقع قبل ساعة.
            'last_at' => Carbon::parse($last['at'], config('app.timezone'))->toIso8601String(),
            // والموضعُ (الملفُّ والسطر) يجيب «ما الخطأ؟» في القناة نفسِها —
            // بلا نصّ الخطأ، فلا يخرج بيانُ موكّل (§56)
            'last_origin' => self::origin($last['line'], $last['trace']),
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
     * @return list<array{count:int,type:string,detail:?string,route:?string,origin:?string,last_at:string}>
     */
    public static function breakdown(?Carbon $since = null, int $limit = 15): array
    {
        $since ??= now()->subDay();
        $groups = [];

        foreach (self::errorLines($since) as [$at, $line, $trace]) {
            $type = self::exceptionType($line);
            $route = self::route($line);
            $origin = self::origin($line, $trace);
            $detail = self::detail($line);

            $key = $type . '|' . ($route ?? '') . '|' . ($origin ?? '') . '|' . ($detail ?? '');

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'count' => 0,
                    'type' => $type,
                    'detail' => $detail,
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
     * @return list<array{0:string,1:string,2:string}> الوقتُ، سطرُ الخطأ، وإطاراتُ التتبّع التي تليه
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

        // ═══ الخطأُ سطرٌ وما بعده تتبّع ═══
        //
        // لارافل يكتب الاستثناءَ على سطرٍ ثمّ [stacktrace] وإطاراتِه على
        // أسطرٍ تليه: ‎#0 /…/vendor/…/Connection.php(825)‎ ثمّ ‎#5 /…/app/…‎.
        // وسطرُ الخطأ نفسُه يشير إلى موضع الرمي في vendor لا إلى شفرتنا،
        // فمن قرأ السطرَ وحده رأى «—» في الموضع. الإطارُ الأوّل تحت app/
        // هو الجواب، وهو في الأسطر التالية.
        $rows = [];
        $open = null;

        foreach (explode("\n", (string) $tail) as $line) {
            $isEntry = preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m) === 1;

            if ($isEntry) {
                $open = null;

                if (!str_contains($line, '.ERROR:') || !Carbon::parse($m[1])->gte($since)) {
                    continue;
                }

                $rows[] = [$m[1], $line, ''];
                $open = count($rows) - 1;
                continue;
            }

            // إطارُ تتبّعٍ يتبع آخرَ خطأٍ مفتوح — يُرفق به (بحدٍّ، فالتتبّع طويل)
            if ($open !== null && str_starts_with($line, '#') && strlen($rows[$open][2]) < 6000) {
                $rows[$open][2] .= $line . "\n";
            }
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
    private static function origin(string $line, string $trace = ''): ?string
    {
        $clean = str_replace('\\/', '/', $line);

        // موضعُ الرمي إن كان في شفرتنا أصلاً
        if (preg_match('#(/(?:app|routes|database|resources|config)/[A-Za-z0-9_/.\-]+\.php)[^0-9]{0,3}(\d+)#', $clean, $m)) {
            return ltrim($m[1], '/') . ':' . $m[2];
        }

        // وإلّا فأوّلُ إطارٍ في التتبّع تحت شفرتنا: الاستثناءُ يُرمى في
        // vendor (Connection.php) والسببُ في app/…
        //
        // وإطاراتُ vendor تُتخطّى بالاسم لا بالنمط وحده: حزمةٌ في vendor
        // قد يكون في مسارها ‎/app/‎ أو ‎/resources/‎ (‎vendor/x/resources/views‎)
        // فيُقرأ إطارُها موضعاً لنا ويُفتح ملفٌ لا علاقة له بالعطب.
        foreach (explode("\n", $trace) as $frame) {
            if (!str_starts_with($frame, '#') || str_contains($frame, '/vendor/')) {
                continue;
            }

            if (preg_match('#(/(?:app|routes|database|resources|config)/[A-Za-z0-9_/.\-]+\.php)\((\d+)\)#', $frame, $m)) {
                return ltrim($m[1], '/') . ':' . $m[2];
            }
        }

        return null;
    }

    /**
     * صنفُ خطأ قاعدة البيانات بلا نصّه: «Column not found (1054)».
     *
     * ‏SQLSTATE ورمزُ المحرّك واسمُ الصنف ثوابتُ في المحرّك لا بياناتٌ من
     * الصفوف — فتُرسَل. أمّا ما بعدها («Unknown column 'x' … bindings
     * [أحمد الريامي]») فقد يحمل اسمَ موكّل، فلا يُقرأ.
     */
    private static function detail(string $line): ?string
    {
        if (preg_match('/SQLSTATE\[([0-9A-Z]{5})\]: ([A-Za-z][A-Za-z \/]{2,40}?): (\d{3,5})\b/', $line, $m)) {
            return trim($m[2]) . ' (' . $m[3] . ')';
        }

        // خطأُ الاتّصال نفسِه يأتي بشكلٍ آخر: ‎SQLSTATE[HY000] [2002] Connection
        // refused‎ أو ‎[1045] Access denied for user 'x'@'host'‎ — والرمزُ
        // يكفي (2002 = الخادم لا يردّ، 1045 = كلمةُ المرور)، وما بعده
        // يحمل اسمَ مستخدم القاعدة فلا يُطبَع.
        if (preg_match('/SQLSTATE\[HY000\] \[(\d{4})\]/', $line, $m)) {
            return 'Connection (' . $m[1] . ')';
        }

        return null;
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
