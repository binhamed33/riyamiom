<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPenalty extends Model
{
    protected $table = 'hr_penalties';

    protected $fillable = ['employee_id', 'amount', 'reason', 'date', 'given_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\GuestScope);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }
}
