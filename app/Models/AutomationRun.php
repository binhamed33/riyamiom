<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل تنفيذ الأتمتة: كل تشغيل (نجاح/فشل/تخطٍّ) يُسجَّل هنا — لا فشل صامت.
 * dedupe_key يمنع تنفيذ نفس القاعدة على نفس الموضوع مرتين.
 */
class AutomationRun extends Model
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'automation_id', 'trigger', 'subject_type', 'subject_id',
        'case_id', 'status', 'summary', 'error', 'dedupe_key',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'نجح',
            self::STATUS_FAILED => 'فشل',
            self::STATUS_SKIPPED => 'تخطّي',
            default => $this->status,
        };
    }
}
