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

    /** مَن كتب وقتَ الانصراف — انظر هجرة closed_by. */
    public const CLOSED_BY_BUTTON = 'button';
    public const CLOSED_BY_CAP = 'cap';
    public const CLOSED_BY_SEEN = 'seen';
    public const CLOSED_BY_MANAGER = 'manager';
    public const CLOSED_BY_LEGACY = 'legacy';
    /** زرُّ الخروج القديم الذي كان يكتب انصرافاً — صفوفٌ من قبل الإصلاح وُسمت من سجلّ التدقيق. */
    public const CLOSED_BY_LOGOUT = 'logout';

    protected $fillable = ['user_id', 'work_date', 'check_in_at', 'check_out_at', 'resumed_at', 'minutes', 'inferred_minutes', 'intervals', 'note', 'status', 'source', 'closed_by'];

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
     * وسمُ وقتِ انصرافٍ لم يضغطه صاحبُه — ليُقرأ في الكشف على حقيقته.
     *
     * الوسمُ كان يُكتب في العمود ولا يُعرض في أيّ كشف، فيقرأ المدير
     * «08:00 — 16:00» كأنّ صاحبَه ضغطها، وهي سقفٌ أُقفل عليه المنسيّ.
     * والصفوفُ التي أُقفلت قبل عمود closed_by تُقرأ من source القديم.
     */
    public function inferredLabel(): ?string
    {
        // أُقفل بالزرّ لكنّ في مجموعه دقائقَ كتبها السقفُ قبل الاستئناف — فلا يُقرأ كلُّه موقَّعاً
        if ($this->closed_by === self::CLOSED_BY_BUTTON && (int) $this->inferred_minutes > 0) {
            return 'فترةٌ بالسقف';
        }

        return match ($this->closed_by ?? $this->legacyClosedBy()) {
            self::CLOSED_BY_CAP => 'بلغ السقف',
            self::CLOSED_BY_SEEN => 'آخر نشاط',
            self::CLOSED_BY_MANAGER => 'مصحَّح',
            self::CLOSED_BY_LOGOUT => 'زرّ الخروج (قديم)',
            default => null,
        };
    }

    private function legacyClosedBy(): ?string
    {
        return match ($this->source) {
            'auto_capped' => self::CLOSED_BY_CAP,
            'auto_closed' => self::CLOSED_BY_SEEN,
            default => null,
        };
    }

    /** هل وقتُ الانصراف استنتاجٌ (سقفٌ أو آخرُ نشاط) لا ضغطةُ صاحبه ولا تصحيحُ إداري؟ */
    public function closedByInference(): bool
    {
        return in_array($this->closed_by ?? $this->legacyClosedBy(), [self::CLOSED_BY_CAP, self::CLOSED_BY_SEEN], true);
    }

    /** هل في مجموع اليوم دقائقُ كتبها النظام — إقفالاً كاملاً أو فترةً قبل استئناف؟ */
    public function hasInferredTime(): bool
    {
        return $this->closedByInference() || (int) $this->inferred_minutes > 0;
    }

    /**
     * وقتُ الانصراف للعرض — و«(+1)» إن وقع بعد منتصف الليل.
     *
     * صفُّ ١٥ سبتمبر بانصراف «00:30» يُقرأ نصفَ ساعةٍ بعد الظهر أو خطأً؛
     * والقولُ «في اليوم التالي» يقطع الشكّ.
     */
    public function checkOutDisplay(string $format = 'H:i'): ?string
    {
        if (! $this->check_out_at) {
            return null;
        }

        $out = $this->check_out_at->timezone('Asia/Muscat');
        $crossed = $this->work_date && $out->toDateString() > $this->work_date->toDateString();

        return $out->format($format) . ($crossed ? ' (+1)' : '');
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
