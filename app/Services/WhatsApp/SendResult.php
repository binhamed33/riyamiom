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
    ) {
    }

    public static function sent(string $wamid): self
    {
        return new self(true, $wamid);
    }

    public static function failed(?string $code, ?string $title, bool $retryable = false): self
    {
        return new self(false, null, $code !== null ? (string) $code : null, $title, $retryable);
    }
}
