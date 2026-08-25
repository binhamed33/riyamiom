<?php

namespace App\Support;

use App\Models\Setting;

/**
 * هويّة المُرسِل في كل بريد يخرج من النظام — من مصدر واحد.
 *
 * ═══ لماذا لا يكون المُرسِل بريدَ المكتب ═══
 *
 * كان البريد يُرسَل بـ From = بريد المكتب المسجَّل في إعداداته. وهذا لا
 * يمشي مع Gmail: خادمُه لا يسمح لحسابٍ أن يرسل باسم عنوانٍ لا يملكه ولا
 * سجّله كعنوانٍ بديل موثَّق. فإمّا يرفض الرسالة، وإمّا يستبدل العنوان
 * بعنوان الحساب صامتاً — وفي الحالتين لا يصل ما قُصد.
 *
 * فالعنوان الحقيقي واحدٌ دائماً: بريد مُداوَلة المركزي من متغيّرات
 * البيئة. وهويّة المكتب تظهر حيث تُقبل ولا تُرفض:
 *
 *   - الاسم المعروض: اسم المكتب («شركة … للمحاماة») فيرى الموكّل مكتبه
 *     لا اسماً غريباً.
 *   - Reply-To: بريد المكتب، فالردّ يصل المكتب مباشرة لا الصندوق المركزي.
 *
 * ولا يظهر في شيء من ذلك اسمُ مطوّرٍ ولا اسمُ شخص.
 */
class MailIdentity
{
    /** حين لا يضبط المكتب اسمه بعد. */
    public const SYSTEM_NAME = 'مُداوَلة';

    /**
     * عنوان المُرسِل — المركزي دائماً.
     *
     * لا يُقرأ من إعدادات المكتب مهما كانت: تغييره هناك يكسر الإرسال
     * كلَّه، وهو ليس مما يملك المكتب تغييره.
     */
    public static function fromAddress(): string
    {
        $address = trim((string) config('mail.from.address', ''));

        return $address !== '' ? $address : 'no-reply@localhost';
    }

    /**
     * الاسم المعروض: اسم المكتب إن ضبطه، وإلا اسم النظام.
     *
     * $system = true للرسائل التي مصدرها النظام لا المكتب (تنبيه اشتراك
     * مثلاً)، فتُوقَّع «مُداوَلة» ولا تُنسب إلى المكتب.
     */
    public static function fromName(bool $system = false): string
    {
        if ($system) {
            return self::SYSTEM_NAME;
        }

        $name = trim((string) Setting::get('office_name', ''));

        if ($name !== '') {
            return $name;
        }

        // ولا يُسقَط على app.name بلا فحص: قيمتُه الافتراضية في لارافل
        // «Laravel»، وهي تصل الموكّل مُرسِلاً باسمها إن لم يُضبط
        // APP_NAME. فما لم يكن اسماً حقيقياً فاسمُ النظام أصدق.
        $appName = trim((string) config('app.name', ''));

        return ($appName !== '' && strcasecmp($appName, 'Laravel') !== 0)
            ? $appName
            : self::SYSTEM_NAME;
    }

    /**
     * وجهةُ الردّ: بريد المكتب إن كان صالحاً.
     *
     * بدونها يردّ الموكّل على الصندوق المركزي فلا يصل مكتبَه أحد.
     */
    public static function replyTo(): ?string
    {
        $email = trim((string) Setting::get('office_email', ''));

        return self::isDeliverable($email) ? $email : null;
    }

    /**
     * ترويسةُ الرسالة كما يقرؤها الموكّل — من موضعٍ واحد.
     *
     * الشاشةُ التي تعرضها والرسالةُ التي تُرسَل تقرآن من هنا معاً، فلا
     * تعِد الشاشةُ بشيءٍ ويخرج البريد بغيره.
     *
     * @return array{name:string, address:string, replyTo:?string}
     */
    public static function clientSees(): array
    {
        return [
            'name' => self::fromName(),
            'address' => self::fromAddress(),
            'replyTo' => self::replyTo(),
        ];
    }

    /**
     * ثغراتُ الهويّة التي يملك المكتبُ سدَّها بنفسه.
     *
     * لا تُخلط بأعطال الخادم: تلك يصلحها الدعم، وهذه حقلٌ فارغ في
     * شاشة الإعدادات. وسكوتُ النظام عنها هو ما يجعل موكّلاً يردّ على
     * رسالةٍ فلا يصل ردُّه أحداً.
     *
     * @return list<array{key:string, text:string}>
     */
    public static function identityIssues(): array
    {
        $issues = [];

        if (trim((string) Setting::get('office_name', '')) === '') {
            $issues[] = [
                'key' => 'office_name',
                'text' => 'اسم المكتب غير مضبوط — تصل رسائل موكّليك باسم «'
                    . self::fromName() . '» لا باسم مكتبك.',
            ];
        }

        $email = trim((string) Setting::get('office_email', ''));

        if ($email === '') {
            $issues[] = [
                'key' => 'office_email',
                'text' => 'بريد المكتب غير مضبوط — إن ردّ الموكّل على الرسالة'
                    . ' وصل ردُّه الصندوقَ المركزي ولم يره أحدٌ في مكتبك.',
            ];
        } elseif (!self::isDeliverable($email)) {
            $issues[] = [
                'key' => 'office_email',
                'text' => 'بريد المكتب المسجَّل غير صالح — يُتجاهَل، ويذهب ردُّ'
                    . ' الموكّل إلى الصندوق المركزي.',
            ];
        }

        return $issues;
    }

