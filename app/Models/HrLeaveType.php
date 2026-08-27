<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** نوع إجازة يحدّده المكتب، وحكمُه في الراتب من إعداده لا من اسمه. */
class HrLeaveType extends Model
{
    protected $table = 'hr_leave_types';

    protected $fillable = ['code', 'name', 'affects_salary', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'affects_salary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(HrLeave::class, 'leave_type_id');
    }

    /** الأنواع المعروضة للاختيار — المعطّل يبقى على إجازاته القديمة ولا يُعرض. */
    public static function selectable()
    {
        return static::where('is_active', true)->orderBy('sort')->orderBy('id')->get();
    }
}
