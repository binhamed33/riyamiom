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
     * أين يذهب من نقر الإشعار.
     *
     * ═══ لماذا يلزم ═══
     *
     * الإشعارُ يقول «أُسندت إليك مهمّة» ثمّ لا يفعل شيئاً عند نقره —
     * فيبحث الموظّفُ عن المهمّة بنفسه في قائمةٍ من ستّين. والإشعارُ
     * الذي لا يُوصل إلى ما يُخبر عنه نصفُ إشعار.
     *
     * ═══ وكيف يُعرف المقصود ═══
     *
     * أوّلاً من الكائن المرتبط (notifiable) إن وُجد — وهو الأدقّ:
     * مهمّةٌ بعينها، قضيةٌ بعينها. فإن لم يوجد فمن مفتاح العنوان:
     * تذكيرُ النسخة الاحتياطية يذهب إلى صفحة النسخ، وتنبيهُ الاشتراك
     * إلى الإعدادات — لا شيءَ يُترك بلا وجهة.
     *
     * ويُتحقّق من وجود الصفّ قبل بناء الرابط: كائنٌ حُذف بعد إشعاره
     * كان يرمي 404 في وجه من نقر.
     */
    public function destination(): ?string
    {
        if ($url = $this->destinationFromSubject()) {
            return $url;
        }

        return $this->destinationFromKey();
    }

    private function destinationFromSubject(): ?string
    {
        $type = (string) $this->notifiable_type;
        $id = $this->notifiable_id;

        if ($type === '' || !$id || !class_exists($type)) {
            return null;
        }

        // الصفُّ قد يكون حُذف بعد إشعاره — رابطٌ إلى محذوفٍ يرمي 404
        $subject = $type::query()->find($id);

        if (!$subject) {
            return null;
        }

        return match ($type) {
            LegalCase::class => route('cases.show', $id),
            Task::class => route('tasks.show', $id),
            Session::class => $subject->case_id ? route('cases.show', $subject->case_id) . '#sessions' : route('sessions.index'),
            CaseReminder::class => $subject->case_id ? route('cases.show', $subject->case_id) : route('cases.index'),
            Appointment::class => route('appointments.index', ['day' => $subject->starts_at?->toDateString()]),
            Document::class => $subject->case_id ? route('cases.show', $subject->case_id) . '#documents' : route('documents.index'),
            Client::class => route('clients.show', $id),
            Suggestion::class => route('suggestions.index'),
            Conversation::class => route('chat.index'),
            HrLeave::class => route('hr.index'),
            FinanceInvoice::class => route('finance.index'),
            default => null,
        };
    }

    /** إشعارٌ بلا كائنٍ مرتبط — وجهتُه من موضوعه. */
    private function destinationFromKey(): ?string
    {
        $key = (string) $this->title_key;

        return match (true) {
            str_contains($key, 'backup') => route('backup.index'),
            str_contains($key, 'sub_') => route('settings.index'),
            str_contains($key, 'wa_') => route('whatsapp.index'),
            str_contains($key, 'auto_task') => route('tasks.index'),
            str_contains($key, 'auto_rule') => route('automations.index'),
            str_contains($key, 'remind') => route('cases.index'),
            str_contains($key, 'task') => route('tasks.index'),
            str_contains($key, 'session') => route('sessions.index'),
            str_contains($key, 'leave') => route('hr.index'),
            str_contains($key, 'suggestion') => route('suggestions.index'),
            str_contains($key, 'chat') => route('chat.index'),
            str_contains($key, 'case') => route('cases.index'),
            default => null,
        };
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
