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
 * قاعدتان تحكمان هذا الملف:
 *
 * ١ — فشل الحضور لا يمنع الدخول أبداً. الموظف يأتي ليعمل؛ وعطلٌ في
 *     جدول الحضور لا يجوز أن يقف بينه وبين قضيةٍ لها جلسةٌ اليوم.
 *     لذلك كل نداء هنا مغلَّفٌ ويُرجع null عند العطل بدل أن يرمي.
 *
 * ٢ — لا نكتب وقتاً لم نره. من أغلق المتصفح دون «تسجيل الانصراف»
 *     لا نعرف متى انصرف، فلا نخترع له وقتاً: يبقى سجلّه مفتوحاً
 *     وتُعرض عليه حالته حين يعود. آخر نشاطٍ معروف يُحفظ في السجلّ
 *     كإشارةٍ للمدير، ولا يُقدَّم قطُّ على أنه وقت انصراف.
 */
class AttendanceGuard
{
    /** الأدوار التي لها حضور — الموكّل ليس موظفاً في المكتب. */
    private const STAFF_ROLES = ['admin', 'lawyer', 'staff'];

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
     * @return array{record: HrAttendance, created: bool}|null
     */
    public static function checkInOnLogin(User $user): ?array
    {
        if (! self::tracks($user) || ! self::autoEnabled()) {
            return null;
        }

        try {
            $existing = HrAttendance::todayFor($user->id);

            if ($existing) {
                return ['record' => $existing, 'created' => false];
            }

            $record = HrAttendance::create([
                'user_id' => $user->id,
                'work_date' => HrAttendance::today(),
                'check_in_at' => now(),
                'status' => 'present',
                'source' => 'auto_login',
            ]);

            return ['record' => $record, 'created' => true];
        } catch (UniqueConstraintViolationException) {
            $existing = HrAttendance::todayFor($user->id);

            return $existing ? ['record' => $existing, 'created' => false] : null;
        } catch (\Throwable $e) {
            // جدولٌ ناقص أو قاعدةٌ لا تستجيب: يُسجَّل ويمضي الدخول
            Log::warning('attendance auto check-in failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * انصرافٌ عند الخروج المتعمَّد.
     *
     * يُنادى قبل إبطال الجلسة: بعدها لا يبقى مستخدمٌ مصادَقٌ ننسب
     * إليه السجلّ.
     */
    public static function checkOutOnLogout(?User $user): ?HrAttendance
    {
        if (! self::tracks($user)) {
            return null;
        }

        try {
            $record = HrAttendance::todayFor($user->id) ?? HrAttendance::openFor($user->id);

            if (! $record || $record->check_out_at !== null) {
                return null;
            }

            $record->update([
                'check_out_at' => now(),
                'minutes' => (int) $record->check_in_at->diffInMinutes(now()),
                'status' => 'completed',
            ]);

            return $record;
        } catch (\Throwable $e) {
            Log::warning('attendance check-out failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * يُغلق سجلّات يومٍ بقيت مفتوحة — لأن الانصراف لم يكن يُسجَّل أصلاً.
     *
     * ═══ العطل الذي وُضع له ═══
     *
     * الحضور يُسجَّل تلقائياً عند الدخول، والانصراف لا يُسجَّل إلا بضغط
     * «تسجيل خروج» أو زرِّ الانصراف اليدوي. والموظّف يُغلق المتصفّح ويمضي
     * — وهو الغالب — فيبقى السجلّ مفتوحاً أبداً: لا انصرافَ ولا دقائقَ
     * محسوبة، وسِجلُّ الشهر أعمدةٌ فارغة.
     *
     * ووقتُ الانصراف يؤخذ من آخر نشاطٍ حقيقيّ للموظّف في جدول الجلسات لا
     * من ساعة تشغيل الأمر: من انصرف الثانية ظهراً لا يُكتب له أنه انصرف
     * منتصف الليل. ومن لا أثرَ لجلسته (انتهت صلاحيتها ومُسح صفُّها) يُقفل
     * سجلُّه على حضوره نفسه بصفر دقيقة — رقمٌ ظاهرُ الخطأ يُراجَع، خيرٌ
     * من رقمٍ مخترَعٍ يُصدَّق.
     *
     * والسجلّ يُوسم `auto_closed` فيعرف المكتب أن الوقت مستنتَجٌ لا مسجَّل.
     *
     * @return int عدد ما أُغلق
     */
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
                $lastSeen = self::lastSeenAt($record->user_id);

                // الانصراف لا يسبق الحضور مهما قال جدول الجلسات
                $out = ($lastSeen && $lastSeen->greaterThan($record->check_in_at))
                    ? $lastSeen
                    : $record->check_in_at;

                $record->update([
                    'check_out_at' => $out,
                    'minutes' => (int) $record->check_in_at->diffInMinutes($out),
                    'status' => 'completed',
                    'source' => 'auto_closed',
                ]);

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
