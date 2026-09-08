<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رقمُ هاتفٍ صحيحٌ في دولته — لا في جدولٍ من ستّ دول.
 *
 * ═══ ما وقع ═══
 *
 * موكّلٌ سعوديٌّ كُتب رقمه ‎009665552000531‎ فرُدّ بـ«رقم هاتف غير صحيح».
 * والردُّ صحيحٌ صدفةً — فيه خانةٌ زائدة — لكنّ الرسالة لا تقول ذلك،
 * ولا تقول كم المطلوب. فيعيد الموظّفُ كتابتَه كما هو ثلاثاً ثمّ يترك
 * الحقلَ فارغاً ويمضي.
 *
 * وأسوأُ من ذلك: القاعدةُ القديمة كانت تردّ رقمَ الهند وبريطانيا
 * وأمريكا كلَّها — لأنّها لا تعرف إلا الخليج. ومكتبُ المحاماة يخاصم
 * شركاتٍ ويمثّل مقيمين.
 *
 * ═══ وما صار ═══
 *
 * الحكمُ من بيانات Google لكلّ دولةٍ من مئتين وخمسٍ وأربعين، والرسالةُ
 * تقول ثلاثةَ أشياء: أيُّ دولةٍ قيست، وكم رقماً تريد، وكم كتبتَ أنت.
 *
 * والدولةُ تُقرأ من الحقل المرافق ‎{الحقل}_country‎ الذي يبعثه المنتقي.
 * فإن غاب — نموذجٌ قديم، أو طلبٌ من غير المتصفّح — فمن الرقم نفسِه إن
 * حمل مفتاحاً، وإلا فعُمان.
 */
class PhoneNumber implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        if (!is_string($value)) {
            $fail('رقم الهاتف يُكتب نصّاً.');

            return;
        }

        // حروفٌ في حقل رقم: خطأُ لصقٍ لا خطأُ طول، فتُقال باسمها بدل
        // «غير صحيح» التي تترك الموظّف يخمّن ما العيب
        if (preg_match('/[A-Za-z\x{0600}-\x{06FF}]/u', $value)) {
            $fail('رقم الهاتف يُكتب أرقاماً فقط — بلا حروف.');

            return;
        }

        // الدولةُ المُسمّاةُ تُقاس بها وحدَها. والغائبةُ تُترك للمكتبة
        // تقرأ المفتاحَ من الرقم — فنموذجٌ قديمٌ أو استيرادٌ لا يحمل
        // مرافقاً يبقى عاملاً كما كان.
        $named = $this->namedRegion($attribute);

        if (Phone::isValid($value, $named)) {
            return;
        }

        $fail($this->explain($value, $named ?: Phone::region($value)));
    }

    /** الدولةُ التي سمّاها المنتقي — أو لا شيء إن لم يُرسَل. */
    private function namedRegion(string $attribute): ?string
    {
        // اسمُ الحقل قد يكون مفهرساً (‎contacts.2.phone‎) فيُؤخذ المرافقُ
        // بجواره لا في جذر النموذج
        $chosen = data_get($this->data, $attribute . '_country');

        if (is_string($chosen) && preg_match('/^[A-Za-z]{2}$/', $chosen)
            && Phone::dialCode($chosen) !== null) {
            return strtoupper($chosen);
        }

        return null;
    }

    /**
     * لماذا رُفض — بلغةٍ تُصلح الخطأ لا تُخبر بوقوعه.
     */
    private function explain(string $value, string $region): string
    {
        $country = Phone::name($region, 'ar');
        $lengths = Phone::lengths($region);

        // ما يُعدّ هو ما يعدّه الحقلُ نفسُه: الجزءُ المحلّيُّ بعد المفتاح،
        // بأصفاره البادئة إن كانت من الرقم. وعدُّ النصّ كلِّه كان يقول
        // «فيه ١٥» لرقمٍ مفتاحُه ثلاثةٌ وجسمُه اثنا عشر.
        $typed = strlen(Phone::national($value, $region));

        if ($lengths !== [] && !in_array($typed, $lengths, true)) {
            return 'رقم ' . $country . ' يُكتب بـ' . self::countWord($lengths)
                . ' — وهذا فيه ' . self::arabic((string) $typed) . '.';
        }

        $example = Phone::example($region);

        return 'رقم ' . $country . ' لا يبدأ هكذا'
            . ($example !== '' ? '. مثال: ' . $example : '') . '.';
    }

    /**
     * «٨ أرقام» أو «٩ أو ١٠ أرقام» أو «من ٥ إلى ١٥ رقماً».
     *
     * @param list<int> $lengths
     */
    private static function countWord(array $lengths): string
    {
        if (count($lengths) === 1) {
            return self::arabic((string) $lengths[0]) . ' أرقام';
        }

        // مدىً متّصلٌ طويلٌ (ألمانيا: من ٥ إلى ١٥) يُقال مدىً، وسردُ
        // أحدَ عشرَ رقماً في رسالة خطأ لا يُقرأ
        $isRange = $lengths[count($lengths) - 1] - $lengths[0] === count($lengths) - 1;

        if ($isRange && count($lengths) > 2) {
            return 'من ' . self::arabic((string) $lengths[0])
                . ' إلى ' . self::arabic((string) $lengths[count($lengths) - 1]) . ' رقماً';
        }

        $words = array_map(fn ($n) => self::arabic((string) $n), $lengths);
        $last = array_pop($words);

        return implode('، ', $words) . ' أو ' . $last . ' أرقام';
    }

    /** الأرقامُ العربيّةُ في نصٍّ عربيّ — والرقمُ نفسُه يبقى لاتينياً. */
    private static function arabic(string $digits): string
    {
        return strtr($digits, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }
}
