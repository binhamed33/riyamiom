<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Attachments;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'message', 'attachment_path', 'attachment_name', 'attachment_type', 'attachment_size', 'reply_to_id', 'edited_at', 'discord_message_id', 'discord_replied_at'];

    protected $appends = ['attachment_url', 'attachment_download_url', 'attachment_size_label', 'is_image'];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'discord_replied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * المرفق يُقدَّم عبر مسارٍ يتحقّق من العضوية، لا من رابطٍ عام.
     *
     * كان Storage::url() يبني ‎/storage/…‎ ويعتمد على رابطٍ رمزيّ لا
     * يُنشأ في هذا المستودع (public/storage مجلدٌ حقيقيّ متتبَّع)، فكانت
     * كل صورة مكسورة وكل تنزيل يفشل. والمسار المحمي يحلّ العطل ويسدّ
     * معه بابَ قراءة مرفقات المكتب بلا تسجيل دخول.
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path && $this->exists
            ? route('chat.attachment', $this)
            : null;
    }

    public function getAttachmentDownloadUrlAttribute(): ?string
    {
        return $this->attachment_path && $this->exists
            ? route('chat.attachment', [$this, 'download' => 1])
            : null;
    }

    /**
     * هل الملفُّ نفسُه ما زال على القرص؟
     *
     * غيرُ مُلحقٍ بالتسلسل عمداً: فحصُ القرص لكل رسالة في كل ردّ JSON
     * ثمنٌ بلا فائدة — الواجهة وحدها تحتاجه لتقول «مرفقٌ مفقود» بدل أن
     * تعرض صورةً مكسورة.
     */
    public function getAttachmentExistsAttribute(): bool
    {
        return Attachments::exists($this->attachment_path);
    }

    public function getAttachmentSizeLabelAttribute(): string
    {
        return Attachments::humanSize($this->attachment_size);
    }

    public function getIsImageAttribute(): bool
    {
        return $this->attachment_type && str_starts_with($this->attachment_type, 'image/');
    }
}
