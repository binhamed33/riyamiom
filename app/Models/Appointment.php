<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * موعدٌ بين المكتب وشخص.
 *
 * الوقتُ يُخزَّن كما كُتب ويُقرأ بتوقيت المكتب: مكتبٌ في مسقط يحجز
 * التاسعةَ صباحاً فيجب أن تبقى التاسعةَ في كلّ شاشةٍ وكلّ رسالة.
 */
class Appointment extends Model
{
    use SoftDeletes;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    /** الحالاتُ التي تشغل وقتاً في التقويم — الملغى لا يحجز فُسحة. */
    public const BUSY_STATUSES = [self::STATUS_SCHEDULED, self::STATUS_COMPLETED];

    public const STATUSES = [
        self::STATUS_SCHEDULED => 'مُثبَّت',
        self::STATUS_COMPLETED => 'تمّ',
        self::STATUS_CANCELLED => 'ملغى',
        self::STATUS_NO_SHOW => 'لم يحضر',
    ];

    protected $fillable = [
        'client_id', 'guest_name', 'guest_phone', 'guest_email',
        'case_id', 'user_id', 'title', 'starts_at',
        'minutes', 'location', 'notes', 'status', 'created_by', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'reminded_at' => 'datetime',
            'minutes' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    /** من يقابله في المكتب. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * صاحبُ الموعد: الموكّلُ المسجَّل إن وُجد، وإلا الشخصُ باسمه.
     *
     * ثلاثةُ توابعَ لا ثلاثةُ شروطٍ متناثرة: الاسمُ يُطبع في التقويم
     * وفي الرسالة وفي البريد، ولو تُرك لكلّ موضعٍ أن يقرّر لأظهر
     * أحدُها «—» حيث يُظهر الآخرُ اسماً.
     */
    public function personName(): string
    {
        return (string) ($this->client?->name ?: ($this->guest_name ?: '—'));
    }

    public function personPhone(): ?string
    {
        $phone = $this->client?->phone ?: $this->guest_phone;

        return filled($phone) ? (string) $phone : null;
    }

    public function personEmail(): ?string
    {
        $email = $this->client?->email ?: $this->guest_email;

        return filled($email) ? (string) $email : null;
    }

    /** أموعدٌ مع شخصٍ خارج سجلّ الموكّلين؟ */
    public function isGuest(): bool
    {
        return $this->client_id === null;
    }

    public function endsAt(): Carbon
    {
        return $this->starts_at->copy()->addMinutes(max(5, (int) $this->minutes));
    }

    public function isBusy(): bool
    {
        return in_array($this->status, self::BUSY_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** «الأحد ٧ سبتمبر ٢٠٢٦، ٩:٠٠ ص» — صيغةٌ واحدةٌ للشاشة والرسالة. */
    public function whenText(): string
    {
        $at = $this->starts_at->locale('ar');

        return $at->isoFormat('dddd D MMMM YYYY') . ' — ' . $at->isoFormat('h:mm a');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now())
            ->where('status', self::STATUS_SCHEDULED)
            ->orderBy('starts_at');
    }
}
