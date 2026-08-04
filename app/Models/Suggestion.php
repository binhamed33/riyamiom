<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suggestion extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_IMPLEMENTED = 'implemented';

    protected $fillable = ['user_id', 'content', 'status', 'developer_reply', 'replied_at', 'reply_read'];

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
            'reply_read' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\GuestScope);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
