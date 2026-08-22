<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suggestion extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_IMPLEMENTED = 'implemented';

    protected $fillable = [
        'user_id', 'title', 'content', 'context', 'status', 'panel_status',
        'developer_reply', 'replied_at', 'reply_read',
        'delivery_state', 'delivery_attempts', 'delivered_at', 'delivery_error',
    ];

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
            'reply_read' => 'boolean',
            'context' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * وصف الحالة كما يقرأه الموظّف. الحالة الدقيقة من اللوحة إن وصلت،
     * وإلا فالحالة المحلّية — فمكتب غير مربوط يبقى كما كان.
     *
     * @return array{label: string, tone: string}
     */
    public function statusDisplay(): array
    {
        return match ($this->panel_status) {
            'planned' => ['label' => __('app.suggestion_state_planned'), 'tone' => 'planned'],
            'declined' => ['label' => __('app.suggestion_state_declined'), 'tone' => 'declined'],
            'done' => ['label' => __('app.suggestion_state_done'), 'tone' => 'done'],
            default => $this->status === self::STATUS_IMPLEMENTED
                ? ['label' => __('app.suggestion_state_done'), 'tone' => 'done']
                : ['label' => __('app.suggestion_state_reviewing'), 'tone' => 'reviewing'],
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
