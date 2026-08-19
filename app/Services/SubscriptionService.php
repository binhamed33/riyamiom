<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

class SubscriptionService
{
    public function isAllowed(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isDeveloper()) {
            return true;
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return false;
        }

        return $this->hasValidSubscription($tenant);
    }

    public function hasValidSubscription(Tenant $tenant): bool
    {
        $subscription = $tenant->subscription;

        if (!$subscription) {
            return false;
        }

        return $subscription->isActive();
    }

    public function statusFor(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        if ($user->isDeveloper()) {
            return [
                'key' => 'system',
                'label' => 'مالك النظام',
                'color' => 'purple',
                'allowed' => true,
                'subscription' => null,
                'remaining_days' => null,
                'end_date' => null,
            ];
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return $this->statusForTenant(null, null);
        }

        return $this->statusForTenant($tenant, $tenant->subscription);
    }

    public function statusForTenant(?Tenant $tenant, ?Subscription $subscription = null): array
    {
        if (!$tenant || !$subscription) {
            return [
                'key' => 'none',
                'label' => 'لا يوجد اشتراك',
                'color' => 'gray',
                'allowed' => false,
                'subscription' => $subscription,
                'remaining_days' => 0,
                'end_date' => null,
            ];
        }

        if ($subscription->isSuspended()) {
            return [
                'key' => 'suspended',
                'label' => 'متوقف يدويًا',
                'color' => 'black',
                'allowed' => false,
                'subscription' => $subscription,
                'remaining_days' => 0,
                'end_date' => $subscription->end_date,
            ];
        }

        if ($subscription->isExpired()) {
            return [
                'key' => 'expired',
                'label' => 'منتهي',
                'color' => 'red',
                'allowed' => false,
                'subscription' => $subscription,
                'remaining_days' => 0,
                'end_date' => $subscription->end_date,
            ];
        }

        $days = $subscription->daysRemaining();

        if ($subscription->isExpiringSoon()) {
            return [
                'key' => 'expiring_soon',
                'label' => 'قريب من الانتهاء',
                'color' => 'amber',
                'allowed' => true,
                'subscription' => $subscription,
                'remaining_days' => $days,
                'end_date' => $subscription->end_date,
            ];
        }

        return [
            'key' => 'active',
            'label' => 'نشط',
            'color' => 'green',
            'allowed' => true,
            'subscription' => $subscription,
            'remaining_days' => $days,
            'end_date' => $subscription->end_date,
        ];
    }

    public static function durationLabel(int $months): string
    {
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
            'purple' => 'bg-purple-50 text-purple-700 border-purple-200',
            default => 'bg-gray-50 text-gray-600 border-gray-200',
        };
    }
}