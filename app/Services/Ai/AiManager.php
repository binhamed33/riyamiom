<?php

namespace App\Services\Ai;

use App\Support\AiSettings;
use RuntimeException;

/**
 * يحلّ مزوّد الذكاء الاصطناعي المُعدّ لهذا المكتب.
 * الإعدادات تُقرأ من قاعدة بيانات المكتب نفسه، فلا تتسرّب بين المكاتب.
 */
class AiManager
{
    public static function provider(?string $name = null): AiProvider
    {
        $name ??= AiSettings::provider();
        $config = config("ai.providers.$name");

        if (!is_array($config) || !($config['implemented'] ?? false)) {
            throw new RuntimeException('مزوّد الذكاء الاصطناعي «' . $name . '» غير مدعوم في هذا الإصدار.');
        }

        $driver = $config['driver'] ?? null;
        if (!is_string($driver) || !class_exists($driver)) {
            throw new RuntimeException('تعذر تحميل مزوّد الذكاء الاصطناعي «' . $name . '».');
        }

        return new $driver();
    }

    /** لا يرمي استثناءً — للاستخدام في المسارات التي تتحمّل غياب الإعداد. */
    public static function tryProvider(): ?AiProvider
    {
        try {
            return self::provider();
        } catch (RuntimeException) {
            return null;
        }
    }
}
