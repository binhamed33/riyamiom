<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'message', 'attachment_path', 'attachment_name', 'attachment_type', 'attachment_size'];

    protected $appends = ['attachment_url', 'is_image'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? Storage::url($this->attachment_path) : null;
    }

    public function getIsImageAttribute(): bool
    {
        return $this->attachment_type && str_starts_with($this->attachment_type, 'image/');
    }
}
