<?php

namespace App\Support;

/**
 * بلغ المكتبُ حدَّ مورد أثناء الإنشاء — تُلتقط في المتحكّم وتُعرض
 * رسالة PlanLimits::message() نفسها، فلا صياغة ثانية تشرد عن الأولى.
 */
class LimitReached extends \RuntimeException
{
    public function __construct(public readonly string $resource)
    {
        parent::__construct('plan limit reached: ' . $resource);
    }
}
