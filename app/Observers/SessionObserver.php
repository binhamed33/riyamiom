<?php

namespace App\Observers;

use App\Models\CaseActivity;
use App\Models\Session;
use App\Support\CaseTimeline;

/**
 * الجلسة تُسجَّل في مسار قضيتها.
 *
 * التسجيل في مراقب النموذج لا في المتحكّم عمداً: للجلسة أكثر من طريق
 * كتابة — المتحكّم، ومحرّك الأتمتة، والقالب، والاستيراد — وإصلاح
 * متحكّم واحد يترك بقية الطرق صامتة كما كانت.
 */
class SessionObserver
{
    public function created(Session $session): void
    {
        $line = implode(' — ', array_filter([
            $session->date?->format('Y-m-d'),
            $session->location,
        ])) ?: 'جلسة';

        CaseTimeline::log(
            $session->case_id,
            CaseActivity::TYPE_SESSION,
            'جلسة جديدة',
            $line,
            $session->date
        );
    }

    public function updated(Session $session): void
    {
        // الموعد وحده هو ما يعني الموكّل. تغيير ملاحظة داخلية أو حالة
        // إدارية لا يستحق سطراً في مساره.
        if (!$session->wasChanged('date')) {
            return;
        }

        $before = $session->getOriginal('date');
        $before = $before ? \Carbon\Carbon::parse($before)->format('Y-m-d') : '—';

        CaseTimeline::log(
            $session->case_id,
            CaseActivity::TYPE_SESSION,
            'تغيّر موعد الجلسة',
            'من ' . $before . ' إلى ' . ($session->date?->format('Y-m-d') ?? '—'),
            $session->date
        );
    }
}
