<?php

namespace App\Support;

use App\Models\AuditLog;
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
 * أربع قواعد تحكم هذا الملف:
 *
 * ١ — فشل الحضور لا يمنع الدخول أبداً. الموظف يأتي ليعمل؛ وعطلٌ في
 *     جدول الحضور لا يجوز أن يقف بينه وبين قضيةٍ لها جلسةٌ اليوم.
 *     لذلك كل نداء هنا مغلَّفٌ ويُرجع null عند العطل بدل أن يرمي.
 *
 * ٢ — لا نكتب وقتاً لم نره. من أغلق المتصفح دون «تسجيل الانصراف»
 *     لا نعرف متى انصرف، فلا نخترع له وقتاً. وما يكتبه النظامُ مكانَه
 *     (سقفٌ أو آخرُ نشاط) يُوسم بكاتبه في closed_by ليُقرأ على حقيقته.
 *
 * ٣ — الانصرافُ بزرّه وحده، والخروجُ من النظام ليس انصرافاً. الموظّف
 *     يخرج من النظام ليدخل من جهازٍ آخر، أو يقفل الجهازَ في استراحة
 *     الظهر ويعود عصراً — وهو في دوامه. ومن انصرف ثمّ عاد يُستأنف يومُه.
 *
 * ٤ — يومٌ واحد لكلّ موظّف مهما تعدّدت فتراتُه، والسقفُ سقفُ اليوم
 *     لا الفترة: فترتان بسقفٍ لكلٍّ كانتا تُعطيان ستَّ عشرةَ ساعة.
 */
class AttendanceGuard
{
    /** الأدوار التي لها حضور — الموكّل ليس موظفاً في المكتب. */
    private const STAFF_ROLES = ['admin', 'lawyer', 'staff'];

    /**
     * استراحةٌ لا يومٌ انقضى: إقفالٌ آليٌّ (سقفٌ أو آخرُ نشاط) وقع قبل
     * أقلَّ من هذا يُستأنف تلقائيّاً عند الدخول التالي — فهو خطأُ النظام
     * لا قرارُ الموظّف.
     *
     * أمّا من ضغط الانصرافَ بيده فلا يُفتح يومُه خلسةً: محامٍ انصرف
     * الواحدةَ والنصف ودخل من هاتفه الثامنةَ ليقرأ حكماً دقيقتين كان
     * يجد يومَه مفتوحاً ثمّ مقفَلاً بالسقف بساعاتٍ لم يعملها. يُعرض عليه
     * زرُّ «استئناف الدوام» ويقرّر هو.
     */
    public const RESUME_WINDOW_HOURS = 6;

    /**
     * من رآه الخادمُ في آخر ساعة لا يُقفل سجلُّه بالسقف.
     *
     * ساعةٌ لأنّها مهلةُ الخمول في الواجهة: من غاب عن الشاشة ساعةً أُخرج
     * من النظام — فمن ما زال أثرُه حيّاً فهو أمام شاشته.
     */
    public const CAP_IDLE_MINUTES = 60;

    /**
     * حدُّ اليوم للإقفال الآليّ: السادسةُ صباحَ اليوم التالي.
     *
     * انصرافٌ مستنتَجٌ لا يتجاوزه: من حضر الحاديةَ عشرةَ ليلاً لملفٍّ
     * عاجل يُقفل يومُه في اليوم التالي صباحاً لا ظهراً، ونشاطٌ رُئي بعد
     * هذا الحدّ هو يومٌ آخر لا امتدادٌ لهذا.
     */
    public const DAY_END_HOUR = 6;

    /** مفتاحُ كاش آخر مسحةِ سقف — يقرؤه office:health. */
    public const SWEEP_STAMP = 'hr_attendance_last_sweep';

    public static function tracks(?User $user): bool
    {
        return $user !== null
            && $user->is_active
            && in_array($user->role, self::STAFF_ROLES, true);
    }

    /**
     * هل فعّل المكتب الحضور التلقائي عند الدخول؟
     *
     * ووحدةُ الموارد البشريّة المعطَّلة (feature_hr = 1) تعطّله معها: كانت
     * الدخولاتُ تكتب سجلّاتٍ لا يراها أحد، ويظهر إشعارُ الحضور بزرّين
     * يرتدّان إلى «قيد التطوير».
     */
    public static function autoEnabled(): bool
    {
        return (string) Setting::get('hr_auto_checkin', '1') !== '0'
            && (string) Setting::get('feature_hr', '0') !== '1';
    }