    /** عنوانٌ يصلح للإرسال إليه — لا فارغ ولا مشوَّه. */
    public static function isDeliverable(?string $email): bool
    {
        $email = trim((string) $email);

        return $email !== ''
            && mb_strlen($email) <= 254
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * هل البريد مضبوط فعلاً على هذا الخادم؟
     *
     * السائق log يكتب الرسالة في السجلّ ولا يرسلها، و array يبتلعها.
     * كلاهما صالح للتطوير وكاذبٌ في الإنتاج: النظام يظنّ أنه أرسل.
     */
    public static function isConfigured(): bool
    {
        $mailer = (string) config('mail.default', 'log');

        if (in_array($mailer, ['log', 'array', 'null'], true)) {
            return false;
        }

        if ($mailer !== 'smtp') {
            return true;
        }

        // وكلمةُ المرور من الأركان لا من الزينة.
        //
        // كانت تُستثنى، فكان مكتبٌ فيه MAIL_PASSWORD فارغ يُعلن
        // «✓ SMTP مضبوط — المُرسِل mudawalah@gmail.com» في office:health،
        // ويقبل OfficeMailer رسائله فيدفعها إلى الطابور، وترتدّ كلُّها
        // بـ ٥٣٥ بعد ربع ساعة في failed_jobs. والمكتبُ يبدو سليماً في
        // كل شاشة.
        //
        // و«مضبوط» هنا تعني «فيه ما يكفي للمحاولة» لا «سينجح»: صحّةُ
        // الكلمة لا تُعرف إلا من الخادم، وذلك عمل ‎--probe.
        return trim((string) config('mail.mailers.smtp.host', '')) !== ''
            && trim((string) config('mail.mailers.smtp.username', '')) !== ''
            && trim((string) config('mail.mailers.smtp.password', '')) !== '';
    }

    /**
     * تشخيصٌ يُعرض للمشرف — بلا كلمة المرور ولا جزءٍ منها.
     *
     * @return array<string, string>
     */
    public static function diagnostics(): array
    {
        $mailer = (string) config('mail.default', 'log');

        return [
            'السائق' => $mailer,
            'الخادم' => (string) config('mail.mailers.smtp.host', '—'),
            'المنفذ' => (string) config('mail.mailers.smtp.port', '—'),
            'التشفير' => (string) (config('mail.mailers.smtp.scheme') ?: 'tls (ضمني بالمنفذ)'),
            'المستخدم' => self::maskEmail((string) config('mail.mailers.smtp.username', '')),
            'كلمة المرور' => trim((string) config('mail.mailers.smtp.password', '')) !== ''
                ? 'مضبوطة (لا تُعرض)'
                : 'غير مضبوطة',
            'المُرسِل' => self::fromAddress(),
            'الاسم المعروض' => self::fromName(),
            'وجهة الردّ' => self::replyTo() ?? 'غير مضبوطة',
            'الطابور' => (string) config('queue.default', 'sync'),
        ];
    }

    /** «mudawalah@gmail.com» تصير «mud•••••@gmail.com». */
    public static function maskEmail(string $email): string
    {
        if ($email === '') {
            return '—';
        }

        $at = strpos($email, '@');

        if ($at === false || $at <= 1) {
            return str_repeat('•', mb_strlen($email));
        }

        return mb_substr($email, 0, min(3, $at)) . str_repeat('•', 5) . mb_substr($email, $at);
    }

    /**
     * تنقيةُ نصٍّ قبل تدوينه في السجلّ.
     *
     * رسائل أخطاء SMTP تنقل ردَّ الخادم كما هو، وبعض الخوادم تُعيد في
     * ردّها ما أُرسل إليها. فيُستبدل كلُّ ما يطابق السرّ المضبوط قبل أن
     * يصل السجلّ — لأنّ سجلَّ المكتب يُقرأ ويُنسخ ويُرسَل عند الشكوى.
     */
    public static function scrub(string $text): string
    {
        $secrets = array_filter([
            (string) config('mail.mailers.smtp.password', ''),
            (string) config('mail.mailers.smtp.username', ''),
        ], static fn (string $s): bool => trim($s) !== '');

        foreach ($secrets as $secret) {
            $text = str_replace($secret, '[محجوب]', $text);
            $text = str_replace(base64_encode($secret), '[محجوب]', $text);
        }

        return $text;
    }
}
