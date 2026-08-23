<?php

namespace App\Support;

/**
 * رقم هاتف خليجي.
 *
 * كان الحقل يقبل «string|max:255» — أي حروفاً وعلامات وأي طول. فيُحفظ
 * في سجلّ الموكّل رقمٌ لا يُتّصل به، ولا يُكتشف إلا حين يُحتاج.
 *
 * القاعدة: مفتاح الدولة اختياري — يُكتب أو يُترك. وإن كُتب فمن دول
 * الخليج وحدها، ويجب أن يطابق طولُ الرقم ما تعرفه تلك الدولة. وإن
 * تُرك فالطول المقبول هو أي طول خليجي معروف.
 */
class GulfPhone
{
    /** الدولة => [مفتاحها, طول الرقم المحلي بلا المفتاح] */
    public const COUNTRIES = [
        'عُمان'      => ['968', 8],
        'الإمارات'   => ['971', 9],
        'السعودية'   => ['966', 9],
        'قطر'        => ['974', 8],
        'الكويت'     => ['965', 8],
        'البحرين'    => ['973', 8],
    ];

    /** أرقام فقط — تُزال المسافات والشرطات والأقواس و«+» و«00». */
    public static function digits(?string $raw): string
    {
        $d = preg_replace('/\D+/', '', (string) $raw) ?? '';

        return str_starts_with($d, '00') ? substr($d, 2) : $d;
    }

    /** هل الرقم صالح — بمفتاح دولة خليجية أو بلا مفتاح؟ */
    public static function isValid(?string $raw): bool
    {
        return self::country($raw) !== null;
    }

    /**
     * اسم الدولة إن عُرفت، وإلّا null.
     * الرقم بلا مفتاح يُقبل إن طابق طولاً خليجياً معروفاً.
     */
    public static function country(?string $raw): ?string
    {
        $d = self::digits($raw);

        if ($d === '') {
            return null;
        }

        foreach (self::COUNTRIES as $name => [$code, $len]) {
            if (str_starts_with($d, $code) && strlen($d) === strlen($code) + $len) {
                return $name;
            }
        }

        // بلا مفتاح: أي طول محلي خليجي معروف. والصفر الأول شائع في
        // الكتابة المحلية (0501234567) فيُقشَّر قبل القياس.
        $local = str_starts_with($d, '0') ? substr($d, 1) : $d;
        $lengths = array_unique(array_map(fn ($c) => $c[1], array_values(self::COUNTRIES)));

        return in_array(strlen($local), $lengths, true) ? 'محلي' : null;
    }

    /** الصيغة المعروضة: +968 9123 4567 */
    public static function format(?string $raw): string
    {
        $d = self::digits($raw);

        if ($d === '') {
            return '';
        }

        foreach (self::COUNTRIES as [$code, $len]) {
            if (str_starts_with($d, $code) && strlen($d) === strlen($code) + $len) {
                $local = substr($d, strlen($code));

                return '+' . $code . ' ' . trim(chunk_split($local, 4, ' '));
            }
        }

        return trim(chunk_split($d, 4, ' '));
    }

    /** قاعدة تحقّق تُستعمل في كل متحكّم. */
    public static function rule(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (preg_match('/[A-Za-z\x{0600}-\x{06FF}]/u', (string) $value)) {
                    $fail('رقم الهاتف يُكتب أرقاماً فقط — بلا حروف.');

                    return;
                }

                if (!self::isValid($value)) {
                    $fail('رقم هاتف غير صحيح. اكتبه بلا مفتاح (٨ أرقام لعُمان) أو مع مفتاح دولة خليجية مثل +968.');
                }
            },
        ];
    }
}
