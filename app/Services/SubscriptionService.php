<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;

class SubscriptionService
{
    public const STATUS_NONE = 'none';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRING = 'expiring_soon';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED = 'expired';

    public const DURATION_OPTIONS = [1, 2, 3, 6, 12];

    public const EXPIRING_SOON_DAYS = 7;

    private function rawStatus(): string
    {
        return (string) Setting::get('subscription_status', self::STATUS_NONE);
    }

    private function startAt(): ?Carbon
    {
        $start = Setting::get('subscription_start_at');

        return $start ? Carbon::parse($start) : null;
    }

    private function endAt(): ?Carbon
    {
        $end = Setting::get('subscription_end_at');

        return $end ? Carbon::parse($end) : null;
    }

    public function isAllowed(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isDeveloper()) {
            return true;
        }

        return in_array($this->status(), [self::STATUS_ACTIVE, self::STATUS_EXPIRING], true);
    }

    /**
     * Source of truth: server time only (now() < subscription_end_at).
     */
    public function status(): string
    {
        $raw = $this->rawStatus();
        $end = $this->endAt();

        if ($raw === self::STATUS_NONE || $raw === '' || !$end) {
            return self::STATUS_NONE;
        }

        if ($raw === self::STATUS_SUSPENDED) {
            return self::STATUS_SUSPENDED;
        }

        if ($end->lessThanOrEqualTo(now())) {
            return self::STATUS_EXPIRED;
        }

        if ($this->daysRemaining() <= self::EXPIRING_SOON_DAYS) {
            return self::STATUS_EXPIRING;
        }

        return self::STATUS_ACTIVE;
    }

    public function info(): array
    {
        $status = $this->status();
        $start = $this->startAt();
        $end = $this->endAt();
        $duration = Setting::get('subscription_duration');
        $created = Setting::get('subscription_created_at');

        return [
            'key' => $status,
            'label' => match ($status) {
                self::STATUS_ACTIVE => 'نشط',
                self::STATUS_EXPIRING => 'قريب من الانتهاء',
                self::STATUS_EXPIRED => 'منتهي',
                self::STATUS_SUSPENDED => 'متوقف',
                default => 'لا يوجد اشتراك',
            },
            'color' => match ($status) {
                self::STATUS_ACTIVE => 'green',
                self::STATUS_EXPIRING => 'amber',
                self::STATUS_EXPIRED => 'red',
                self::STATUS_SUSPENDED => 'black',
                default => 'gray',
            },
            'allowed' => in_array($status, [self::STATUS_ACTIVE, self::STATUS_EXPIRING], true),
            'duration_months' => $duration ? (int) $duration : null,
            'is_custom_period' => !$duration && $end !== null,
            'start_at' => $start,
            'end_at' => $end,
            'created_at' => $created ? Carbon::parse($created) : null,
            'remaining_days' => $end ? $this->daysRemaining() : 0,
            'end_timestamp' => $end ? $end->timestamp : null,
        ];
    }

    public function daysRemaining(): int
    {
        $end = $this->endAt();

        if (!$end || $end->lessThanOrEqualTo(now())) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($end->copy()->endOfDay());
    }

    /**
     * Starts a fresh period from now. A custom end date replaces the month count.
     */
    public function activate(int $months, ?Carbon $customEnd = null): array
    {
        $start = now();
        $end = $customEnd ? $customEnd->copy() : $start->copy()->addMonthsNoOverflow($months);

        $this->writePeriod(self::STATUS_ACTIVE, $start, $end);
        Setting::set('subscription_created_at', now(), 'subscription');

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Adds onto whatever time is left rather than discarding it; a period that has
     * already run out is extended from now instead. The start date is preserved.
     */
    public function extend(int $months, ?Carbon $customEnd = null): array
    {
        $now = now();
        $currentEnd = $this->endAt();
        $base = ($currentEnd && $currentEnd->greaterThan($now)) ? $currentEnd->copy() : $now->copy();

        $start = $this->startAt() ?? $now->copy();
        $end = $customEnd ? $customEnd->copy() : $base->addMonthsNoOverflow($months);

        $this->writePeriod(self::STATUS_ACTIVE, $start, $end);

        return ['start' => $start, 'end' => $end, 'added_months' => $customEnd ? null : $months];
    }

    /**
     * Ends the subscription immediately. end_at has to move into the past because
     * status() recomputes from server time and ignores the stored status while the
     * period is still live.
     */
    public function expire(): void
    {
        Setting::set('subscription_status', self::STATUS_EXPIRED, 'subscription');
        Setting::set('subscription_end_at', now()->subMinute(), 'subscription');
        Setting::set('subscription_updated_at', now(), 'subscription');
    }

    /**
     * Stores a period and the duration that actually describes it, so the label
     * stays true after an extension (3 months extended by 2 reads as 5, not 2).
     * A span that is not a whole number of months is stored as a custom period.
     */
    private function writePeriod(string $status, Carbon $start, Carbon $end): void
    {
        Setting::set('subscription_status', $status, 'subscription');
        Setting::set('subscription_duration', self::wholeMonthsBetween($start, $end), 'subscription');
        Setting::set('subscription_start_at', $start, 'subscription');
        Setting::set('subscription_end_at', $end, 'subscription');
        Setting::set('subscription_updated_at', now(), 'subscription');
    }

    /**
     * Whole months from $start to $end, or null when the span does not land on a
     * month boundary (a custom end date usually will not).
     */
    public static function wholeMonthsBetween(Carbon $start, Carbon $end): ?int
    {
        $approx = ($end->year - $start->year) * 12 + ($end->month - $start->month);

        foreach ([$approx, $approx - 1, $approx + 1] as $months) {
            if ($months >= 1 && $months <= 120
                && $start->copy()->addMonthsNoOverflow($months)->equalTo($end)) {
                return $months;
            }
        }

        return null;
    }

    public function suspend(): void
    {
        Setting::set('subscription_status', self::STATUS_SUSPENDED, 'subscription');
        Setting::set('subscription_updated_at', now(), 'subscription');
    }

    public function reactivate(): void
    {
        Setting::set('subscription_status', self::STATUS_ACTIVE, 'subscription');
        Setting::set('subscription_updated_at', now(), 'subscription');
    }

    public static function durationLabel(?int $months): string
    {
        if (!$months) {
            return 'مدة مخصصة';
        }

        return match ($months) {
            1 => 'شهر واحد',
            2 => 'شهران',
            3 => '3 أشهر',
            6 => '6 أشهر',
            12 => 'سنة كاملة',
            default => $months . ' أشهر',
        };
    }

    public static function countdownParts(int $seconds): array
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return compact('days', 'hours', 'minutes');
    }

    public static function colorClasses(string $color): string
    {
        return match ($color) {
            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
            'red' => 'bg-red-50 text-red-700 border-red-200',
            'black' => 'bg-gray-100 text-gray-700 border-gray-300',
            'gray' => 'bg-gray-50 text-gray-500 border-gray-200',
            default => 'bg-gray-50 text-gray-600 border-gray-200',
        };
    }
}