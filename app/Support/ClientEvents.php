<?php

namespace App\Support;

use App\Models\Setting;

/**
 * ما الذي يستحقّ إشعارَ الموكّل — وأين يذهب به الرابط.
 *
 * ═══ لماذا جدولٌ واحد ═══
 *
 * لأنّ لكلّ نوعِ حدثٍ أربعةَ قراراتٍ متفرّقة: أيُرسَل؟ وبأيّ نصّ؟
 * وإلى أيّ صفحةٍ في البوابة؟ وهل يملك المكتب إطفاءه؟ وتفريقُها على
 * مراقبٍ ومهمّةٍ وقالبٍ وشاشةِ إعداداتٍ يعني أنّ إضافة نوعٍ خامس
 * تُنسى في ثلاثة مواضع — فيصل الموكّلَ إشعارٌ لا يستطيع المكتب
 * إيقافه، أو يُطفأ نوعٌ ويظلّ يُرسَل.
 *
 * ═══ ما يُؤشَّر افتراضاً وما يُترك ═══
 *
 * سبعةٌ تخصّ **سيرَ القضية**: فتحُها، وتغيّرُ حالتها، وتحديثُها،
 * والجلسةُ وتأجيلُها وتذكيرُها، والفاتورة. هذه ما يسأل عنه الموكّل
 * المكتبَ هاتفياً كلَّ أسبوع، وإخبارُه بها هو الغرضُ من المنظومة
 * أصلاً.
 *
 * وثلاثةٌ تُترك للمكتب: المستندُ الجديد (قد يُرفع عشرةٌ في يومٍ
 * واحد فتغرق رسائلُه)، وتسجيلُ الدفعة (خبرٌ ماليٌّ يُساء فهمه بلا
 * سياق)، والإشعارُ العام (لا يُطلق تلقائياً أصلاً).
 *
 * ولا شيءَ من هذه يعمل قبل المفتاح الرئيسي: الافتراضاتُ هنا تقول ما
 * يُؤشَّر حين يشغّله المكتب، لا ما يُرسَل قبل أن يشغّله.
 */
class ClientEvents
{
    public const CASE_CREATED = 'case_created';
    public const CASE_STATUS = 'case_status';
    public const CASE_UPDATE = 'case_update';
    public const SESSION_NEW = 'session_new';
    public const SESSION_MOVED = 'session_moved';
    public const SESSION_REMINDER = 'session_reminder';
    public const DOCUMENT_NEW = 'document_new';
    public const INVOICE_NEW = 'invoice_new';
    public const PAYMENT_NEW = 'payment_new';
    public const ANNOUNCEMENT = 'announcement';

    /** بادئةُ مفتاح الإعداد — إعدادٌ لكلّ نوع، يملكه المكتب. */
    public const SETTING_PREFIX = 'cn_evt_';

    /**
     * المفتاحُ الرئيسي — مطفأٌ حتى يشغّله المكتب بيده.
     *
     * ═══ لماذا مطفأ ═══
     *
     * لأنّ تشغيلَه افتراضياً يعني أنّ كلَّ مكتبٍ يُحدَّث يبدأ في نفس
     * اللحظة بمراسلة موكّليه — بلا أن يطلب، وبلا أن يعلم. ومكتبُ
     * محاماةٍ يراسل موكّليه قرارٌ مهنيٌّ ومالي: كلُّ رسالةٍ تُحاسَب،
     * وكلُّ رسالةٍ غير متوقّعة قد تُبلَّغ فيهبط تقييمُ رقم المكتب.
     *
     * فالمفتاحُ الرئيسيُّ مطفأ، وحالتُه ظاهرةٌ في الشاشة كما هي — لا
     * خاناتٌ مؤشَّرة على ميزةٍ لا تعمل.
     */
    public const KEY_MASTER = 'cn_enabled';

    public const GROUP = 'client_notifications';

