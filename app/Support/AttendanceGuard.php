<?php

namespace App\Support;

use App\Models\HrAttendance;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * الحضور المرتبط بالجلسة.
 *
 * ثلاث قواعد تحكم هذا الملف:
 *
 * ١ — فشل الحضور لا يمنع الدخول أبداً. الموظف يأتي ليعمل؛ وعطلٌ في
 *     جدول الحضور لا يجوز أن يقف بينه وبين قضيةٍ لها جلسةٌ اليوم.
 *     لذلك كل نداء هنا مغلَّفٌ ويُرجع null عند العطل بدل أن يرمي.
 *
 * ٢ — لا نكتب وقتاً لم نره. من أغلق المتصفح دون «تسجيل الانصراف»
 *     لا نعرف متى انصرف، فلا نخترع له وقتاً: يبقى سجلّه مفتوحاً
 *     وتُعرض عليه حالته حين يعود.
 *
 * ٣ — الانصرافُ بزرّه وحده، والخروجُ من النظام ليس انصرافاً. الموظّف
 *     يخرج من النظام ليدخل من جهازٍ آخر، أو يقفل الجهازَ في استراحة
 *     الظهر ويعود عصراً — وهو في دوامه. وكان زرُّ الخروج يكتب انصرافاً
 *     فيُقفل اليومُ ظهراً وتضيع الفترةُ المسائيّة من كشف الشهر.
 *     ومن انصرف ثمّ عاد يُستأنف يومُه لا يُقال له «يومك مكتمل».
 */
class AttendanceGuard
{
    /** الأدوار التي لها حضور — الموكّل ليس موظفاً في المكتب. */
    private const STAFF_ROLES = ['admin', 'lawyer', 'staff'];

    /**
     * استراحةٌ لا يومٌ انقضى: انصرافٌ مسجَّلٌ قبل أقلَّ من هذا يُستأنف
     * تلقائيّاً عند الدخول التالي.
     *
     * الفترتان في المكاتب تفصل بينهما ثلاثُ ساعات (١:٣٠ → ٤:٣٠). أمّا
     * من أنهى يومَه الثانيةَ ظهراً ودخل من بيته العاشرةَ ليلاً ليقرأ
     * ملفّاً فليس مستأنفاً دوامَه — ولو فُتحت له فترةٌ لبلغت السقفَ
     * وأضافت ثماني ساعاتٍ لم يعملها. فتلك تُترك لزرّ «استئناف الدوام».
     */
    public const RESUME_WINDOW_HOURS = 6;

    /**
     * من رآه الخادمُ في آخر ساعة لا يُقفل سجلُّه بالسقف.
     *
     * ساعةٌ لأنّها مهلةُ الخمول في الواجهة: من غاب عن الشاشة ساعةً
     * أُخرج من النظام فانقطع أثرُه — فمن ما زال أثرُه حيّاً فهو أمام
     * شاشته، لا يُكتب له انصرافٌ وهو يعمل.
     */
    public const CAP_IDLE_MINUTES = 60;

    public static function tracks(?User $user): bool
    {
        return $user !== null
            && $user->is_active
            && in_array($user->role, self::STAFF_ROLES, true);
    }

    /** هل فعّل المكتب الحضور التلقائي عند الدخول؟ */
    public static function autoEnabled(): bool
    {
        return (string) Setting::get('hr_auto_checkin', '1') !== '0';
    }

