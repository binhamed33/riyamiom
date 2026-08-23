<?php

namespace App\Support;

use App\Models\CaseActivity;
use Illuminate\Support\Facades\Log;

/**
 * كاتب المسار الزمني للقضية.
 *
 * السبب الجذري لشكوى «أضيف جلسة أو مستند ولا يتحدّث شيء عند الموكّل»:
 * المسار الزمني في البوابة يقرأ CaseActivity، ولم يكن أحدٌ يكتب فيه
 * إلا الإدخال اليدوي للإجراءات ومحرّك الأتمتة والقوالب. أما إضافة
 * جلسة أو رفع مستند أو تغيير حالة القضية فلا تكتب فيه حرفاً — فيعمل
 * الموظّف طول اليوم ولا يرى الموكّل شيئاً.
 */
class CaseTimeline
{
    /**
     * التسجيل لا يُسقط العملية أبداً.
     *
     * إضافة جلسة عملٌ حقيقي للموظّف، وفشلُ سطرٍ في المسار الزمني لا
     * يجوز أن يُلغيها. يُبتلع الخطأ هنا ويُسجَّل تحذيراً، ولا يصعد.
     */
    public static function log(
        ?int $caseId,
        string $type,
        string $title,
        string $content = '',
        $occurredAt = null
    ): void {
        if (!$caseId) {
            return;
        }

        try {
            CaseActivity::create([
                'case_id' => $caseId,
                'user_id' => auth()->id(),
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'occurred_at' => $occurredAt ?? now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('تعذّر تسجيل حدث في المسار الزمني: ' . $e->getMessage());
        }
    }
}
