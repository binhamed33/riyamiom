<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_TERMINATED = 'terminated';
    const STATUS_EXPIRED = 'expired';

    const STATUSES = [self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_TERMINATED, self::STATUS_EXPIRED];

    const DURATION_OPTIONS = [1, 2, 3, 6, 12];

    protected $fillable = [
        'tenant_id',
        'plan_duration_months',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            if (!$subscription->start_date) {
                $subscription->start_date = now()->startOfDay();
            }
            if (!$subscription->end_date && $subscription->plan_duration_months) {
                $subscription->end_date = self::endDateFor($subscription->start_date, $subscription->plan_duration_months);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->end_date->greaterThan(now());
    }

    public function isSuspended(): bool
    {
        return in_array($this->status, [self::STATUS_SUSPENDED, self::STATUS_TERMINATED], true);
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->status === self::STATUS_ACTIVE && $this->end_date->lessThanOrEqualTo(now()));
    }

    public function daysRemaining(): int
    {
        if ($this->end_date->lessThanOrEqualTo(now())) {
            return 0;
        }
        return (int) now()->startOfDay()->diffInDays($this->end_date->endOfDay());
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->isActive() && $this->daysRemaining() <= $days;
    }

    public function secondsRemaining(): int
    {
        $end = $this->end_date->endOfDay();
        if ($end->lessThanOrEqualTo(now())) {
            return 0;
        }
        return (int) $end->diffInSeconds(now(), true);
    }

    public function elapsedRatio(): float
    {
        $start = $this->start_date->startOfDay();
        $end = $this->end_date->endOfDay();
        $total = max(1, (int) $start->diffInSeconds($end));
        $elapsed = (int) $start->diffInSeconds(now()->min($end));
        return min(1.0, max(0.0, $elapsed / $total));
    }

    public static function endDateFor(Carbon|string $startDate, int $months): Carbon
    {
        return Carbon::parse($startDate)->startOfDay()->addMonthsNoOverflow($months);
    }
}