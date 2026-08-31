<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * محادثةُ واتساب — خيطٌ واحد لكل جهة اتصال.
 */
class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'contact_id',
        'case_id',
        'assigned_to',
        'status',
        'last_inbound_at',
        'last_message_at',
        'unread_count',
        'handoff_at',
    ];

    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
            'last_message_at' => 'datetime',
            'handoff_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    /**
     * هل نافذةُ الردّ الحرّ ما زالت مفتوحة؟
     *
     * تسمح Meta بالردّ الحرّ أربعاً وعشرين ساعةً من آخر رسالةٍ يرسلها
     * العميل. خارجها لا يمرّ إلا قالبٌ معتمَد — والمحاولةُ تُرفض بخطأ
     * ‏131047، فيرى المحامي رسالةً «أُرسلت» ولم تصل أحداً.
     */
    public function windowOpen(): bool
    {
        // ═══ النافذةُ قاعدةُ Meta لا قاعدتُنا ═══
        //
        // جسرُ واتساب ويب لا يعرفها: ما يقدر عليه الهاتفُ يقدر عليه،
        // ويرسل لأيّ رقمٍ في أيّ وقت. فمنعُ المحامي من الردّ هناك منعٌ
        // بلا سبب — يرى «انتهت النافذة» بينما الرسالة كانت ستصل.
        if (!(bool) config('whatsapp.providers.' . config('whatsapp.default', 'meta') . '.service_window', true)) {
            return true;
        }

        if ($this->last_inbound_at === null) {
            return false;
        }

        return $this->last_inbound_at->gt(
            now()->subHours((int) config('whatsapp.service_window_hours', 24))
        );
    }

    /** هل يفرض المزوّدُ نافذةً أصلاً؟ — للعرض في الواجهة. */
    public function windowApplies(): bool
    {
        return (bool) config('whatsapp.providers.' . config('whatsapp.default', 'meta') . '.service_window', true);
    }

    /** كم بقي من النافذة بالدقائق — للعرض في الواجهة. */
    public function windowMinutesLeft(): int
    {
        if (!$this->windowApplies() || $this->last_inbound_at === null || !$this->windowOpen()) {
            return 0;
        }

        $closesAt = $this->last_inbound_at->copy()
            ->addHours((int) config('whatsapp.service_window_hours', 24));

        return max(0, (int) now()->diffInMinutes($closesAt, absolute: false));
    }

    /** هل يردّ الذكاء الاصطناعي هنا؟ التحويل إلى موظّف يوقفه. */
    public function aiMayReply(): bool
    {
        return $this->handoff_at === null && $this->status === self::STATUS_OPEN;
    }
}
