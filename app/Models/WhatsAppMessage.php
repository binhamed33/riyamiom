<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رسالةُ واتساب — واردةٌ أو صادرة أو ملاحظةٌ داخلية.
 *
 * الملاحظةُ الداخلية تعيش في نفس الخيط كي يقرأها الفريق في سياقها،
 * ولا تُرسَل أبداً: is_internal تمنعها عند الإرسال، والمهمّةُ ترفضها
 * صراحةً لا اعتماداً على أنّ أحداً لن يدفعها إلى الطابور.
 */
class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    public const IN = 'in';
    public const OUT = 'out';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'conversation_id',
        'wamid',
        'direction',
        'type',
        'body',
        'media_id',
        'media_mime',
        'media_name',
        'media_size',
        'document_id',
        'status',
        'error_code',
        'error_title',
        'sent_by',
        'is_internal',
        'template_name',
        'session_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'media_size' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === self::IN;
    }

    public function hasMedia(): bool
    {
        return in_array($this->type, ['image', 'document', 'audio', 'video', 'sticker'], true)
            && filled($this->media_id);
    }

    /**
     * ترتيبُ الحالات لا يعود إلى الوراء.
     *
     * إشعاراتُ Meta تصل بلا ترتيبٍ مضمون: قد يسبق «مقروءة» وصولَ
     * ‏«سُلّمت». ولولا هذا الحارس لعادت الرسالةُ المقروءة «مُرسَلة»
     * أمام المحامي بعد أن رآها مقروءة.
     */
    public function advanceStatus(string $status): bool
    {
        $rank = [
            self::STATUS_QUEUED => 0,
            self::STATUS_SENT => 1,
            self::STATUS_DELIVERED => 2,
            self::STATUS_READ => 3,
        ];

        // الفشلُ يُكتب دائماً: هو خبرٌ يجب أن يراه المرسِل مهما سبقه
        if ($status === self::STATUS_FAILED) {
            return true;
        }

        if (!isset($rank[$status])) {
            return false;
        }

        if ($this->status === self::STATUS_FAILED) {
            return false;
        }

        return ($rank[$status] ?? 0) > ($rank[$this->status] ?? 0);
    }

    /** نصٌّ مختصر لقائمة المحادثات — لا يكشف مستنداً ولا صورة. */
    public function preview(): string
    {
        if ($this->is_internal) {
            return '📝 ' . \Illuminate\Support\Str::limit((string) $this->body, 60);
        }

        return match ($this->type) {
            'image' => '📷 صورة',
            'document' => '📎 ' . ($this->media_name ?: 'مستند'),
            'audio' => '🎤 رسالة صوتية',
            'video' => '🎬 مقطع مرئي',
            'sticker' => '🙂 ملصق',
            'location' => '📍 موقع',
            'template' => \Illuminate\Support\Str::limit((string) $this->body, 60),
            default => \Illuminate\Support\Str::limit((string) $this->body, 60),
        };
    }
}
