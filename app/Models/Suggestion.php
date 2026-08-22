<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suggestion extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_IMPLEMENTED = 'implemented';

    protected $fillable = [
        'user_id', 'title', 'content', 'context', 'status',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
