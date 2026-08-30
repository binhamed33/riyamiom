<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceInvoice extends Model
{
    protected $table = 'finance_invoices';

    protected $fillable = ['invoice_number', 'client_id', 'case_id', 'amount', 'paid_amount', 'status', 'client_visible', 'issue_date', 'due_date', 'description', 'user_id', 'attachment_path', 'attachment_name'];

    protected $appends = ['attachment_url', 'attachment_download_url'];

    public function getAttachmentDownloadUrlAttribute(): ?string
    {
        return $this->attachment_path && $this->exists
            ? route('finance.invoices.attachment', [$this, 'download' => 1])
            : null;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        // مسارٌ محميّ لا رابطٌ عام: Storage::url() كان يبني ‎/storage/…‎
        // ويعتمد رابطاً رمزياً لا يُنشأ هنا، فكان المرفق لا يُفتح أبداً.
        return $this->attachment_path && $this->exists
            ? route('finance.invoices.attachment', $this)
            : null;
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'client_visible' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    /** ما يراه الموكّل: ما علّمه المكتب صراحةً — والافتراضي لا يُرى. */
    public function scopeVisibleToClient($query)
    {
        return $query->where('client_visible', true);
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
