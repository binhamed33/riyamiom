<?php

namespace App\Support;

use App\Models\LegalCase;
use App\Models\Setting;

/**
 * الرسائل التي تصل الموكّل — من مصدر واحد.
 *
 * كانت مكتوبة حرفياً في موضعين، وفيهما اسم مكتب واحد بعينه ورابط
 * نطاقه. فأيّ مكتب يشتري مُداوَلة كان يرسل لموكّليه رسالة موقّعة باسم
 * مكتب آخر وتحيلهم إلى بوابة مكتب آخر. هنا يُقرأ الاسم من إعدادات
 * المكتب نفسه، ويُبنى الرابط من نطاقه هو — فلا يُذكر مكتب في بريد
 * مكتب غيره.
 *
 * وكانت الرسالة تطلب «رقم الهاتف أو البريد» للدخول، والبوابة تسأل عن
 * رقم الهوية ثم آخر ثلاثة أرقام من الهاتف. موكّل يتبع التعليمات
 * المكتوبة لا يدخل. صُحِّحت لتطابق البوابة.
 */
class ClientMessage
{
    /** اسم المكتب كما ضبطه صاحبه، لا كما وُرِث في الكود. */
    public static function officeName(): string
    {
        $name = trim((string) Setting::get('office_name', ''));

        return $name !== '' ? $name : (string) config('app.name', 'مُداوَلة');
    }

    /** رابط بوابة هذا المكتب — من نطاقه هو، فيتبع كل نسخة نطاقها. */
    public static function portalUrl(): string
    {
        return route('client.access');
    }

    public static function portalInvite(?LegalCase $case = null): string
    {
        $office = self::officeName();
        $url = self::portalUrl();
        $caseLine = $case?->case_number
            ? "\nرقم القضية: {$case->case_number}\n"
            : '';

        return <<<TXT
يسر **{$office}** أن تضع بين أيديكم خدمة **متابعة القضايا إلكترونياً**، وذلك حرصاً منا على تعزيز جودة الخدمات القانونية، وتوفير تجربة أكثر سهولة وشفافية لموكلينا الكرام.
{$caseLine}
يمكنكم الاطلاع على آخر مستجدات القضية، ومتابعة تفاصيلها بكل يسر، من خلال الدخول إلى الرابط التالي:

{$url}

بعد فتح الرابط، يُرجى إدخال **رقم الهوية أو السجل التجاري** المسجل لدى المكتب، ثم **آخر ثلاثة أرقام من رقم هاتفكم** المسجل لدينا، لتظهر لكم جميع تفاصيل القضية والمستجدات المتعلقة بها بشكل مباشر.

وفي حال واجهتكم أي صعوبة في الدخول أو كانت لديكم أي استفسارات، فإن فريقنا على أتم الاستعداد لخدمتكم والإجابة عن جميع استفساراتكم.

**{$office}**
نعتز بثقتكم، ونسعى دائماً إلى تقديم خدمات قانونية احترافية بأعلى معايير الجودة.
TXT;
    }

    public static function caseUpdate(?LegalCase $case = null): string
    {
        $office = self::officeName();
        $url = self::portalUrl();
        $caseLine = $case?->case_number
            ? "\nرقم القضية: {$case->case_number}\n"
            : '';

        return <<<TXT
يسرّ **{$office}** إشعاركم بأنه **تم تحديث بياناتكم** في نظام المكتب، وذلك لضمان تقديم خدماتنا القانونية بصورة أكثر كفاءة وسهولة.
{$caseLine}
يمكنكم الآن الاطلاع على بياناتكم المحدثة، ومتابعة جميع المستجدات المتعلقة بقضاياكم وخدماتكم، من خلال الدخول إلى الرابط التالي:

{$url}

بعد فتح الرابط، يُرجى إدخال **رقم الهوية أو السجل التجاري** المسجل لدى المكتب، ثم **آخر ثلاثة أرقام من رقم هاتفكم** المسجل لدينا، لتظهر لكم بياناتكم المحدثة وجميع تفاصيل القضايا وآخر المستجدات المتعلقة بها.

وفي حال واجهتكم أي صعوبة في الدخول أو كانت لديكم أي استفسارات، فإن فريقنا على أتم الاستعداد لخدمتكم والإجابة عن جميع استفساراتكم.

**{$office}**
نعتز بثقتكم، ونسعى دائماً إلى تقديم خدمات قانونية احترافية بأعلى معايير الجودة.
TXT;
    }

    /** عنوان البريد — يحمل اسم المكتب المرسِل لا اسماً موروثاً. */
    public static function inviteSubject(?LegalCase $case = null): string
    {
        return self::subject('متابعة قضيتك إلكترونياً', $case);
    }

    public static function updateSubject(?LegalCase $case = null): string
    {
        return self::subject('إشعار بتحديث بيانات قضيتك', $case);
    }

    private static function subject(string $lead, ?LegalCase $case): string
    {
        $subject = $lead . ' - ' . self::officeName();

        return $case?->case_number
            ? $subject . ' (قضية ' . $case->case_number . ')'
            : $subject;
    }

    /** المُرسِل: بريد المكتب واسمه من إعداداته. */
    public static function fromAddress(): string
    {
        $email = trim((string) Setting::get('office_email', ''));

        return $email !== '' ? $email : (string) config('mail.from.address', 'hello@example.com');
    }
}
