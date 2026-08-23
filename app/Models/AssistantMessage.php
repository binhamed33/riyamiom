<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رسالة في محادثة المساعد القانوني.
 *
 * كانت المحادثة تعيش في الجلسة، فمن أغلق المتصفّح فقدها. وهي محفوظة
 * لكل موظّف على حدة: لا يرى أحدٌ محادثة غيره.
 */
class AssistantMessage extends Model
{
    /** ما يُحفظ لكل موظّف. الأقدم يسقط حين يزيد العدد. */
    public const KEEP = 60;

    /** ما يُرسل إلى النموذج من سياق سابق. */
    public const CONTEXT = 20;

    protected $fillable = ['user_id', 'role', 'content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOf(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId)->orderBy('id');
    }

    /** يكتب رسالة ويقصّ ما زاد عن الحدّ — بلا حذفٍ عشوائي. */
    public static function write(int $userId, string $role, string $content): self
    {
        $message = static::create([
            'user_id' => $userId,
            'role' => $role,
            'content' => $content,
        ]);

        $ids = static::where('user_id', $userId)
            ->orderByDesc('id')
            ->skip(self::KEEP)
            ->take(1000)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            static::whereIn('id', $ids)->delete();
        }

        return $message;
    }

    /** آخر ما قيل، بالشكل الذي يفهمه النموذج. */
    public static function contextFor(int $userId): array
    {
        // لا تُبنَ على of(): ترتيبُها تصاعدي، فيصير latest ترتيباً
        // ثانياً لا أوّلاً — فتُرسَل أقدمُ الرسائل بدل أحدثها، ويتذكّر
        // المساعدُ أوّلَ المحادثة وينسى ما قيل قبل سطر.
        return static::where('user_id', $userId)
            ->orderByDesc('id')
            ->take(self::CONTEXT)
            ->get()
            ->reverse()
            ->map(fn (self $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }
}
