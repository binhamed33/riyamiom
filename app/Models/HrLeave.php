<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeave extends Model
{
    protected $table = 'hr_leaves';

    protected $fillable = [
        'employee_id', 'type', 'leave_type_id', 'start_date', 'end_date',
        'reason', 'status', 'approved_by', 'days', 'deduction_amount',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'deduction_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrLeaveType::class, 'leave_type_id');
    }

    /**
     * اسم النوع للعرض.
     *
     * الجدول أولاً، فإن كانت الإجازة قديمةً بلا نوعٍ مربوط رجعنا إلى
     * ترجمة الرمز القديم — فلا يظهر سطرٌ باسمٍ فارغ في صفحةٍ يقرأها
     * المدير ليقرّر.
     */
    public function typeName(): string
    {
        if ($this->leaveType) {
            return $this->leaveType->name;
        }

        $key = 'hr_leave_type_' . $this->type;
        $translated = __($key);

        return $translated === $key ? (string) $this->type : $translated;
    }
}