    /**
     * حضورٌ عند الدخول — مرّة واحدة في اليوم مهما تكرّر الدخول.
     *
     * القيد الفريد (user_id, work_date) هو الحارس الحقيقي: فحصٌ قبل
     * الإنشاء لا يكفي حين يدخل الموظف من جهازين في اللحظة نفسها،
     * فنمسك اصطدام القيد ونعيد السجلّ القائم بدل أن نُفشل الدخول.
     *
     * ومن دخل وسجلُّ يومه مقفَلٌ بانصرافٍ قريب (استراحة الظهر) يُستأنف
     * يومُه: كان يُقال له «يومك مكتمل» وتضيع فترتُه المسائيّة.
     *
     * @return array{record: HrAttendance, created: bool, resumed: bool}|null
     */
    public static function checkInOnLogin(User $user): ?array
    {
        if (! self::tracks($user) || ! self::autoEnabled()) {
            return null;
        }

        try {
            $existing = HrAttendance::todayFor($user->id);

            if ($existing) {
                $resumed = self::shouldAutoResume($existing) && self::reopen($existing) !== null;

                return ['record' => $existing, 'created' => false, 'resumed' => $resumed];
            }

            $record = HrAttendance::create([
                'user_id' => $user->id,
                'work_date' => HrAttendance::today(),
                'check_in_at' => now(),
                'status' => 'present',
                'source' => 'auto_login',
            ]);

            return ['record' => $record, 'created' => true, 'resumed' => false];
        } catch (UniqueConstraintViolationException) {
            $existing = HrAttendance::todayFor($user->id);

            return $existing ? ['record' => $existing, 'created' => false, 'resumed' => false] : null;
        } catch (\Throwable $e) {
            // جدولٌ ناقص أو قاعدةٌ لا تستجيب: يُسجَّل ويمضي الدخول
            Log::warning('attendance auto check-in failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** انصرافٌ قريبٌ (استراحة) لا يومٌ انقضى — انظر RESUME_WINDOW_HOURS. */
    private static function shouldAutoResume(HrAttendance $record): bool
    {
        return $record->check_out_at !== null
            && $record->check_out_at->greaterThan(now()->subHours(self::RESUME_WINDOW_HOURS));
    }

    /**
     * انصرافٌ بالزرّ الصريح — الطريقُ الوحيد لإقفال اليوم بيد صاحبه.
     *
     * المدّةُ تُحسب من بداية الفترة المفتوحة وتُضاف إلى ما قبلها: من
     * استأنف عصراً لا تُحسب له استراحةُ الظهر دواماً.
     */
    public static function checkOut(?User $user): ?HrAttendance
    {
        if (! self::tracks($user)) {
            return null;
        }

        try {
            $record = HrAttendance::todayFor($user->id) ?? HrAttendance::openFor($user->id);

            if (! $record || $record->check_out_at !== null) {
                return null;
            }

            // وقتٌ بلغ السقفَ ثمّ استُؤنف وأُقفل بالزرّ صار وقتاً مسجَّلاً
            // لا مستنتَجاً — فلا يبقى موسوماً بالسقف في الكشف
            $source = in_array($record->source, ['auto_capped', 'auto_closed'], true) ? 'manual' : $record->source;

            return self::close($record, now(), $source);
        } catch (\Throwable $e) {
            Log::warning('attendance check-out failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * استئنافُ يومٍ أُقفل — بالزرّ، لمن عاد وسجلُّه يقول «مكتمل».
     *
     * يُفتح سجلُّ اليوم نفسُه (يومٌ واحدٌ لكلّ موظّف — القيدُ الفريد باقٍ)
     * وتُحفظ دقائقُه السابقة، وتبدأ فترةٌ جديدة من الآن.
     */
    public static function resume(?User $user): ?HrAttendance
    {
        if (! self::tracks($user)) {
            return null;
        }

        try {
            $record = HrAttendance::todayFor($user->id);

            if (! $record || $record->check_out_at === null) {
                return null;
            }

            return self::reopen($record);
        } catch (\Throwable $e) {
            Log::warning('attendance resume failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function reopen(HrAttendance $record): ?HrAttendance
    {
        $tz = 'Asia/Muscat';
        $trail = 'استُؤنف ' . now()->timezone($tz)->format('h:i A')
            . ' بعد انصرافٍ مسجَّل ' . $record->check_out_at->timezone($tz)->format('h:i A');

        // الملاحظةُ ٢٥٥ حرفاً: يُحتفظ بأحدث الأثر لا بأقدمه
        $note = trim(($record->note ? $record->note . ' · ' : '') . $trail);
        if (mb_strlen($note) > 255) {
            $note = mb_substr($note, -255);
        }

        $record->update([
            'resumed_at' => now(),
            'check_out_at' => null,
            'intervals' => (int) ($record->intervals ?: 1) + 1,
            'status' => 'present',
            'note' => $note,
        ]);

        return $record;
    }

    /**
     * إقفالٌ واحدٌ لكلّ الطرق: الزرّ والسقف والإقفال الليليّ.
     *
     * الدقائقُ من بداية الفترة المفتوحة إلى وقت الانصراف مضافةً إلى
     * المقفَل قبلها، والانصرافُ لا يسبق بدايةَ الفترة مهما قال المصدر.
     */
    private static function close(HrAttendance $record, CarbonInterface $out, ?string $source = null): HrAttendance
    {
        $start = $record->intervalStart();
        $out = $out->greaterThan($start) ? $out : $start;

        $data = [
            'check_out_at' => $out,
            'resumed_at' => null,
            'minutes' => $record->bankedMinutes() + (int) $start->diffInMinutes($out),
            'status' => 'completed',
        ];

        if ($source !== null) {
            $data['source'] = $source;
        }

        $record->update($data);

        return $record;
    }

    /**
     * هل فعّل المكتب الإقفال الليليّ بآخر نشاطٍ معروف؟
     *
     * معطَّلٌ افتراضاً بناءً على اقتراح محامٍ يستعمل النظام: المحامي
     * يكتب ويقابل الموكّلين بعيداً عن الشاشة، فآخرُ نقرةٍ له الساعة
     * ١١:٢٠ لا تعني انصرافه ١١:٢٠ — ووقتٌ مخترَع في كشف دوامٍ أسوأ
     * من خانةٍ فارغة تقول الصدق: «لم يُسجَّل». الانصراف بزرّه وحده.
     */
    public static function autoCloseEnabled(): bool
    {
        return (string) Setting::get('hr_auto_close', '0') === '1';
    }

    /**
     * سقفُ المناوبة: ثماني ساعاتٍ من الحضور ثمّ يُقفل السجلّ.
     *
     * ═══ ما يُصلحه ═══
     *
     * الانصرافُ بزرّه وحده — وهذه قاعدةٌ تبقى. لكنّ الغالب أن يُغلق
     * الموظّفُ المتصفّح ويمضي، فيبقى سجلُّه مفتوحاً إلى الأبد: لا
     * انصرافَ ولا دقائقَ محسوبة، ويُعرض في كشف الشهر «لم يُسجَّل».
     * ومن دخل الأحد يبقى «مفتوحاً» يوم الخميس.
     *
     * فالسقفُ حدٌّ لا تخمين: بعد ثماني ساعاتٍ من بداية الفترة يُقفل
     * السجلّ على «بدايةٌ + ثماني ساعات» — يومُ دوامٍ كامل.
     *
     * ═══ ولا يُقفل على من ما زال يعمل ═══
     *
     * كان يُقفل بالساعة وحدها، فمن حضر السابعةَ وُجد الثالثةَ «يومك
     * مكتمل» وهو على شاشته. فمن رآه الخادمُ في آخر ساعةٍ يُترك، ومن
     * رآه بعد السقف ثمّ غاب يُقفل على آخر ما رآه لا على السقف —
     * الوقتُ الذي رأيناه لا يُقصّ. وما دون السقف لا يُستنتج من نقرة.
     *
     * ═══ ولماذا وسمٌ مستقلّ ═══
     *
     * `auto_capped` يقول للمدير: هذا وقتٌ بلغ السقف، لا وقتٌ ضغطه
     * صاحبُه. فمن نسي الانصراف يُراجَع سجلُّه ويُصحَّح، ولا يُقرأ
     * الرقمُ كأنّه شهادةُ حضورٍ موقّعة.
     */
    public const SHIFT_CAP_HOURS = 8;

    public static function capHours(): int
    {
        $value = (int) Setting::get('hr_shift_cap_hours', (string) self::SHIFT_CAP_HOURS);

        // سقفٌ صفرٌ أو سالبٌ يُقفل كلَّ سجلٍّ لحظةَ فتحه، وسقفُ يومين
        // لا يقفل شيئاً. فيُحبَس بين ساعةٍ وأربعٍ وعشرين.
        return max(1, min(24, $value));
    }

    /**
     * إقفالُ ما تجاوز السقف — يعمل مجدولاً كلَّ ساعة.
     *
     * ولا يُشترط له إعدادُ الإقفال الليليّ: ذاك يخترع وقتاً من آخر
     * نقرة، وهذا حدٌّ معلومٌ مقدَّماً يعرفه الموظّف والمدير معاً.
     *
     * @return int عددُ ما أُقفل
     */
    public static function closeOvertimeRecords(): int
    {
        $cap = self::capHours();
        $closed = 0;

        try {
            // السجلّاتُ المفتوحة قليلة (موظّفو مكتبٍ واحد)، والسقفُ يُقاس
            // من بداية الفترة المفتوحة (الاستئناف إن كان) لا من الحضور
            // الأوّل — وإلّا أُقفل المستأنَفُ عصراً في أوّل مسحة
            $records = HrAttendance::whereNull('check_out_at')
                ->get()
                ->filter(fn ($r) => $r->intervalStart()->lessThanOrEqualTo(now()->subHours($cap)));
        } catch (\Throwable $e) {
            Log::warning('attendance cap sweep failed', ['error' => $e->getMessage()]);

            return 0;
        }

        foreach ($records as $record) {
            try {
                $lastSeen = self::lastSeenAt($record->user_id);

                // ما زال على شاشته: يُترك، ويُعاد النظرُ في المسحة التالية
                if ($lastSeen && $lastSeen->greaterThan(now()->subMinutes(self::CAP_IDLE_MINUTES))) {
                    continue;
                }

                $out = $record->intervalStart()->copy()->addHours($cap);

                // رأيناه يعمل بعد السقف ثمّ غاب: آخرُ ما رأيناه لا يُقصّ
                if ($lastSeen && $lastSeen->greaterThan($out)) {
                    $out = $lastSeen;
                }

                self::close($record, $out, 'auto_capped');
                $closed++;
            } catch (\Throwable $e) {
                Log::warning('attendance cap close failed', [
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $closed;
    }

    /**
     * يُغلق سجلّات يومٍ بقيت مفتوحة — لمن فعّل الإقفال الليليّ.
     *
     * ووقتُ الانصراف يؤخذ من آخر نشاطٍ حقيقيّ للموظّف في جدول الجلسات لا
     * من ساعة تشغيل الأمر: من انصرف الثانية ظهراً لا يُكتب له أنه انصرف
     * منتصف الليل. ومن لا أثرَ لجلسته (انتهت صلاحيتها ومُسح صفُّها) يُقفل
     * سجلُّه على بداية فترته بصفر دقيقة — رقمٌ ظاهرُ الخطأ يُراجَع، خيرٌ
     * من رقمٍ مخترَعٍ يُصدَّق.
     *
     * والسجلّ يُوسم `auto_closed` فيعرف المكتب أن الوقت مستنتَجٌ لا مسجَّل.
     *
     * @return int عدد ما أُغلق
     */
    public static function closeStaleRecords(?CarbonInterface $for = null, bool $force = false): int
    {
        if (! $force && ! self::autoCloseEnabled()) {
            return 0;
        }

        $day = ($for ?? now())->toDateString();

        $records = HrAttendance::whereNull('check_out_at')
            ->whereDate('work_date', '<=', $day)
            ->get();

        $closed = 0;

        foreach ($records as $record) {
            try {
                // الانصراف لا يسبق بدايةَ الفترة مهما قال جدول الجلسات — close() تضمنه
                self::close($record, self::lastSeenAt($record->user_id) ?? $record->intervalStart(), 'auto_closed');
                $closed++;
            } catch (\Throwable $e) {
                Log::warning('attendance auto-close failed', [
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $closed;
    }

    /**
     * آخر نشاطٍ معروف للموظّف من جدول جلسات لارافيل.
     *
     * `last_activity` يتجدّد مع كل طلب، فهو أصدقُ ما يُعرف عن لحظة
     * انصرافه. وكاش «النشِط الآن» لا يصلح: عمرُه ثماني دقائق فينتهي قبل
     * أن يعمل الأمر بساعات.
     */
    private static function lastSeenAt(int $userId): ?CarbonInterface
    {
        if (! Schema::hasTable('sessions')) {
            return null;
        }

        $ts = DB::table('sessions')
            ->where('user_id', $userId)
            ->max('last_activity');

        // `createFromTimestamp` تُنشئ بـUTC، والتطبيق على توقيت المكتب —
        // فبلا تحويلٍ يُكتب الانصراف ناقصاً بفارق المنطقتين: أربع ساعات
        // في عُمان، فمن انصرف الثالثة والنصف يُسجَّل الحادية عشرة والنصف.
        return $ts
            ? Carbon::createFromTimestamp((int) $ts)->setTimezone(config('app.timezone'))
            : null;
    }

    /**
     * السجلّ المفتوح الذي يستحقّ سؤال «أما زلت حاضراً؟».
     *
     * لا يُسأل إلا من له حضورٌ بلا انصراف. ومن أجاب «استمرار» لا
     * يُسأل ثانيةً في هذه الجلسة — السؤال المتكرّر يصير إزعاجاً.
     */
    public static function openRecord(?User $user): ?HrAttendance
    {
        if (! self::tracks($user)) {
            return null;
        }

        try {
            return HrAttendance::openFor($user->id);
        } catch (\Throwable) {
            return null;
        }
    }

    /** حالة الموظف الآن — للعرض في لوحة المدير. */
    public static function statusOf(?HrAttendance $record): string
    {
        if (! $record) {
            return 'absent';
        }

        if ($record->check_out_at !== null) {
            return 'completed';
        }

        // «حاضرٌ» صفةُ يومه فقط. سجلُّ أمسِ المفتوح — والإقفال الليليّ
        // معطَّلٌ بطلب المكاتب — كان سيُعرض «ما زال حاضراً» إلى الأبد،
        // فيظهر الموظّف حاضراً منذ الثلاثاء في كشف الشهر.
        return $record->work_date?->isToday() ?? false ? 'present' : 'unclosed';
    }
}
