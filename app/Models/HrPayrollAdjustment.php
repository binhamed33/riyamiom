<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** بدلٌ أو خصمٌ يخصّ فترةً واحدة (YYYY-MM) لا الراتب الدائم. */
class HrPayrollAdjustment extends Model
{
    protected $table = 'hr_payroll_adjustments';

    protected $fillable = ['employee_id', 'period', 'kind', 'amount', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
