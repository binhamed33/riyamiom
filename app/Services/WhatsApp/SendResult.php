<?php

namespace App\Services\WhatsApp;

/**
 * نتيجة محاولة إرسال — نجاحٌ بمعرّف، أو فشلٌ يعرف هل يستحقّ إعادة.
 *
 * التمييز بين «فشلٍ يزول» و«فشلٍ لا تُصلحه ألف إعادة» هو كلُّ الفرق:
 * إعادةُ رسالةٍ رُفضت لأنّ الرقم ليس على واتساب تكرّر الفشل ثلاثاً ثم
 * تُسجّل خطأً، والتوقّفُ عن إعادة رسالةٍ سقطت لانقطاعِ شبكةٍ لحظيّ
 * يُضيع رسالةَ موكّل.
 */
class SendResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $wamid = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorTitle = null,
        public readonly bool $retryable = false,
        /** الجسرُ قبل الرسالة (2xx) ولم يُعِد معرّفَها: خرجت على الأرجح ولا تأكيد. */
        public readonly bool $unconfirmed = false,
    ) {
    }

    public static function sent(string $wamid): self
    {
        return new self(true, $wamid);
    }

    /**
     * قُبلت ولا معرّف: ليست إخفاقاً يُعاد — الإعادةُ تُرسل الرسالةَ مرّتين إلى
     * الموكّل — وليست إرسالاً مؤكَّداً يُخترع له معرّف.
     */
    public static function accepted(): self
    {
        return new self(true, null, null, null, false, true);
    }

    public static function failed(?string $code, ?string $title, bool $retryable = false): self
    {
        return new self(false, null, $code !== null ? (string) $code : null, $title, $retryable);
    }
}