    /**
     * الأنواعُ بترتيب عرضها، ولكلٍّ عنوانُه ووجهتُه وحالتُه الافتراضية.
     *
     * @return array<string, array{label: string, hint: string, target: string, default: bool}>
     */
    public static function catalogue(): array
    {
        return [
            self::CASE_CREATED => [
                'label' => 'قضية جديدة',
                'hint' => 'حين تُفتح للموكّل قضيةٌ في المكتب',
                'target' => 'case',
                'default' => true,
            ],
            self::CASE_STATUS => [
                'label' => 'تغيّر حالة القضية',
                'hint' => 'من «جارية» إلى «محكومة» مثلاً',
                'target' => 'case',
                'default' => true,
            ],
            self::CASE_UPDATE => [
                'label' => 'تحديث على القضية',
                'hint' => 'ما يعلّمه المحامي «مرئياً للموكّل» وحده',
                'target' => 'timeline',
                'default' => true,
            ],
            self::SESSION_NEW => [
                'label' => 'جلسة جديدة',
                'hint' => 'عند إضافة موعد جلسة',
                'target' => 'sessions',
                'default' => true,
            ],
            self::SESSION_MOVED => [
                'label' => 'تغيّر موعد جلسة',
                'hint' => 'تأجيلٌ أو تقديم',
                'target' => 'sessions',
                'default' => true,
            ],
            self::SESSION_REMINDER => [
                'label' => 'تذكير قبل الجلسة',
                'hint' => 'قبل الموعد بالمدّة المضبوطة',
                'target' => 'sessions',
                'default' => true,
            ],
            self::DOCUMENT_NEW => [
                'label' => 'مستند جديد',
                'hint' => 'ما عُلّم «مرئياً للموكّل» وحده',
                'target' => 'documents',
                'default' => false,
            ],
            self::INVOICE_NEW => [
                'label' => 'فاتورة جديدة',
                'hint' => 'الفواتير المعلَّمة مرئيةً للموكّل',
                'target' => 'billing',
                'default' => true,
            ],
            self::PAYMENT_NEW => [
                'label' => 'تسجيل دفعة',
                'hint' => 'عند قيد دفعةٍ على حساب الموكّل',
                'target' => 'billing',
                'default' => false,
            ],
            self::ANNOUNCEMENT => [
                'label' => 'إشعار عام',
                'hint' => 'ما يكتبه المكتب يدوياً',
                'target' => 'home',
                'default' => false,
            ],
        ];
    }

    /** هل شغّل المكتبُ إشعاراتِ الموكّل أصلاً؟ */
    public static function masterEnabled(): bool
    {
        try {
            return Setting::get(self::KEY_MASTER, '0') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    public static function setMasterEnabled(bool $on): void
    {
        Setting::set(self::KEY_MASTER, $on ? '1' : '0', self::GROUP);
    }

    /** هل شغّل المكتبُ هذا النوع؟ */
    public static function enabled(string $type): bool
    {
        if (!self::masterEnabled()) {
            return false;
        }

        $entry = self::catalogue()[$type] ?? null;

        if ($entry === null) {
            return false;
        }

        try {
            $value = Setting::get(self::SETTING_PREFIX . $type);
        } catch (\Throwable) {
            // جدولٌ غير مهاجَر بعد: يُعمل بالافتراض ولا تسقط الصفحة
            return (bool) $entry['default'];
        }

        // ‏null تعني «لم يقرّر المكتب بعد» — فيُعمل بالافتراض. وذلك
        // يختلف عن «0» التي هي إطفاءٌ صريحٌ يُحترَم.
        return $value === null || $value === ''
            ? (bool) $entry['default']
            : $value === '1';
    }

    public static function setEnabled(string $type, bool $on): void
    {
        if (!isset(self::catalogue()[$type])) {
            return;
        }

        Setting::set(self::SETTING_PREFIX . $type, $on ? '1' : '0', self::GROUP);
    }

    public static function label(string $type): string
    {
        return self::catalogue()[$type]['label'] ?? $type;
    }

    public static function target(string $type): string
    {
        return self::catalogue()[$type]['target'] ?? 'case';
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return array_keys(self::catalogue());
    }
}
