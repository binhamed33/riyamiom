<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    protected $table = 'finance_transactions';

    protected $fillable = ['type', 'category', 'amount', 'description', 'date', 'payment_method', 'reference', 'user_id', 'attachment_path', 'attachment_name'];

    protected $appends = ['attachment_url', 'attachment_download_url'];

    public function getAttachmentDownloadUrlAttribute(): ?string
    {
        return $this->attachment_path && $this->exists
            ? route('finance.transactions.attachment', [$this, 'download' => 1])
            : null;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        // مسارٌ محميّ لا رابطٌ عام: Storage::url() كان يبني ‎/storage/…‎
        // ويعتمد رابطاً رمزياً لا يُنشأ هنا، فكان المرفق لا يُفتح أبداً.
        return $this->attachment_path && $this->exists
            ? route('finance.transactions.attachment', $this)
            : null;
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
