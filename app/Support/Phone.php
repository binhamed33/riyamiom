<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * رقمُ هاتفٍ دوليّ — بمرجعِ Google لا بجدولٍ مكتوبٍ بخطّ اليد.
 *
 * ═══ ما كان ═══
 *
 * ‏GulfPhone تعرف ستَّ دولٍ خليجيّةٍ وأطوالَها. وموكّلٌ سعوديٌّ حقيقيّ
 * كُتب رقمُه فرُفض بـ«رقم هاتف غير صحيح» — والرقمُ صحيح، والعطبُ أنّ
 * الجدولَ لا يعرف إلا ستّاً، ولا يفرّق بين خانةٍ زائدةٍ وصفرٍ محلّيّ.
 *
 * ومكتبُ المحاماة يخدم موكّلين من كلّ مكان: خصمٌ في الهند، وشركةٌ في
 * لندن، وموكّلٌ سعوديٌّ يكتب رقمَه بصفره المحلّيّ. وجدولٌ بخطّ اليد
 * لمئتين وخمسٍ وأربعين دولةً لا يُكتب صحيحاً ولا يبقى صحيحاً.
 *
 * ═══ فالمرجعُ libphonenumber ═══
 *
 * مكتبةُ Google نفسِها: تعرف لكلّ دولةٍ أطوالَ أرقامها وبادئاتِها
 * وصيغَها، وتُحدَّث مع تغيّر خطط الترقيم. فـ«+966 5 5200 0531» صحيح،
 * و«+966 55 5200 0531» بخانةٍ زائدةٍ مرفوض — وهو الفرقُ الذي لم يكن
 * الجدولُ القديم يراه.
 *
 * والتخزينُ بصيغة E.164 دائماً (‎+966552000531): صيغةٌ واحدةٌ لا تلتبس،
 * يقبلها واتساب والرسائلُ والاتّصال. والعرضُ يُجمَّل عند العرض وحدَه.
 */
class Phone
{
    /** الدولةُ الافتراضيّة — مكاتبُنا في عُمان. */
    public const DEFAULT_REGION = 'OM';

    /** الدولُ المقدَّمة في أوّل القائمة: أكثرُ ما يُدخَل في مكاتبنا. */
    public const PINNED = ['OM', 'AE', 'SA', 'QA', 'KW', 'BH', 'YE', 'EG', 'JO', 'IN', 'PK', 'GB', 'US'];

    /**
     * ما لا يُعرض ولا يُقبل.
     *
     * النظامُ يخدم مكاتبَ محاماةٍ عُمانيّة، وسلطنةُ عُمان لا تعترف
     * بالكيان المحتلّ ولا تقيم معه علاقات، والتعاملُ معه ممنوعٌ قانوناً.
     * فمفتاحُه لا يُعرض في منتقي الدول ولا يُقبل رقمٌ به.
     *
     * ولا يكفي حذفُه من القائمة: من لصق رقماً بمفتاحه في الحقل مباشرةً
     * كان يمرّ من الخادم لأنّ المكتبةَ تعرفه. فالمنعُ في ‎resolve‎ —
     * المعبرِ الذي تمرّ منه كلُّ قراءةٍ وكلُّ تحقّق — لا في العرض وحده.
     *
     * والأراضي الفلسطينيّة (PS، ‎+970‎) في القائمة كما هي.
     */
    public const EXCLUDED = ['IL'];

    /** حقولُ صفِّ الدولة — تُشتقّ منها بصمةُ الذاكرة، فلا تُقرأ نسخةٌ قديمة. */
    private const ROW_SHAPE = ['iso', 'name', 'dial', 'flag', 'example', 'lengths', 'max', 'main', 'q'];

    /** أتُقبل هذه الدولةُ في النظام أصلاً؟ */
    public static function supports(?string $region): bool
    {
        return $region !== null
            && $region !== ''
            && !in_array(strtoupper($region), self::EXCLUDED, true);
    }

    private static ?PhoneNumberUtil $util = null;

    private static function util(): PhoneNumberUtil
    {
        return self::$util ??= PhoneNumberUtil::getInstance();
    }

    /**
     * يحلّل الرقمَ ويعيد كائنَه — أو null إن لم يُفهم.
     *
     * ‏$region يُستعمل حين لا يحمل النصُّ مفتاحاً دولياً. ورقمٌ يبدأ
     * بـ+ أو 00 يحمل مفتاحَه فيُقرأ منه ويُهمَل ما مُرّر.
     */
    public static function parse(?string $raw, ?string $region = null): ?\libphonenumber\PhoneNumber
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        // ‏«00» بادئةُ الاتّصال الدوليّ في أكثر العالم، وlibphonenumber
        // يفهمها من الدولة لا من النصّ. فتُحوَّل إلى «+» الصريحة.
        if (str_starts_with($raw, '00')) {
            $raw = '+' . substr($raw, 2);
        }

