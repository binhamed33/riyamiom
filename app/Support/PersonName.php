<?php

namespace App\Support;

/**
 * اسمٌ يُكتب في خانة اسم.
 *
 * كان الحقل «string|max:255» فيقبل وسماً برمجياً كاملاً. والتضييق
 * وحده لا يكفي: أول قاعدة كتبناها ردّت «موكّل» — لأن الشدّة علامةٌ
 * مركّبة لا حرف، فلا يشملها \p{L}. فالقاعدة تُدخل \p{M} معه، وإلّا
 * منعنا العربية المشكولة عن نظام عربي.
 */
class PersonName
{
    /** حروف وعلامات وأرقام ومسافات وما يتخلّل الأسماء من فواصل. */
    public const PATTERN = '/^[\p{L}\p{M}\p{N}\s\.\-_\(\)\/\'’،,&]+$/u';

    public static function isValid(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && preg_match(self::PATTERN, $value) === 1;
    }

    /** قاعدة تحقّق تُستعمل في كل متحكّم. */
    public static function rule(bool $required = true, int $max = 255): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:' . $max,
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! self::isValid((string) $value)) {
                    $fail('الاسم يُكتب حروفاً — بلا وسوم أو رموز برمجية.');
                }
            },
        ];
    }
}
