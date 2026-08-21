<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تذكير مجدول لقضية: عند حلول موعده يُرسل إشعار داخلي للمستهدف
 * (محامي القضية / الإدارة / كلاهما) ويُختم بـ notified_at.
 */
class CaseReminder extends Model
{
    protected $fillable = ['case_id', 'title', 'remind_at', 'target', 'notified_at'];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }
}