        try {
            return self::util()->parse($raw, $region ?: self::DEFAULT_REGION);
        } catch (NumberParseException) {
            return null;
        }
    }

    /** أرقامٌ فقط — تُزال المسافاتُ والشُّرَط والأقواسُ و«+» و«00». */
    public static function digits(?string $raw): string
    {
        $d = preg_replace('/\D+/', '', (string) $raw) ?? '';

        return str_starts_with($d, '00') ? substr($d, 2) : $d;
    }

    /**
     * الرقمُ صحيحاً كائناً — أو null.
     *
     * ═══ ولماذا محاولتان ═══
     *
     * كثيرٌ من أرقام النظام محفوظةٌ بمفتاحها بلا «+»: معرّفُ واتساب
     * ‏‎96891234567‎، وما نسخه الموظّف من محادثة، وكلُّ ما حُفظ قبل
     * المنتقي. وهذه بلا علامةٍ تُقرأ أرقاماً محلّيّةً طويلةً فتُردّ
     * وهي صحيحة — وهو عطبُ الجدول القديم نفسُه بثوبٍ آخر.
     *
     * فتُجرَّب «+» بعد أن تخيب القراءةُ المباشرة. والشرطُ **الصحّةُ
     * الكاملة** لا مجرّدُ الفهم: ‎5552000531‎ السعوديُّ بخانةٍ زائدةٍ
     * يُفهم بـ«+55» رقماً برازيلياً — ولا يصحّ، فيبقى مردوداً.
     *
     * ═══ ولا تُجرَّب حين تُسمَّى الدولة ═══
     *
     * ‏«912345678901» بلا دولةٍ يُقرأ رقماً هندياً ثابتاً — وهو قراءةٌ
     * صحيحةٌ لمن كتبه هكذا. لكن من اختار «عُمان» في المنتقي ثمّ كتبه
     * إنّما أخطأ في رقمٍ عُمانيّ، فقبولُه رقماً هندياً حفظُ رقمٍ لا
     * يقصده أحد. فالدولةُ المُسمّاةُ تُصدَّق ولا يُخمَّن فوقها، والدولةُ
     * الغائبةُ وحدَها تُخمَّن.
     */
    private static function resolve(?string $raw, ?string $region = null): ?\libphonenumber\PhoneNumber
    {
        if (!self::supports($region ?? self::DEFAULT_REGION)) {
            return null;
        }

        $number = self::parse($raw, $region);
        $valid = $number !== null && self::util()->isValidNumber($number);

        if ($valid || $region !== null) {
            return $valid && self::supported($number) ? $number : null;
        }

        $bare = self::parse('+' . self::digits($raw));

        return $bare !== null && self::util()->isValidNumber($bare) && self::supported($bare)
            ? $bare
            : null;
    }

    /** رقمٌ دولتُه مستثناة لا يُقرأ صحيحاً مهما صحّ بناؤه. */
    private static function supported(?\libphonenumber\PhoneNumber $number): bool
    {
        if ($number === null) {
            return false;
        }

        return self::supports(self::util()->getRegionCodeForNumber($number))
            && self::supports(self::util()->getRegionCodeForCountryCode($number->getCountryCode()));
    }

    /**
     * أرقامُ المشترك كما يكتبها هو — بأصفارها البادئة إن كانت منه.
     *
     * ═══ عطبٌ لا يظهر إلا في ثلاث دول ═══
     *
     * ‏getNationalNumber يعيد عدداً لا نصّاً، والعددُ لا يحمل صفراً
     * بادئاً. وأكثرُ الدنيا لا يضرّها ذلك: الصفرُ في «07400 123456»
     * بادئةُ اتّصالٍ محلّيّةٌ ليست من الرقم.
     *
     * لكنّ الكونغو وساحلَ العاج وبنين أرقامُها **تبدأ بصفرٍ هو منها**:
     * ‏«061234567» تسعةُ أرقامٍ أوّلُها صفر. فكان يخرج «61234567» —
     * ثمانيةٌ، ناقصُ خانةٍ — فيُعرض في الحقل رقمٌ لا يصحّ، ويُعدّ في
     * رسالة الخطأ عدّاً خاطئاً.
     *
     * والمكتبةُ تحفظ ذلك في علمٍ منفصل، وهذا ما يقرؤه.
     */
    private static function subscriber(\libphonenumber\PhoneNumber $number): string
    {
        $digits = (string) $number->getNationalNumber();

        if ($number->isItalianLeadingZero()) {
            $digits = str_repeat('0', max(1, (int) $number->getNumberOfLeadingZeros())) . $digits;
        }

        return $digits;
    }

    /** هل هو رقمٌ صحيحٌ في دولته فعلاً — طولاً وبادئةً؟ */
    public static function isValid(?string $raw, ?string $region = null): bool
    {
        return self::resolve($raw, $region) !== null;
    }

    /**
     * الصيغةُ المخزَّنة: E.164 بلا مسافاتٍ ولا رموز.
     *
     * يعيد النصَّ كما جاء إن تعذّر الفهم — فالتطبيعُ لا يُتلف ما لم
     * يفهمه، والتحقّقُ هو من يرفض.
     */
    public static function e164(?string $raw, ?string $region = null): ?string
    {
        if (trim((string) $raw) === '') {
            return null;
        }

        $number = self::resolve($raw, $region);

        return $number === null
            ? trim((string) $raw)
            : self::util()->format($number, PhoneNumberFormat::E164);
    }

    /** الصيغةُ المعروضة: ‎+966 55 200 0531 */
    public static function format(?string $raw, ?string $region = null): string
    {
        $number = self::resolve($raw, $region);

        return $number === null
            ? trim((string) $raw)
            : self::util()->format($number, PhoneNumberFormat::INTERNATIONAL);
    }

    /** رمزُ دولة الرقم (ISO2) — أو الافتراضيّةُ إن لم يُفهم. */
    public static function region(?string $raw, ?string $fallback = null): string
    {
        $fallback = $fallback ?: self::DEFAULT_REGION;

        // بلا دولةٍ مُسمّاة: هذا سؤالٌ عن دولة الرقم لا تصديقٌ لدولةٍ
        // قالها أحد، فيُقرأ المفتاحُ من الرقم نفسِه إن حمله
        $number = self::resolve($raw);

        if ($number !== null) {
            return self::util()->getRegionCodeForNumber($number)
                ?: (self::util()->getRegionCodeForCountryCode($number->getCountryCode()) ?: $fallback);
        }

        // رقمٌ ناقصٌ ما زال يُكتب: لا يصحّ بعد، لكنّ مفتاحَه يقول دولتَه.
        // والمنتقي يجب أن يُظهر العلمَ الصحيحَ أثناء الكتابة لا أن يقفز
        // إلى عُمان عند كلّ حرف.
        $partial = self::parse($raw, $fallback);

        if ($partial === null) {
            return $fallback;
        }

        $partialRegion = self::util()->getRegionCodeForNumber($partial)
            ?: (self::util()->getRegionCodeForCountryCode($partial->getCountryCode()) ?: $fallback);

        // ولا تُرجَع مستثناةٌ ولو قالها المفتاح: المنتقي يبني اختيارَه
        // على هذه القيمة، فلو أعادت «IL» بحث عن خيارٍ لا وجود له
        return self::supports($partialRegion) ? $partialRegion : $fallback;
    }

    /**
     * الجزءُ المحلّيّ بلا مفتاح — لملء حقل الإدخال.
     *
     * ‏«00971506233112» ⇐ «506233112». وكان القصُّ بآخر ثمانيةٍ يقطع
     * الرقمَ من وسطه فيخرج «06233112» — شريحةٌ لا تقابل شيئاً في رقم
     * صاحبها. وهي ما يُعرض مقنَّعاً في بوّابة الموكّلين.
     */
    public static function national(?string $raw, ?string $region = null): string
    {
        $raw = trim((string) $raw);
        $number = self::resolve($raw, $region);

        if ($number !== null) {
            return self::subscriber($number);
        }

        // ═══ ما لم يُفهم يُترك كما هو ═══
        //
        // ‏«06234567» ليس رقماً صحيحاً في عُمان، لكنّ المكتبة تفهمه
        // وتُسقط صفرَه فيخرج «6234567» — فيُعرض للموكّل في بوّابته
        // رقمٌ ناقصُ خانةٍ عن الذي في ملفّه. فالقصُّ لا يقع إلا حين
        // يصحّ الرقم، أو حين يكتب صاحبُه المفتاحَ صراحةً بـ«+» أو «00».
        if (str_starts_with($raw, '+') || str_starts_with($raw, '00')) {
            $partial = self::parse($raw, $region);

            if ($partial !== null) {
                return self::subscriber($partial);
            }
        }

        return preg_replace('/\D+/', '', $raw) ?: '';
    }

    /** مفتاحُ الاتّصال لدولة (‎968) — أو لا شيء إن لم تُعرف. */
    public static function dialCode(string $region): ?int
    {
        $code = self::util()->getCountryCodeForRegion(strtoupper($region));

        return $code > 0 ? $code : null;
    }

    /**
     * علمُ الدولة من رمزها.
     *
     * ‏«SA» ⇐ 🇸🇦 — حرفان يُحوَّلان إلى رمزَي «مؤشّر إقليميّ» في
     * يونيكود، فيعرضهما النظامُ علماً. فلا صورةٌ تُحمَّل ولا حزمةُ
     * أعلامٍ تُصان.
     */
    public static function flag(string $region): string
    {
        $region = strtoupper($region);

        if (!preg_match('/^[A-Z]{2}$/', $region)) {
            return '🏳';
        }

        return mb_chr(0x1F1E6 + ord($region[0]) - 65, 'UTF-8')
            . mb_chr(0x1F1E6 + ord($region[1]) - 65, 'UTF-8');
    }

    /** اسمُ الدولة بلغة الواجهة. */
    public static function name(string $region, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-' . strtoupper($region), $locale);

            if ($name !== '' && $name !== strtoupper($region)) {
                return $name;
            }
        }

        return strtoupper($region);
    }

    /**
     * مثالٌ حيٌّ على رقمِ جوّالٍ في هذه الدولة.
     *
     * يُعرض في الحقل تلميحاً: «هكذا يُكتب هنا». وهو من المكتبة نفسها،
     * فلا مثالَ مكتوبٌ بخطّ اليد يشيخ.
     */
    public static function example(string $region): string
    {
        $region = strtoupper($region);

        // ═══ ولا يُعرض مثالٌ لو كُتب لرُدّ ═══
        //
        // ثلاثُ دولٍ (الكونغو، بنين، ساحل العاج) غيّرت خطط ترقيمها
        // وزادت خانةً، ومثالُها في البيانات بقي على القديم. فالحقلُ كان
        // سيعرض في تلميحه رقماً أقصرَ ممّا يقبله هو نفسُه — والموظّفُ
        // يكتب على مثاله فيُرَدّ ولا يفهم لماذا.
        foreach ([\libphonenumber\PhoneNumberType::MOBILE, null] as $type) {
            try {
                $number = $type === null
                    ? self::util()->getExampleNumber($region)
                    : self::util()->getExampleNumberForType($region, $type);
            } catch (\Throwable) {
                continue;
            }

            if ($number !== null && self::util()->isValidNumber($number)) {
                return self::subscriber($number);
            }
        }

        return '';
    }

    /**
     * قاعدةُ التحقّق — بديلُ GulfPhone::rule في كلّ متحكّم.
     *
     * ‏32 لا 20: ‎+998 71 123 45 67‎ مكتوباً بمسافاته يتجاوز العشرين،
     * فكان السقفُ القديم يردّه قبل أن يُقرأ.
     *
     * @return array<int, mixed>
     */
    public static function rule(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:32',
            new \App\Rules\PhoneNumber(),
        ];
    }

    /**
     * الأطوالُ التي يقبلها اشتراكُ شخصٍ في هذه الدولة.
     *
     * ‏«كم رقماً مسموحاً؟» جوابُها ليس واحداً لكلّ الدنيا: ثمانيةٌ في
     * عُمان، وتسعةٌ في السعوديّة، وعشرةٌ في الهند، وتسعٌ أو عشرٌ في
     * بريطانيا. وهي في بيانات المكتبة لكلّ دولةٍ على حِدة.
     *
     * والمجموعُ محمولٌ وثابت: صاحبُ الشركة يُعطي رقمَ مكتبه لا جوّاله،
     * فقصرُ القبول على المحمول يردّ رقماً صحيحاً.
     *
     * وحين تتّحد خطّتا المحمول والثابت في دولة (أمريكا مثلاً) تُترك
     * قائمتاهما فارغتين في البيانات ويُكتفى بالعامّة — فتُقرأ العامّة.
     *
     * @return list<int>
     */
    public static function lengths(string $region): array
    {
        $meta = self::util()->getMetadataForRegion(strtoupper($region));

        if ($meta === null) {
            return [];
        }

        $lengths = array_merge(
            $meta->getMobile()->getPossibleLength(),
            $meta->getFixedLine()->getPossibleLength(),
        );

        if ($lengths === []) {
            $lengths = $meta->getGeneralDesc()->getPossibleLength();
        }

        $lengths = array_values(array_unique(array_map('intval', $lengths)));
        sort($lengths);

        return $lengths;
    }

    /**
     * أقصى طولٍ يمكن أن يبلغه رقمٌ في هذه الدولة — أيّ نوعٍ كان.
     *
     * يُستعمل سقفاً للكتابة لا شرطاً للقبول: قصُّ الحقل عند طول المحمول
     * يمنع كتابةَ رقمٍ مجّانيٍّ أو خدميٍّ أطولَ منه وهو صحيح. فالسقفُ
     * من الوصف العامّ، والحكمُ بالصحّة يبقى لـisValid.
     */
    public static function maxLength(string $region): int
    {
        $meta = self::util()->getMetadataForRegion(strtoupper($region));

        $general = $meta ? $meta->getGeneralDesc()->getPossibleLength() : [];
        $all = array_merge($general, self::lengths($region));

        return $all === [] ? 15 : max(array_map('intval', $all));
    }

    /**
     * كلُّ الدول للقائمة — المقدَّمةُ أوّلاً ثمّ الباقي بالحروف.
     *
     * @return array<int, array{iso: string, name: string, dial: int, flag: string, example: string}>
     */
    public static function countries(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        // ═══ مفتاحُ الذاكرة يحمل شكلَ الصفّ ═══
        //
        // القائمةُ محفوظةٌ يوماً كاملاً. فحين يُضاف حقلٌ إلى الصفّ —
        // الأطوالُ مثلاً — يبقى المحفوظُ على شكله القديم، ويقرؤه القالبُ
        // الجديد فيسقط بـ«Undefined array key» في **كلّ نموذجٍ فيه هاتف**
        // حتى تنتهي مدّةُ الحفظ. ومسحُ الذاكرة عند النشر يُنسى مرّةً
        // فيقع العطبُ في مكتبٍ لا نراه.
        //
        // فالمفتاحُ يُشتقّ من أسماء الحقول نفسِها: من غيّرها غيّر المفتاح
        // معه ولو لم ينتبه، والقديمُ يُهمَل ولا يُقرأ.
        // والبصمةُ تشمل المستثنى كذلك: حذفُ دولةٍ يغيّر المخرَجَ ولا
        // يغيّر شكلَ الصفّ، فلولا ذلك بقيت المحذوفةُ معروضةً يوماً كاملاً
        // من ذاكرةٍ كُتبت قبل الحذف.
        $shape = substr(md5(implode(',', self::ROW_SHAPE) . '|' . implode(',', self::EXCLUDED)), 0, 8);

        return cache()->remember('phone.countries.' . $shape . '.' . $locale, 86400, function () use ($locale) {
            $rows = [];

            foreach (self::util()->getSupportedRegions() as $iso) {
                $dial = self::dialCode($iso);

                if ($dial === null || !self::supports($iso)) {
                    continue;
                }

                $name = self::name($iso, $locale);

                $rows[$iso] = [
                    'iso' => $iso,
                    'name' => $name,
                    'dial' => $dial,
                    'flag' => self::flag($iso),
                    'example' => self::example($iso),
                    'lengths' => self::lengths($iso),
                    'max' => self::maxLength($iso),
                    // الدولةُ صاحبةُ المفتاح حين يتقاسمه غيرُها: ‎+1‎
                    // لعشرين دولة، فمن لصق رقماً بمفتاحٍ مشترَك يُنقل
                    // إلى الأصل لا إلى أوّل ما صادفَ في الترتيب
                    'main' => self::util()->getRegionCodeForCountryCode($dial) === $iso,
                    // ما يُبحث فيه: بالعربيّة وبالإنجليزيّة وبالرمز
                    // وبالمفتاح — فمن كتب «saudi» أو «966» يجدها
                    'q' => mb_strtolower(implode(' ', array_unique([
                        $name,
                        self::name($iso, 'en'),
                        $iso,
                        (string) $dial,
                    ])), 'UTF-8'),
                ];
            }

            // الترتيبُ بحروف اللغة المعروضة لا بالإنجليزيّة
            $collator = class_exists(\Collator::class) ? new \Collator($locale) : null;

            uasort($rows, fn ($a, $b) => $collator
                ? $collator->compare($a['name'], $b['name'])
                : strcmp($a['name'], $b['name']));

            $pinned = [];

            foreach (self::PINNED as $iso) {
                if (isset($rows[$iso])) {
                    $pinned[] = $rows[$iso] + ['pinned' => true];
                    unset($rows[$iso]);
                }
            }

            return array_merge($pinned, array_values($rows));
        });
    }
}
