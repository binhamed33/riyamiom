<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** بند قائمة تحقق داخل قضية (يُنشأ عادة من قالب ذكي). */
class CaseChecklistItem extends Model
{
    protected $fillable = ['case_id', 'title', 'is_done', 'done_by', 'done_at', 'sort'];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'done_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
