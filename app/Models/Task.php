<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'title',
        'description',
        'case_id',
        'assigned_to',
        'created_by',
        'created_via',
        'status',
        'priority',
        'due_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            // «timestamp» يُرجع عدداً صحيحاً لا تاريخاً، والعمود نفسه
            // يخزّن تاريخاً سليماً. فكان كل ما ينادي ->format() على
            // تاريخ الإنجاز ينكسر: تصدير المهام كان يسقط كلّما وُجدت
            // مهمة واحدة منجَزة. لا شيء في القاعدة يتغيّر — القراءة
            // وحدها هي التي كانت خاطئة.
            'completed_at' => 'datetime',
        ];
    }

    /**
     * المتأخّرةُ ما كان يومُ استحقاقها قبل اليوم (بتوقيت مسقط) — لا ما مضت ساعتُه.
     *
     * ‏due_date يُحفظ «Y-m-d 00:00:00»، فكانت المقارنةُ بـnow() تجعل مهمّةَ
     * اليوم متأخّرةً منذ منتصف الليل: «متأخرة 0 يوم» في مركز الانتباه،
     * و«مهمة متأخرة» في الموجز، و«متأخر» أحمرُ بجانب تاريخ اليوم.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', self::STATUS_COMPLETED)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->status !== self::STATUS_COMPLETED
            && $this->due_date !== null
            && $this->due_date->copy()->startOfDay()->lt(today());
    }

    /** أيّامُ التأخّر عدداً موجباً — وصفرٌ لما لم يتأخّر. */
    public function daysOverdue(): int
    {
        return $this->isOverdue()
            ? (int) $this->due_date->copy()->startOfDay()->diffInDays(today())
            : 0;
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * اسم مَن يُعرض مُنشئاً للمهمّة.
     *
     * ما أنشأته الأتمتة يُنسب إلى «نظام مُداوَلة» لا إلى صاحب القاعدة:
     * اشتكى مستخدمٌ يرى اسمه على مهامَّ أنشأتها قاعدةٌ ليلاً وهو نائم.
     * وcreated_by باقٍ في القاعدة للمساءلة — قاعدةُ مَن فعلت.
     */
    public function creatorLabel(): string
    {
        if ($this->created_via === 'automation') {
            return 'نظام مُداوَلة';
        }

        return $this->creator->name ?? '—';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
