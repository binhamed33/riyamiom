<?php

namespace App\Services\WhatsApp;

use App\Support\WhatsAppSettings;

/**
 * اختيارُ مزوّد واتساب المضبوط لهذا المكتب.
 *
 * نفس نمط App\Services\Ai\AiManager: نقطةُ إنشاءٍ واحدة، فلا يبني
 * متحكّمٌ ولا مهمّةٌ صنفَ مزوّدٍ بيدها ولا تعرف اسمه.
 */
class WhatsAppManager
{
    /** المزوّد إن كان مضبوطاً وجاهزاً للإرسال، وإلا null. */
    public static function provider(): ?WhatsAppProviderInterface
    {
        $provider = self::make();

        return $provider && $provider->isConfigured() ? $provider : null;
    }

    /** المزوّد ولو لم يكن مضبوطاً — لصفحة الفحص التي تشرح ما ينقص. */
    public static function make(): ?WhatsAppProviderInterface
    {
        $name = (string) config('whatsapp.default', 'meta');
        $driver = config("whatsapp.providers.$name.driver");
        $implemented = (bool) config("whatsapp.providers.$name.implemented", false);

        if (!$implemented || !is_string($driver) || !class_exists($driver)) {
            return null;
        }

        $instance = app($driver);

        return $instance instanceof WhatsAppProviderInterface ? $instance : null;
    }

    public static function isConnected(): bool
    {
        return WhatsAppSettings::isConnected() && self::provider() !== null;
    }
}
