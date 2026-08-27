<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * الراتب الجاري لموظف.
 *
 * لا يُقرأ هذا النموذج إلا خلف تفويضٍ من الخادم — انظر
 * HrSalaryPolicy وSalaryController. ولا يُمرَّر إلى أي عرضٍ
 * يراه صاحب الراتب نفسه.
 */
class HrSalary extends Model
{
    protected $table = 'hr_salaries';

    protected $fillable = ['employee_id', 'basic_salary', 'allowances', 'effective_from', 'note', 'updated_by'];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
