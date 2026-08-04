<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    protected $table = 'finance_transactions';

    protected $fillable = ['type', 'category', 'amount', 'description', 'date', 'payment_method', 'reference', 'user_id', 'attachment_path', 'attachment_name'];

    protected $appends = ['attachment_url'];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? \Illuminate\Support\Facades\Storage::url($this->attachment_path) : null;
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
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
