<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceInvoice extends Model
{
    protected $table = 'finance_invoices';

    protected $fillable = ['invoice_number', 'client_id', 'amount', 'paid_amount', 'status', 'issue_date', 'due_date', 'description', 'user_id', 'attachment_path', 'attachment_name'];

    protected $appends = ['attachment_url'];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? \Illuminate\Support\Facades\Storage::url($this->attachment_path) : null;
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\GuestScope);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->amount - $this->paid_amount);
    }
}
