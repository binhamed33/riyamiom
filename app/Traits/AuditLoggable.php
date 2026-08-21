<?php

namespace App\Traits;

use App\Models\AuditLog;
use App\Models\LegalCase;

trait AuditLoggable
{
    private function logAudit(string $action, ?string $modelType, ?int $modelId, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => $modelType,
            'model_id'   => $modelId,
            'case_id'    => $this->deriveCaseId($modelType, $modelId, $oldValues, $newValues),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * اشتقاق القضية المرتبطة بالسجل ليظهر في الخط الزمني للقضية.
     * القضية نفسها → معرّفها؛ نماذج تحمل case_id (جلسة/مهمة/مستند/نشاط) → قيمته.
     */
    private function deriveCaseId(?string $modelType, ?int $modelId, ?array $oldValues, ?array $newValues): ?int
    {
        if ($modelType === LegalCase::class) {
            return $modelId;
        }

        $caseId = $newValues['case_id'] ?? $oldValues['case_id'] ?? null;

        return is_numeric($caseId) ? (int) $caseId : null;
    }
}
