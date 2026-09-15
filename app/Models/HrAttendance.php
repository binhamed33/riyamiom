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

    protected $fillable = ['user_id', 'work_date', 'check_in_at', 'check_out_at', 'resumed_at', 'minutes', 'intervals', 'note', 'status', 'source'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'resumed_at' => 'datetime',
            'intervals' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * بدايةُ الفترة المفتوحة الآن: الاستئنافُ إن كان، وإلّا الحضورُ الأوّل.
     *
     * كلُّ حسابٍ للمدّة يبدأ من هنا — الانصرافُ بالزرّ والسقفُ والإقفالُ
     * الليليّ سواء — وإلّا حُسبت استراحةُ الظهر دواماً لمن استأنف عصراً.
     */
    public function intervalStart(): \Carbon\Carbon
    {
        return $this->resumed_at ?? $this->check_in_at;
    }

    /** الدقائقُ المقفَلة قبل الفترة المفتوحة — صفرٌ ما لم يُستأنف اليوم. */
    public function bankedMinutes(): int
    {
        return $this->resumed_at ? (int) $this->minutes : 0;
    }

    /**
     * وسمُ وقتِ انصرافٍ استنتجه النظام — ليُقرأ في الكشف على حقيقته.
     *
     * الوسمُ كان يُكتب في العمود ولا يُعرض في أيّ كشف، فيقرأ المدير
     * «08:00 — 16:00» كأنّ صاحبَه ضغطها، وهي سقفٌ أُقفل عليه المنسيّ.
     */
    public function inferredLabel(): ?string
    {
        return match ($this->source) {
            'auto_capped' => 'بلغ السقف',
            'auto_closed' => 'آخر نشاط',
            default => null,
        };
    }

    /** «فترتان» لمن استأنف يومه — فلا يُقرأ «08:29 — 20:30» اثنتي عشرة ساعة. */
    public function intervalsLabel(): ?string
    {
        $n = (int) ($this->intervals ?? 1);

        return match (true) {
            $n <= 1 => null,
            $n === 2 => 'فترتان',
            default => $n . ' فترات',
        };
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
