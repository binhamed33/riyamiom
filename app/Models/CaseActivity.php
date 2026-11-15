<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseActivity extends Model
{
    use HasFactory;

    const TYPE_NOTE = 'note';
    const TYPE_CALL = 'call';
    const TYPE_DOCUMENT = 'document';
    const TYPE_TASK = 'task';
    const TYPE_SESSION = 'session';
    const TYPE_PAYMENT = 'payment';
    const TYPE_APPOINTMENT = 'appointment';
    const TYPE_STATUS = 'status';
    const TYPE_OTHER = 'other';

    protected $fillable = [
        'case_id',
        'user_id',
        'type',
        'title',
        'content',
        'occurred_at',
        'created_via',
    ];

    /** اسم الفاعل كما يُعرض — والنظامُ فاعلٌ باسمه لا باسم صاحب القاعدة. */
    public function actorLabel(): ?string
    {
        if ($this->created_via === 'automation') {
            return 'نظام مُداوَلة';
        }

        return \App\Support\DeveloperShadow::name($this->user);
    }

    /** ما يكتبه الإنسانُ بيده — وهو وحده «نشاطُ» المطوّر الذي يُخفى. */
    public const PERSONAL_TYPES = [self::TYPE_NOTE, self::TYPE_CALL, self::TYPE_APPOINTMENT, self::TYPE_OTHER];

    /**
     * نشاطُ المطوّر مخفيٌّ عن غير المطوّرين (DeveloperShadow).
     *
     * ‏«نشاطُه» = ما كتبه بيده: ملاحظةٌ واتصالٌ وموعدٌ. أمّا أثرُ البيانات الذي
     * يكتبه النظامُ عند إدخالها (جلسةٌ حُدّدت، حالةٌ تغيّرت، مستندٌ أُضيف)
     * وأحداثُ الأتمتة فبياناتُ المكتب: تبقى ظاهرةً منسوبةً إلى «نظام مُداوَلة»
     * ‏(actorLabel). كان إخفاؤها يُسقط موعدَ جلسةٍ أدخله المطوّرُ من مسار
     * الموكّل في البوابة، وأحداثَ كلِّ قاعدةِ أتمتةٍ أنشأها المطوّر.
     */
    public function scopeVisibleTo($query, ?User $viewer = null)
    {
        if (!\App\Support\DeveloperShadow::applies($viewer) || ($ids = \App\Support\DeveloperShadow::ids()) === []) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->whereNull('user_id')
            ->orWhereNotIn('user_id', $ids)
            ->orWhere('created_via', 'automation')
            ->orWhereNotIn('type', self::PERSONAL_TYPES));
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}