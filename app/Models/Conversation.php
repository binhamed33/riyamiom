<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['title', 'type'];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * ما لم يقرأه هذا الموظّفُ في كلّ محادثة — عمود unread_count.
     *
     * ═══ لماذا في مكانٍ واحد ═══
     *
     * كان الاستعلامُ مكتوباً مرّتين: في قائمة المحادثات وفي عدّاد الشارة،
     * وسقط ثالثةً من شاشة المحادثة المفتوحة — فتختفي الشاراتُ الحمراء عن
     * كلّ المحادثات ما دامت واحدةٌ مفتوحة. مكانٌ واحدٌ لا ثلاثة.
     *
     * ═══ ورسائلي ليست «غيرَ مقروءة» ═══
     *
     * الشرطُ كان «كلُّ رسالةٍ بعد آخر قراءة» بلا استثناء المرسِل، فمن أرسل
     * رسالةً رأى شارةً حمراءَ على محادثته هو.
     */
    public function scopeWithUnreadFor(Builder $query, int $userId): Builder
    {
        return $query->withCount(['messages as unread_count' => function ($q) use ($userId) {
            $q->where('messages.user_id', '!=', $userId)
                ->whereRaw(
                    '((SELECT last_read_at FROM conversation_participants'
                    . ' WHERE conversation_id = messages.conversation_id AND user_id = ?) IS NULL'
                    . ' OR messages.created_at > (SELECT last_read_at FROM conversation_participants'
                    . ' WHERE conversation_id = messages.conversation_id AND user_id = ?))',
                    [$userId, $userId],
                );
        }]);
    }

    /**
     * اسمُ المحادثة كما يراه هذا الموظّف.
     *
     * كان يُؤخذ أوّلُ مشاركٍ غيري ويُعرض اسماً للمحادثة — فمجموعةٌ فيها
     * أربعةٌ تظهر باسم واحدٍ منهم، ويظنّ القارئُ أنّها محادثةٌ ثنائيّة معه
     * ويكتب فيها ما لا يقوله للبقيّة.
     */
    public function titleFor(int $userId): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        $others = $this->participants->where('id', '!=', $userId)->values();

        if ($others->count() <= 1) {
            return $others->first()?->name ?? 'محادثة';
        }

        // الأسماءُ العُمانيّة طويلة («لجينة بنت مالك بن علي المعني»)، فالاسمُ
        // الأوّلُ يكفي للتعريف في سطرٍ واحد
        $first = $others->take(3)->map(fn ($u) => explode(' ', trim((string) $u->name))[0])->implode('، ');

        return $others->count() > 3 ? $first . ' +' . ($others->count() - 3) : $first;
    }

    /** هل هي مجموعةٌ لا محادثةٌ ثنائيّة؟ */
    public function isGroup(): bool
    {
        return $this->participants->count() > 2 || $this->type === 'group';
    }
}
