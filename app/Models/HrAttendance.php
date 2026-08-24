<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل حضور يوم واحد لموظف واحد.
 *
 * اليوم يُحسب بتوقيت مسقط لا بتوقيت الخادم: موظفٌ يحضر ٧ صباحاً
 * بتوقيت عُمان يجب ألا يُسجَّل على يوم أمس لأن الخادم على UTC.
 */
class HrAttendance extends Model
{
    protected $table = 'hr_attendance';

    protected $fillable = ['user_id', 'work_date', 'check_in_at', 'check_out_at', 'minutes', 'note'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function today(): string
    {
        return now('Asia/Muscat')->toDateString();
    }

    /** سجلّ اليوم لهذا الموظف إن وُجد. */
    public static function todayFor(int $userId): ?self
    {
        return static::where('user_id', $userId)->whereDate('work_date', self::today())->first();
    }

    /**
     * السجلّ المفتوح الذي ينتظر انصرافاً — ولو كان حضورُه أمس.
     *
     * موظّف حضر ١١:٥٠ ليلاً وانصرف ١٢:١٠ بعد منتصف الليل كان يُقال له
     * «لم تسجّل حضوراً اليوم» ويبقى سجلّ أمس مفتوحاً بلا انصراف أبداً،
     * ولا شيء في الواجهة يُغلقه. النافذة يوم كامل: أطول من أي دوام،
     * وأقصر من أن يُغلق سجلّ نُسي منذ أسبوع بانصراف اليوم.
     */
    public static function openFor(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->whereNull('check_out_at')
            ->where('check_in_at', '>=', now()->subDay())
            ->orderByDesc('check_in_at')
            ->first();
    }
}
