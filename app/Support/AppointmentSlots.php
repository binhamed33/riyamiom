<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * أوقاتُ المكتب، والفُسَحُ الشاغرةُ منها.
 *
 * ═══ لماذا تُحسب ولا تُخزَّن ═══
 *
 * الفُسحةُ ليست شيئاً يملكه المكتب، بل نتيجةُ ثلاثةِ أشياءَ تتغيّر:
 * أوقاتُ الدوام، وطولُ الموعد، وما حُجز فعلاً. وجدولُ فُسَحٍ مخزَّنٌ
 * يعني صفوفاً تُولَّد لكلّ يومٍ إلى الأبد ثمّ تكذب أوّلَ ما يغيّر
 * المكتبُ دوامَه أو يُلغى موعد.
 *
 * فتُحسب عند السؤال: يومٌ واحدٌ وموظّفٌ واحد، واستعلامٌ واحدٌ لما
 * حُجز فيه.
 *
 * ═══ والتعارضُ يُمنع عند الحفظ لا عند العرض ═══
 *
 * شاشتان مفتوحتان تريان الفُسحةَ نفسَها شاغرة، فيحجزها الاثنان.
 * ‏`isFree()` هو الحَكَم قبل الكتابة، والعرضُ راحةٌ لا حارس.
 */
class AppointmentSlots
{
    public const KEY_DAYS = 'appt_days';
    public const KEY_START = 'appt_start';
    public const KEY_END = 'appt_end';
    public const KEY_SLOT = 'appt_slot_minutes';
    public const KEY_REMIND = 'appt_remind_hours';

    /** الأحد إلى الخميس — أسبوعُ العمل في عُمان. (0 = الأحد) */
    public const DEFAULT_DAYS = '0,1,2,3,4';
    public const DEFAULT_START = '08:00';
    public const DEFAULT_END = '16:00';
    public const DEFAULT_SLOT = 30;
    public const DEFAULT_REMIND_HOURS = 24;

    public const DAY_NAMES = [
        0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
        4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
    ];

    /** @return array<int, int> أرقامُ أيّام العمل */
    public static function days(): array
    {
        $raw = (string) Setting::get(self::KEY_DAYS, self::DEFAULT_DAYS);

        $days = array_values(array_unique(array_filter(
            array_map('intval', array_filter(explode(',', $raw), static fn ($d) => $d !== '')),
            static fn ($d) => $d >= 0 && $d <= 6,
        )));

        // بلا يومِ عملٍ واحدٍ لا تُعرض فُسحةٌ أبداً وتبدو الشاشةُ معطّلة
        return $days !== [] ? $days : array_map('intval', explode(',', self::DEFAULT_DAYS));
    }

    public static function startTime(): string
    {
        return self::time(self::KEY_START, self::DEFAULT_START);
    }

    public static function endTime(): string
    {
        $end = self::time(self::KEY_END, self::DEFAULT_END);

        // نهايةٌ قبل البداية تُفرّغ اليومَ كلَّه بصمت
        return $end <= self::startTime() ? self::DEFAULT_END : $end;
    }

    public static function slotMinutes(): int
    {
        return max(5, min(240, (int) Setting::get(self::KEY_SLOT, self::DEFAULT_SLOT)));
    }

    public static function remindHours(): int
    {
        return max(1, min(168, (int) Setting::get(self::KEY_REMIND, self::DEFAULT_REMIND_HOURS)));
    }

    public static function isWorkday(Carbon $day): bool
    {
        return in_array($day->dayOfWeek, self::days(), true);
    }

    /**
     * فُسَحُ يومٍ لموظّف: كلُّ وقتٍ يبدأ فيه موعدٌ ولا يصطدم بمحجوز.
     *
     * @return array<int, array{time: string, at: Carbon, free: bool}>
     */
    public static function forDay(Carbon $day, ?int $userId = null, ?int $ignoreId = null): array
    {
        $day = $day->copy()->startOfDay();

        if (!self::isWorkday($day)) {
            return [];
        }

        $slot = self::slotMinutes();
        $cursor = self::at($day, self::startTime());
        $end = self::at($day, self::endTime());

        // ما حُجز في هذا اليوم — استعلامٌ واحدٌ لا واحدٌ لكلّ فُسحة
        $taken = Appointment::query()
            ->whereIn('status', Appointment::BUSY_STATUSES)
            ->whereBetween('starts_at', [$day->copy()->subDay(), $day->copy()->addDay()])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get(['id', 'starts_at', 'minutes']);

        $slots = [];
        $now = now();

        while ($cursor < $end) {
            $slotEnd = $cursor->copy()->addMinutes($slot);

            // فُسحةٌ تتجاوز نهايةَ الدوام ليست فُسحة
            if ($slotEnd > $end) {
                break;
            }

            $free = $cursor > $now;

            if ($free) {
                foreach ($taken as $busy) {
                    $busyEnd = $busy->starts_at->copy()->addMinutes(max(5, (int) $busy->minutes));

                    if ($cursor < $busyEnd && $slotEnd > $busy->starts_at) {
                        $free = false;

                        break;
                    }
                }
            }

            $slots[] = ['time' => $cursor->format('H:i'), 'at' => $cursor->copy(), 'free' => $free];
            $cursor->addMinutes($slot);
        }

        return $slots;
    }

    /**
     * أشاغرٌ هذا الوقتُ لهذا الموظّف؟ — الحَكَمُ قبل الحفظ.
     *
     * التداخلُ لا التطابق: موعدُ ساعةٍ في التاسعة يمنع التاسعةَ
     * والنصف أيضاً، ولو لم تكن فُسحةً مطابقة.
     */
    public static function isFree(Carbon $startsAt, int $minutes, ?int $userId, ?int $ignoreId = null): bool
    {
        if (!$userId) {
            return true; // بلا موظّفٍ محدَّد لا تعارضَ يُحسب
        }

        $endsAt = $startsAt->copy()->addMinutes(max(5, $minutes));

        return !Appointment::query()
            ->where('user_id', $userId)
            ->whereIn('status', Appointment::BUSY_STATUSES)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereBetween('starts_at', [$startsAt->copy()->subDay(), $endsAt->copy()->addDay()])
            ->get(['starts_at', 'minutes'])
            ->contains(function ($busy) use ($startsAt, $endsAt) {
                $busyEnd = $busy->starts_at->copy()->addMinutes(max(5, (int) $busy->minutes));

                return $startsAt < $busyEnd && $endsAt > $busy->starts_at;
            });
    }

    private static function at(Carbon $day, string $time): Carbon
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $day->copy()->setTime(max(0, min(23, $h)), max(0, min(59, $m)));
    }

    private static function time(string $key, string $default): string
    {
        $raw = trim((string) Setting::get($key, $default));

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw) ? $raw : $default;
    }
}
