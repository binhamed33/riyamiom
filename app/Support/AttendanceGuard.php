<?php

namespace App\Support;

use App\Models\HrAttendance;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

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

        return $record->check_out_at === null ? 'present' : 'completed';
    }
}
