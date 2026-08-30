<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceFee extends Model
{
    protected $table = 'finance_fees';

    protected $fillable = ['case_id', 'fee_type', 'amount', 'status', 'client_visible', 'date', 'description', 'user_id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'client_visible' => 'boolean'];
    }

    /** ما يراه الموكّل: ما علّمه المكتب صراحةً — والافتراضي لا يُرى. */
    public function scopeVisibleToClient($query)
    {
        return $query->where('client_visible', true);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
