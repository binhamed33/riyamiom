<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    const TYPE_INFO = 'info';
    const TYPE_WARNING = 'warning';
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';
    const TYPE_CHAT = 'chat';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'is_read',
        'notifiable_type',
        'notifiable_id',
        'message_count',
        'title_key',
        'message_key',
        'params',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'params' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * عنوان الإشعار بلغة قارئه.
     *
     * الإشعارات الجديدة تُخزَّن مفتاحَ ترجمة ومعاملاتِه لا نصّاً جاهزاً،
     * فتُقرأ بالعربية لمن اختار العربية وبالإنجليزية لمن اختار
     * الإنجليزية — ولو تغيّر اختياره بعد وصولها.
     *
     * والإشعارات القديمة محفوظة بنصّها الحرفي: تُعرض كما هي بلا تعديل
     * ولا حذف. لا مفتاح لها فتُرجَع كما كُتبت.
     */
    public function localizedTitle(?string $locale = null): string
    {
        return $this->translate($this->title_key, (string) $this->title, $locale);
    }

    public function localizedMessage(?string $locale = null): string
    {
        return $this->translate($this->message_key, (string) $this->message, $locale);
    }

    private function translate(?string $key, string $fallback, ?string $locale): string
    {
        if (!$key) {
            return $fallback;   // إشعار قديم — نصّه هو نصّه
        }

        $locale ??= app()->getLocale();
        $params = is_array($this->params) ? $this->params : [];

        $text = __($key, $params, $locale);

        // مفتاح غير موجود يرجع كما هو — لا نعرض «app.foo» على المستخدم
        return $text === $key ? $fallback : $text;
    }
}
