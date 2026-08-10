<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseActivity extends Model
{
    use HasFactory;

    const TYPE_NOTE = 'note';
    const TYPE_CALL = 'call';
    const TYPE_DOCUMENT = 'document';
    const TYPE_TASK = 'task';
    const TYPE_SESSION = 'session';
    const TYPE_PAYMENT = 'payment';
    const TYPE_APPOINTMENT = 'appointment';
    const TYPE_OTHER = 'other';

    protected $fillable = [
        'case_id',
        'user_id',
        'type',
        'title',
        'content',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}