    /**
     * دخولٌ عابر لا دوام: يُلغى حضورٌ تلقائيٌّ لم تمضِ عليه نصفُ ساعة.
     *
     * محامٍ دخل من بيته العاشرةَ ليلاً ليقرأ ملفّاً فُتح له يومٌ يبلغ السقفَ
     * صباحاً بثماني ساعاتٍ لم يعملها. الشروطُ ضيّقةٌ عمداً: سجلُّه هو،
     * مفتوح، أنشأه الدخول لا الزرّ، فترةٌ واحدة، وحضورُه قبل أقلَّ من
     * نصف ساعة — فلا يُحذف شيءٌ عُمل.
     */
    public static function cancellable(?HrAttendance $record): bool
    {
        return $record !== null
            && $record->check_out_at === null
            && $record->resumed_at === null
            && (int) ($record->intervals ?: 1) === 1
            && $record->source === 'auto_login'
            && $record->check_in_at->greaterThan(now()->subMinutes(30));
    }

    public static function cancelAutoCheckIn(?User $user): bool
    {
        if (! self::tracks($user)) {
            return false;
        }

        try {
            // السجلُّ نفسُه الذي يعرضه الإشعار: المفتوحُ ولو كان حضورُه قبل منتصف الليل بدقائق
            $record = HrAttendance::openFor($user->id) ?? HrAttendance::todayFor($user->id);

            if (! self::cancellable($record)) {
                return false;
            }

            // حذفٌ بشرطٍ دقيق: سجلُّ صاحبِه، مفتوح، تلقائيّ، وحديثٌ
            $deleted = HrAttendance::whereKey($record->id)->whereNull('check_out_at')->where('source', 'auto_login')->delete() === 1;

            if ($deleted) {
                self::audit($record, 'attendance_cancel', ['check_in_at' => $record->check_in_at->toDateTimeString()], ['cancelled' => true]);
            }

            return $deleted;
        } catch (\Throwable $e) {
            Log::warning('attendance cancel failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * حضورٌ عند الدخول — مرّة واحدة في اليوم مهما تكرّر الدخول.
     *
     * القيد الفريد (user_id, work_date) هو الحارس الحقيقي: فحصٌ قبل
     * الإنشاء لا يكفي حين يدخل الموظف من جهازين في اللحظة نفسها،
     * فنمسك اصطدام القيد ونعيد السجلّ القائم بدل أن نُفشل الدخول.
     *
     * وسجلُّ أمسِ المفتوح (دخولٌ الحاديةَ عشرةَ ليلاً وعودةٌ بعد منتصف
     * الليل) هو سجلُّ هذا الدخول لا سجلٌّ جديد — وإلّا بقي الأوّل مفتوحاً
     * أبداً وأُقفل الثاني وحدَه.
     *
     * @return array{record: HrAttendance, created: bool, resumed: bool}|null
     */
    public static function checkInOnLogin(User $user): ?array
    {
        if (! self::tracks($user) || ! self::autoEnabled()) {
            return null;
        }

        try {
            $existing = HrAttendance::todayFor($user->id) ?? self::openWithinDay($user->id);

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

    /**
     * سجلُّ أمسِ المفتوح ما دام يومُه لم يبلغ حدَّه: من حضر الحاديةَ عشرةَ ليلاً
     * وعاد بعد منتصف الليل يواصل يومَه؛ أمّا من دخل صباحَ الغد فيومٌ جديد —
     * وإلّا بقي يومُ الثلاثاء المنسيّ سجلَّ الأربعاء وضاع حضورُ الأربعاء.
     */
    public static function openWithinDay(int $userId): ?HrAttendance
    {
        $open = HrAttendance::openFor($userId);

        return $open && now()->lessThanOrEqualTo(self::dayEnd($open)) ? $open : null;
    }

    /** إقفالٌ آليٌّ قريب (استراحة) لا انصرافٌ بالزرّ ولا يومٌ انقضى — انظر RESUME_WINDOW_HOURS. */
    private static function shouldAutoResume(HrAttendance $record): bool
    {
        return $record->check_out_at !== null
            && $record->closedByInference()
            && $record->check_out_at->greaterThan(now()->subHours(self::RESUME_WINDOW_HOURS));
    }

    /**
     * انصرافٌ مقفَلٌ قبل قليل — يُعرض على صاحبه استئنافُه ولا يُفتح خلسةً.
     * وما صحّحه الإداري ليس للموظّف أن يفتحه فوق التصحيح.
     */
    public static function resumable(?HrAttendance $record): bool
    {
        return $record !== null
            && $record->check_out_at !== null
            && $record->closed_by !== HrAttendance::CLOSED_BY_MANAGER
            && $record->check_out_at->greaterThan(now()->subHours(self::RESUME_WINDOW_HOURS));
    }

    /**
     * انصرافٌ بالزرّ الصريح — الطريقُ الوحيد لإقفال اليوم بيد صاحبه.
     *
     * المدّةُ تُحسب من بداية الفترة المفتوحة وتُضاف إلى ما قبلها: من
     * استأنف عصراً لا تُحسب له استراحةُ الظهر دواماً. والسجلُّ المفتوح
     * من أمس (ليلةُ عمل) يُقفل هو، لا سجلُّ اليوم المقفَل.
     */
    public static function checkOut(?User $user): ?HrAttendance
    {
        if (! self::tracks($user)) {
            return null;
        }

        try {
            $record = HrAttendance::openFor($user->id) ?? HrAttendance::todayFor($user->id);

            if (! $record || $record->check_out_at !== null) {
                return null;
            }

            return self::close($record, now(), HrAttendance::CLOSED_BY_BUTTON);
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
     *
     * والحدُّ في الخادم لا في الزرّ وحده: انصرافٌ مضى عليه أكثرُ من نافذة
     * الاستئناف يومٌ انقضى، وما صحّحه الإداري لا يفتحه الموظّفُ فوق تصحيحه.
     */
    public static function resume(?User $user): ?HrAttendance
    {
        if (! self::tracks($user)) {
            return null;
        }

        try {
            $record = HrAttendance::todayFor($user->id);

            if (! self::resumable($record) || $record->closed_by === HrAttendance::CLOSED_BY_MANAGER) {
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
        $previous = match ($record->closed_by) {
            HrAttendance::CLOSED_BY_CAP => 'إقفالٍ بالسقف',
            HrAttendance::CLOSED_BY_SEEN => 'إقفالٍ بآخر نشاط',
            HrAttendance::CLOSED_BY_MANAGER => 'تصحيحِ الإداري',
            default => 'انصرافٍ بالزرّ',
        };
        $trail = 'استُؤنف ' . now()->timezone($tz)->format('h:i A')
            . ' بعد ' . $previous . ' ' . $record->check_out_at->timezone($tz)->format('h:i A');

        $record->update([
            'resumed_at' => now(),
            'check_out_at' => null,
            'closed_by' => null,
            // العمودُ بايتٌ واحد: سكربتٌ عالقٌ يستأنف ويُقفل بالتناوب كان يُسقط MySQL بخطأٍ لا يُفهم
            'intervals' => min(255, (int) ($record->intervals ?: 1) + 1),
            'status' => 'present',
            'note' => self::trail($record->note, $trail),
        ]);

        // الاستئنافُ نفسُه نشاطٌ مرئيّ: ما بقي من السقف قد يكون صفراً، فلولا
        // الختمُ أُقفل من ضغط الاستئنافَ قبل دقيقةٍ في المسحة التالية وهو يكتب
        self::stampSeen($record->user);

        return $record;
    }

    /** الملاحظةُ ٢٥٥ حرفاً: يُحتفظ بأحدث الأثر لا بأقدمه. */
    private static function trail(?string $note, string $entry): string
    {
        $note = trim(($note ? $note . ' · ' : '') . $entry);

        return mb_strlen($note) > 255 ? mb_substr($note, -255) : $note;
    }

    /**
     * إقفالٌ واحدٌ لكلّ الطرق: الزرّ والسقف وآخرُ النشاط والتصحيح.
     *
     * الدقائقُ من بداية الفترة المفتوحة إلى وقت الانصراف مضافةً إلى
     * المقفَل قبلها، والانصرافُ لا يسبق بدايةَ الفترة مهما قال المصدر.
     * والكاتبُ يُحفظ في closed_by وفي أثر الملاحظة، ويُدوَّن في سجلّ
     * التدقيق — ليُجاب بعد أسبوع «ما الذي كتب ١:٣٦؟».
     */
    private static function close(HrAttendance $record, CarbonInterface $out, string $closedBy): HrAttendance
    {
        $start = $record->intervalStart();
        $out = $out->greaterThan($start) ? $out : $start;
        $tz = 'Asia/Muscat';

        $entry = match ($closedBy) {
            HrAttendance::CLOSED_BY_CAP => 'أُقفل بالسقف ' . $out->copy()->timezone($tz)->format('h:i A'),
            HrAttendance::CLOSED_BY_SEEN => 'أُقفل بآخر نشاط ' . $out->copy()->timezone($tz)->format('h:i A'),
            default => null,
        };

        $before = ['check_out_at' => $record->check_out_at?->toDateTimeString(), 'minutes' => $record->minutes, 'closed_by' => $record->closed_by];

        $intervalMinutes = (int) $start->diffInMinutes($out);
        $inferred = in_array($closedBy, [HrAttendance::CLOSED_BY_CAP, HrAttendance::CLOSED_BY_SEEN], true) ? $intervalMinutes : 0;

        $data = [
            'check_out_at' => $out,
            'resumed_at' => null,
            'minutes' => $record->bankedMinutes() + $intervalMinutes,
            // ما كتبه النظامُ يبقى محسوباً كذلك بعد استئنافٍ وإقفالٍ بالزرّ
            'inferred_minutes' => (int) $record->inferred_minutes + $inferred,
            'status' => 'completed',
            'closed_by' => $closedBy,
            'note' => $entry ? self::trail($record->note, $entry) : $record->note,
            'updated_at' => now(),
        ];

        // إقفالٌ مشروط: مسحةُ السقف تقرأ السجلَّ ثمّ تكتبه، وبينهما قد يضغط
        // صاحبُه الانصرافَ — فيُكتب فوق ضغطته وقتٌ مستنتَج. الشرطُ يجعل
        // الأوّلَ يفوز والثاني لا يمسّ شيئاً.
        if (HrAttendance::whereKey($record->id)->whereNull('check_out_at')->update($data) !== 1) {
            return $record->refresh();
        }

        $record->refresh();

        self::audit($record, 'attendance_close', $before, [
            'check_out_at' => $record->check_out_at->toDateTimeString(), 'minutes' => $record->minutes, 'closed_by' => $closedBy,
        ]);

        return $record;
    }

    /** سطرُ تدقيقٍ لا يُسقط الإقفالَ إن تعذّر. */
    private static function audit(HrAttendance $record, string $action, array $old, array $new): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => HrAttendance::class,
                'model_id' => $record->id,
                'old_values' => $old + ['employee_id' => $record->user_id, 'work_date' => $record->work_date?->toDateString()],
                'new_values' => $new,
                'ip_address' => request()?->ip(),
                'user_agent' => app()->runningInConsole() ? 'console' : request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('attendance audit failed', ['record_id' => $record->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * تصحيحُ الإداري: الحضورُ والانصرافُ بوقتين مسجَّلين، بسببٍ مكتوب.
     *
     * يُكتب اليومُ فترةً واحدة (الحضورُ الأوّل → الانصراف)، وتُحسب دقائقُه
     * منهما، ويُوسم «مصحَّح» ويُدوَّن القديمُ والجديدُ والسببُ ومَن صحّح —
     * فالكشفُ يبقى صادقاً عن مصدر كلّ رقم.
     */
    public static function correct(HrAttendance $record, User $manager, CarbonInterface $in, CarbonInterface $out, string $reason): HrAttendance
    {
        $tz = 'Asia/Muscat';
        $before = [
            'check_in_at' => $record->check_in_at?->toDateTimeString(),
            'check_out_at' => $record->check_out_at?->toDateTimeString(),
            'minutes' => $record->minutes, 'intervals' => $record->intervals, 'closed_by' => $record->closed_by,
        ];

        $entry = 'صحّحه ' . $manager->name . ' ' . now()->timezone($tz)->format('Y-m-d')
            . ': كان ' . ($record->check_in_at?->timezone($tz)->format('h:i A') ?? '—') . ' → ' . ($record->check_out_at?->timezone($tz)->format('h:i A') ?? '—')
            . '، صار ' . $in->copy()->timezone($tz)->format('h:i A') . ' → ' . $out->copy()->timezone($tz)->format('h:i A')
            . ' — ' . $reason;

        $record->update([
            'check_in_at' => $in,
            'check_out_at' => $out,
            'resumed_at' => null,
            'minutes' => (int) $in->diffInMinutes($out),
            'inferred_minutes' => 0,
            'intervals' => 1,
            'status' => 'completed',
            'closed_by' => HrAttendance::CLOSED_BY_MANAGER,
            'note' => self::trail($record->note, $entry),
        ]);

        self::audit($record, 'attendance_correct', $before, [
            'check_in_at' => $in->toDateTimeString(), 'check_out_at' => $out->toDateTimeString(),
            'minutes' => $record->minutes, 'reason' => $reason, 'by' => $manager->id,
        ]);

        return $record;
    }

    /**
     * يومٌ يضيفه الإداري لموظّفٍ لم يدخل النظام — يومُ محكمةٍ أو جهازٌ معطَّل.
     *
     * كان يُقرأ «غائباً» ويسقط من مجموع الشهر ولا سبيلَ إلّا المطوّر. يُكتب
     * بوقتين وسببٍ وباسم من أضافه، ويُوسم «مصحَّح» فلا يُقرأ كأنّه ضغطةُ صاحبه.
     * ويومٌ له سجلٌّ أصلاً يُردّ — القيدُ الفريد يومٌ لكلّ موظّف.
     */
    public static function addDay(User $employee, User $manager, CarbonInterface $in, CarbonInterface $out, string $reason): ?HrAttendance
    {
        if (! self::tracks($employee)) {
            return null;
        }

        try {
            $tz = 'Asia/Muscat';
            $record = HrAttendance::create([
                'user_id' => $employee->id,
                'work_date' => $in->copy()->timezone($tz)->toDateString(),
                'check_in_at' => $in,
                'check_out_at' => $out,
                'minutes' => (int) $in->diffInMinutes($out),
                'status' => 'completed',
                'source' => 'manual',
                'closed_by' => HrAttendance::CLOSED_BY_MANAGER,
                'note' => 'أضافه ' . $manager->name . ' ' . now()->timezone($tz)->format('Y-m-d') . ': '
                    . $in->copy()->timezone($tz)->format('h:i A') . ' → ' . $out->copy()->timezone($tz)->format('h:i A') . ' — ' . $reason,
            ]);

            self::audit($record, 'attendance_add', [], [
                'check_in_at' => $in->toDateTimeString(), 'check_out_at' => $out->toDateTimeString(),
                'minutes' => $record->minutes, 'reason' => $reason, 'by' => $manager->id,
            ]);

            return $record;
        } catch (UniqueConstraintViolationException) {
            return null;
        }
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
     * سقفُ اليوم: ثماني ساعاتٍ من الحضور ثمّ يُقفل السجلّ.
     *
     * ═══ ما يُصلحه ═══
     *
     * الانصرافُ بزرّه وحده — وهذه قاعدةٌ تبقى. لكنّ الغالب أن يُغلق
     * الموظّفُ المتصفّح ويمضي، فيبقى سجلُّه مفتوحاً إلى الأبد: لا
     * انصرافَ ولا دقائقَ محسوبة، ويُعرض في كشف الشهر «لم يُسجَّل».
     * ومن دخل الأحد يبقى «مفتوحاً» يوم الخميس.
     *
     * فالسقفُ حدٌّ لا تخمين: حين تبلغ دقائقُ اليوم (المقفَلُ منها والمفتوح)
     * السقفَ يُقفل على ذلك الحدّ. سقفُ اليوم لا الفترة — فترتان بسقفٍ
     * لكلٍّ كانتا تُعطيان ستَّ عشرةَ ساعة.
     *
     * ═══ ولا يُقفل على من ما زال يعمل ═══
     *
     * كان يُقفل بالساعة وحدها، فمن حضر السابعةَ وُجد الثالثةَ «يومك
     * مكتمل» وهو على شاشته. فمن رُئي في آخر ساعةٍ يُترك، ومن رُئي بعد
     * السقف ثمّ غاب يُقفل على آخر ما رُئي (موسوماً «آخر نشاط») — الوقتُ
     * الذي رأيناه لا يُقصّ. وما دون السقف لا يُستنتج من نقرة.
     *
     * ═══ ولماذا وسمٌ مستقلّ ═══
     *
     * closed_by يقول للمدير: هذا وقتٌ بلغ السقف، لا وقتٌ ضغطه صاحبُه.
     * فمن نسي الانصراف يُراجَع سجلُّه ويُصحَّح، ولا يُقرأ الرقمُ كأنّه
     * شهادةُ حضورٍ موقّعة.
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
     * اللحظةُ التي يبلغ فيها اليومُ سقفَه: بدايةُ الفترة + ما بقي من السقف.
     */
    public static function capMoment(HrAttendance $record): CarbonInterface
    {
        $remaining = max(0, self::capHours() * 60 - $record->bankedMinutes());

        return $record->intervalStart()->copy()->addMinutes($remaining);
    }

    /**
     * إقفالُ ما بلغ سقفَ اليوم — يعمل مجدولاً كلَّ ساعة.
     *
     * ولا يُشترط له إعدادُ الإقفال الليليّ: ذاك يخترع وقتاً من آخر
     * نقرة، وهذا حدٌّ معلومٌ مقدَّماً يعرفه الموظّف والمدير معاً.
     *
     * @return int عددُ ما أُقفل
     */
    public static function closeOvertimeRecords(): int
    {
        $closed = 0;

        try {
            // السجلّاتُ المفتوحة قليلة (موظّفو مكتبٍ واحد)، والسقفُ يُقاس
            // على اليوم كلِّه لا على الفترة المفتوحة وحدها
            $records = HrAttendance::whereNull('check_out_at')
                ->get()
                ->filter(fn ($r) => self::capMoment($r)->lessThanOrEqualTo(now()));
        } catch (\Throwable $e) {
            Log::warning('attendance cap sweep failed', ['error' => $e->getMessage()]);

            return 0;
        }

        foreach ($records as $record) {
            try {
                $lastSeen = self::lastSeenFor($record);

                // ما زال على شاشته: يُترك، ويُعاد النظرُ في المسحة التالية
                if ($lastSeen && $lastSeen->greaterThan(now()->subMinutes(self::CAP_IDLE_MINUTES))) {
                    continue;
                }

                $out = self::capMoment($record);
                $closedBy = HrAttendance::CLOSED_BY_CAP;

                // رأيناه يعمل بعد السقف ثمّ غاب: آخرُ ما رأيناه لا يُقصّ
                if ($lastSeen && $lastSeen->greaterThan($out)) {
                    $out = $lastSeen;
                    $closedBy = HrAttendance::CLOSED_BY_SEEN;
                }

                self::close($record, self::boundToDay($record, $out), $closedBy);
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
     * ووقتُ الانصراف يؤخذ من آخر نشاطٍ بشريٍّ للموظّف في يومه لا من ساعة
     * تشغيل الأمر: من انصرف الثانية ظهراً لا يُكتب له أنه انصرف منتصف
     * الليل. ومن لا أثرَ له في يومه يُترك مفتوحاً — «بلا انصراف» في
     * الكشف صدقٌ يُصحَّح، وصفرُ دقائق كذبٌ يُصدَّق — وسقفُ اليوم يُقفله
     * في مسحته إن لم يُصحَّح.
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
                $lastSeen = self::lastSeenFor($record);

                // لا أثرَ — أو ما زال على شاشته (محامٍ يعدّ مذكّرةً قربَ منتصف الليل)
                if (! $lastSeen || $lastSeen->greaterThan(now()->subMinutes(self::CAP_IDLE_MINUTES))) {
                    continue;
                }

                self::close($record, $lastSeen, HrAttendance::CLOSED_BY_SEEN);
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

    /** حدُّ اليوم للإقفال الآليّ — انظر DAY_END_HOUR. */
    private static function dayEnd(HrAttendance $record): CarbonInterface
    {
        $date = $record->work_date?->copy() ?? $record->check_in_at->copy()->startOfDay();

        return Carbon::parse($date->toDateString(), config('app.timezone'))->addDay()->setTime(self::DAY_END_HOUR, 0);
    }

    private static function boundToDay(HrAttendance $record, CarbonInterface $out): CarbonInterface
    {
        $end = self::dayEnd($record);

        return $out->greaterThan($end) ? $end : $out;
    }

    /**
     * آخرُ نشاطٍ بشريٍّ للموظّف داخل يوم هذا السجلّ — أو لا شيء.
     *
     * يُقرأ من users.last_seen_at (يكتبه وسيطُ TrackUserActivity من الطلبات
     * البشريّة ويُختم عند الخروج) لا من جدول الجلسات: ذاك يُحذف بالخروج
     * ويُكنَس بعد ساعتين وتُجدّده نبضةُ المزامنة بلا إنسان. ويُقبل فقط
     * ما وقع بعد بداية الفترة وقبل حدّ اليوم — نشاطُ الأربعاء لا يُقفل
     * به سجلُّ الاثنين المنسيّ بسبعةٍ وأربعين ساعة.
     */
    private static function lastSeenFor(HrAttendance $record): ?CarbonInterface
    {
        $seen = self::lastSeenAt($record->user_id);

        if (! $seen) {
            return null;
        }

        return $seen->greaterThanOrEqualTo($record->intervalStart()) && $seen->lessThanOrEqualTo(self::dayEnd($record))
            ? $seen
            : null;
    }

    private static function lastSeenAt(int $userId): ?CarbonInterface
    {
        try {
            if (Schema::hasColumn('users', 'last_seen_at')) {
                $ts = DB::table('users')->where('id', $userId)->value('last_seen_at');

                // العمودُ موجودٌ ولا ختمَ بعد: لا أثرَ — ولا يُقرأ جدولُ الجلسات مكانه،
                // فنبضةُ المزامنة تجدّده بلا إنسان وكانت تُبقي المنسيَّ «حاضراً»
                return $ts ? Carbon::parse($ts, config('app.timezone')) : null;
            }
        } catch (\Throwable) {
            // يُكمَل بجدول الجلسات
        }

        // قبل الهجرة (لا عمود) يُقرأ جدولُ الجلسات كما كان
        if (! Schema::hasTable('sessions')) {
            return null;
        }

        $ts = DB::table('sessions')->where('user_id', $userId)->max('last_activity');

        // `createFromTimestamp` تُنشئ بـUTC، والتطبيق على توقيت المكتب —
        // فبلا تحويلٍ يُكتب الانصراف ناقصاً بفارق المنطقتين: أربع ساعات
        // في عُمان، فمن انصرف الثالثة والنصف يُسجَّل الحادية عشرة والنصف.
        return $ts
            ? Carbon::createFromTimestamp((int) $ts)->setTimezone(config('app.timezone'))
            : null;
    }

    /**
     * ختمُ آخر نشاطٍ عند الخروج من النظام — قبل أن تموت الجلسة.
     *
     * لحظةُ الخروج آخرُ لحظةٍ رأينا فيها الموظّفَ يقيناً؛ ولولا الختم لضاع
     * أثرُه مع صفّ الجلسة وأُقفل سجلُّه بالسقف قبل خروجه الحقيقيّ.
     */
    public static function stampSeen(?User $user): void
    {
        if (! self::tracks($user)) {
            return;
        }

        try {
            if (Schema::hasColumn('users', 'last_seen_at')) {
                DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning('attendance seen stamp failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
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

        if ($record->check_out_at !== null) {
            return 'completed';
        }

        // «حاضرٌ» صفةُ يومه — أو ليلتِه: من حضر الحاديةَ عشرةَ ليلاً وما
        // زال يعمل بعد منتصف الليل حاضرٌ لا «بلا انصراف». أمّا سجلُّ
        // الثلاثاء المفتوح يوم الخميس فيُقال عنه الصدق.
        return ($record->work_date?->isToday() ?? false) || $record->intervalStart()->greaterThan(now()->subDay())
            ? 'present'
            : 'unclosed';
    }
}
