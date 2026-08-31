<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\Ai\AiManager;
use App\Services\WhatsApp\InboxService;
use App\Support\Notify;
use App\Support\WhatsAppSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * الردّ الآليّ على رسالةٍ واردة — وحدودُه.
 *
 * ═══ ما لا يفعله هذا الردّ ═══
 *
 * لا يُفتي. «هل أستطيع رفع دعوى؟» و«هل سأربح؟» أسئلةٌ جوابُها استشارةٌ
 * قانونية، وإجابتُها آلياً باسم المكتب تُنشئ التزاماً مهنياً على محامٍ
 * لم يقرأ السؤال — وقد تُبنى عليها خطوةٌ تُسقط حقّاً أو تُفوّت ميعاداً.
 *
 * فحين يُشمّ في السؤال طلبُ رأيٍ قانوني: يُعتذر بلطف، وتُنشأ مهمّةٌ
 * للفريق، ويُحوَّل الخيط إلى إنسان — ولا يُكتب رأيٌ البتّة.
 *
 * ═══ ومتى لا يعمل أصلاً ═══
 *
 * مطفأٌ افتراضاً. ولا يعمل خارج نافذة الأربع والعشرين ساعة (لا يجوز
 * الردُّ الحرّ)، ولا على محادثةٍ حُوِّلت إلى موظّف، ولا على من طلب
 * إيقاف المراسلة.
 */
class AnswerWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** عباراتٌ يغلب أن يتبعها طلبُ رأيٍ قانوني. */
    private const LEGAL_ADVICE_HINTS = [
        'دعوى', 'أرفع', 'ارفع', 'استشارة', 'رأيك', 'هل يحق', 'هل يجوز',
        'أستحق', 'استحق', 'سأربح', 'ساربح', 'أخسر', 'محكمة', 'قضيتي',
        'حكم', 'استئناف', 'تعويض', 'أقاضي', 'اقاضي', 'حقي', 'ورث', 'ميراث',
        'طلاق', 'حضانة', 'نفقة', 'عقوبة', 'سجن', 'كفالة', 'شكوى',
    ];

    public function __construct(public int $messageId)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(InboxService $inbox): void
    {
        if (!WhatsAppSettings::flag(WhatsAppSettings::KEY_AI_REPLY)) {
            return;
        }

        $message = WhatsAppMessage::with('conversation.contact.client')->find($this->messageId);

        if (!$message || !$message->isInbound()) {
            return;
        }

        $conversation = $message->conversation;

        if (!$conversation || !$conversation->aiMayReply() || !$conversation->windowOpen()) {
            return;
        }

        if (!$conversation->contact?->acceptsNotifications()) {
            return;
        }

        // ردٌّ لاحقٌ للرسالة موجودٌ أصلاً: وصلت رسالتان متتاليتان وأُجيب
        // عن الأحدث. الردُّ على الأقدم الآن يُربك الترتيب أمام العميل.
        $newerReply = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('id', '>', $message->id)
            ->where('direction', WhatsAppMessage::OUT)
            ->exists();

        if ($newerReply) {
            return;
        }

        $body = trim((string) $message->body);

        if ($body === '') {
            return; // صورةٌ أو صوتٌ بلا نصّ — لا يُجاب آلياً
        }

        if ($this->looksLikeLegalAdvice($body)) {
            $this->escalate($conversation, $message, $inbox, $body);

            return;
        }

        $provider = AiManager::tryProvider();

        if (!$provider) {
            return; // بلا ذكاء اصطناعي مضبوط: صمتٌ خيرٌ من ردٍّ فارغ
        }

        $reply = $provider->chat(
            [['role' => 'user', 'content' => $body]],
            $this->systemPrompt($conversation),
        );

        if (!is_string($reply) || trim($reply) === '') {
            Log::info('WhatsApp auto-reply produced nothing for message ' . $message->id);

            return;
        }

        $outgoing = $inbox->queueOutgoing($conversation, 'text', $this->stamp(trim($reply)));
        SendWhatsAppMessage::dispatch($outgoing->id);
    }

    /**
     * سؤالٌ قانوني: اعتذارٌ ومهمّةٌ وتحويلٌ لإنسان — ولا رأي.
     */
    protected function escalate(
        \App\Models\WhatsAppConversation $conversation,
        WhatsAppMessage $message,
        InboxService $inbox,
        string $question,
    ): void {
        $conversation->forceFill(['handoff_at' => now()])->save();

        $name = $conversation->contact?->displayName() ?? 'مستفسر';

        $outgoing = $inbox->queueOutgoing($conversation, 'text', $this->stamp(
            'شكراً لتواصلكم. سؤالكم يحتاج نظرَ المحامي المختص، وقد أُحيل إليه'
            . ' وسيتواصل معكم في أقرب وقت خلال ساعات العمل.'
        ));
        SendWhatsAppMessage::dispatch($outgoing->id);

        $assignee = $conversation->assigned_to
            ?? User::whereIn('role', ['admin', 'lawyer'])->where('is_active', true)->value('id');

        if ($assignee) {
            Task::create([
                'title' => 'استفسار واتساب من ' . mb_substr($name, 0, 60),
                'description' => mb_substr($question, 0, 900),
                'assigned_to' => $assignee,
                'created_by' => $assignee,
                'created_via' => 'automation',
                'status' => 'pending',
                'priority' => 'medium',
                'due_date' => now()->addDay()->toDateString(),
                'case_id' => $conversation->case_id,
            ]);

            Notify::send(
                userId: $assignee,
                titleKey: 'app.notif_wa_handoff_title',
                messageKey: 'app.notif_wa_handoff_body',
                params: ['name' => $name],
                type: 'warning',
            );
        }
    }

    protected function looksLikeLegalAdvice(string $body): bool
    {
        $text = mb_strtolower($body);

        foreach (self::LEGAL_ADVICE_HINTS as $hint) {
            if (str_contains($text, $hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * الرسالةُ الآليّة تُعرِّف نفسها.
     *
     * عميلٌ يظنّ أنّه يكلّم محاميه وهو يكلّم آلةً يبني على الجواب ما
     * لا يحتمله. والشفافيّةُ هنا ليست تجميلاً بل شرطُ ألّا يكون الردُّ
     * تضليلاً.
     */
    protected function stamp(string $reply): string
    {
        return $reply . "\n\n— رد آلي من نظام المكتب. للتحدث مع موظف اكتب: موظف";
    }

    protected function systemPrompt(\App\Models\WhatsAppConversation $conversation): string
    {
        $office = \App\Support\OfficeBrand::name();
        $known = $conversation->contact?->client !== null ? 'موكّل مسجَّل لدى المكتب' : 'مستفسر غير مسجَّل';

        return <<<PROMPT
        أنت موظّف استقبال في مكتب «{$office}» للمحاماة في سلطنة عُمان، تردّ عبر واتساب.
        المتحدث: {$known}.

        قواعد لا تُخالف:
        - لا تُعطِ رأياً قانونياً ولا تقديراً لنتيجة قضية ولا تفسيراً لنصّ نظامي.
        - لا تَعِد بموعد ولا بمبلغ أتعاب ولا بنتيجة.
        - لا تطلب أرقام بطاقات أو كلمات مرور أو مستندات حساسة عبر واتساب.
        - لا تذكر بيانات موكّل آخر ولا أيّ قضية بالاسم أو الرقم.
        - إن كان السؤال قانونياً، قل إنّه سيُحال إلى المحامي المختص ولا تُجب.

        ما تفعله: ترحّب، تجيب عن ساعات العمل والموقع وطريقة الحجز وخدمات المكتب عموماً،
        وتجمع اسم المتصل وسبب تواصله لتسليمه للفريق.

        اكتب بالعربية الفصحى المهذّبة، جملتين إلى أربع، بلا قوائم ولا رموز تنسيق.
        PROMPT;
    }
}
