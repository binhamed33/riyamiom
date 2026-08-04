<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPerformance extends Model
{
    protected $table = 'hr_performance';

    protected $fillable = ['employee_id', 'review_date', 'rating', 'notes', 'reviewer_id'];

    protected function casts(): array
    {
        return ['review_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\GuestScope);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
