<?php

namespace App\Services;

use App\Mail\MailKind;
use App\Mail\OfficeMail;
use App\Support\MailIdentity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * البابُ الوحيد الذي يخرج منه بريدُ النظام.
 *
 * ═══ القاعدة الحاكمة ═══
 *
 * لا يُفشِل عملاً أبداً. قيدُ القضية عملُ المحامي، وإرسالُ البشارة إلى
 * الموكّل خدمةٌ تابعة. فإن تعذّرت — عنوانٌ ناقص، أو نوعٌ مُطفأ، أو
 * بريدٌ غير مضبوط على الخادم، أو طابورٌ لا يستجيب — عاد هذا الباب
 * بسببٍ مكتوب ولم يرمِ شيئاً إلى أعلى.
 *
 * ولذلك يُرجع نتيجةً تُقرأ، لا true/false: من ينادي يعرف ماذا جرى
 * فيُخبر المستخدم بدقّة بدل «تعذّر الإرسال».
 */
class OfficeMailer
{
    public const SENT = 'sent';
    public const NO_ADDRESS = 'no_address';
    public const BAD_ADDRESS = 'bad_address';
    public const DISABLED = 'disabled';
    public const NOT_CONFIGURED = 'not_configured';
    public const FAILED = 'failed';

    /**
     * يضع الرسالة في الطابور بعد استيفاء الشروط.
     *
     * @return array{status:string, reason:?string}
     */
    public static function send(?string $to, OfficeMail $mail): array
    {
        $kind = $mail->kind;

        if (!$kind->isEnabled()) {
            return self::result(self::DISABLED, 'نوع الإشعار «' . $kind->label() . '» مُطفأ في إعدادات المكتب.');
        }

        if (trim((string) $to) === '') {
            return self::result(self::NO_ADDRESS, 'لا يوجد بريد إلكتروني مسجَّل للمستلم.');
        }

        if (!MailIdentity::isDeliverable($to)) {
            // لا يُدوَّن العنوان نفسه: بريد الموكّل بيانٌ يخصّه
            Log::warning('Mail skipped [' . $kind->value . ']: recipient address is not valid.');

            return self::result(self::BAD_ADDRESS, 'البريد الإلكتروني المسجَّل غير صالح — راجع بطاقة الموكّل.');
        }

        if (!MailIdentity::isConfigured()) {
            return self::result(
                self::NOT_CONFIGURED,
                'البريد غير مُفعَّل على هذا الخادم — راجع إعدادات SMTP مع الدعم.',
            );
        }

        try {
            Mail::to($to)->queue($mail);
        } catch (\Throwable $e) {
            // الطابور نفسه تعثّر (قاعدةٌ مقفلة، أو سائقٌ sync وSMTP ساقط).
            // يُدوَّن السبب منقّىً ولا يُرمى: القضية تُحفظ على كل حال.
            Log::error('Mail queueing failed [' . $kind->value . ']: ' . MailIdentity::scrub($e->getMessage()));

            return self::result(self::FAILED, 'تعذّر جدولة الرسالة الآن — ستبقى القضية محفوظة.');
        }

        return self::result(self::SENT, null);
    }

    /** هل النوع صالحٌ للإرسال أصلاً؟ — لعرض الحالة قبل المحاولة. */
    public static function canSend(MailKind $kind): bool
    {
        return $kind->isEnabled() && MailIdentity::isConfigured();
    }

    /** @return array{status:string, reason:?string} */
    private static function result(string $status, ?string $reason): array
    {
        return ['status' => $status, 'reason' => $reason];
    }
}
