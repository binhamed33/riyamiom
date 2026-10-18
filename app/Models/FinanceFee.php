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
        // ‏Y-m-d في JSON: نافذةُ التعديل تربط التاريخَ بـ<input type=date> مباشرةً
        return ['date' => 'date:Y-m-d', 'amount' => 'decimal:2', 'client_visible' => 'boolean'];
    }

    /** ما يراه الموكّل: ما علّمه المكتب صراحةً — والافتراضي لا يُرى. */
    public function scopeVisibleToClient($query)
    {
        return $query->where('client_visible', true);
    }

    public function case(): BelongsTo
    {
        // ‏withTrashed: القضيةُ المحذوفة تبقى في سلّة المحذوفات ورسومُها باقية،
        // وكان تبويبُ الرسوم ينهار (500) على ‎$fee->case->case_number‎ = null
        return $this->belongsTo(LegalCase::class, 'case_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
