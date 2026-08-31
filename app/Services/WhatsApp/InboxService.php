<?php

namespace App\Services\WhatsApp;

use App\Models\Client;
use App\Models\Document;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\Notify;
use App\Support\WhatsAppSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * تحويلُ حمولة واتساب إلى محادثاتٍ ورسائل في هذا المكتب.
 *
 * كلُّ ما يكتب في الجداول يمرّ من هنا: المتحكّمات تعرض، والمهامّ
 * تُنادي، والكتابةُ في موضعٍ واحد — فقاعدةُ «لا رسالةَ مكرّرة» و«نافذةُ
 * الأربع والعشرين ساعة تُحدَّث مع كلّ وارد» تُطبَّق مرّةً لا في كل
 * مستدعٍ.
 */
class InboxService
{
    /**
     * رسالةٌ واردة → جهةُ اتصالٍ ومحادثةٌ ورسالة.
     *
     * تُرجع الرسالة، أو null إن كانت مكرّرة (رآها النظام من قبل).
     */
    public function ingestIncoming(array $message, array $contacts = []): ?WhatsAppMessage
    {
        $wamid = (string) ($message['id'] ?? '');
        $from = WhatsAppContact::normalizeWaId((string) ($message['from'] ?? ''));

        if ($wamid === '' || $from === '') {
            return null;
        }

        // الرسالةُ نفسها قد تصل مرّتين رغم دفتر الأحداث — لو أُعيد
        // إدراجُ الحدث بمفتاحٍ مختلف. القيدُ على wamid هو الحدّ الأخير.
        if (WhatsAppMessage::where('wamid', $wamid)->exists()) {
            return null;
        }

        $profileName = $this->profileNameFor($from, $contacts);

        return DB::transaction(function () use ($message, $wamid, $from, $profileName) {
            $contact = $this->contactFor($from, $profileName);
            $conversation = $this->conversationFor($contact);

            $parsed = $this->parseContent($message);
            $sentAt = $this->timestamp($message['timestamp'] ?? null);

            $row = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'wamid' => $wamid,
                'direction' => WhatsAppMessage::IN,
                'type' => $parsed['type'],
                'body' => $parsed['body'],
                'media_id' => $parsed['media_id'],
                'media_mime' => $parsed['media_mime'],
                'media_name' => $parsed['media_name'],
                'media_size' => $parsed['media_size'],
                'status' => WhatsAppMessage::STATUS_DELIVERED,
                'sent_at' => $sentAt,
            ]);

            // النافذةُ تُفتح من جديد مع كل رسالةٍ واردة — هذا تعريفُها
            // عند Meta، وعليه يتوقّف هل يجوز الردُّ الحرّ بعد قليل.
            $conversation->forceFill([
                'last_inbound_at' => $sentAt,
                'last_message_at' => $sentAt,
                'unread_count' => $conversation->unread_count + 1,
                'status' => WhatsAppConversation::STATUS_OPEN,
            ])->save();

            $this->honourOptOut($contact, $parsed['body']);

            return $row;
        });
    }

    /**
     * تحديثُ حالة رسالةٍ صادرة (أُرسلت/سُلّمت/قُرئت/فشلت).
     */
    public function applyStatus(array $status): bool
    {
        $wamid = (string) ($status['id'] ?? '');
        $state = (string) ($status['status'] ?? '');

        if ($wamid === '' || $state === '') {
            return false;
        }

        $message = WhatsAppMessage::where('wamid', $wamid)->first();

        if (!$message || !$message->advanceStatus($state)) {
            return false;
        }

        $at = $this->timestamp($status['timestamp'] ?? null);
        $fields = ['status' => $state];

        match ($state) {
            WhatsAppMessage::STATUS_SENT => $fields['sent_at'] = $at,
            WhatsAppMessage::STATUS_DELIVERED => $fields['delivered_at'] = $at,
            WhatsAppMessage::STATUS_READ => $fields['read_at'] = $at,
            default => null,
        };

        if ($state === WhatsAppMessage::STATUS_FAILED) {
            $error = (array) ($status['errors'][0] ?? []);
            $fields['error_code'] = isset($error['code']) ? (string) $error['code'] : null;
            $fields['error_title'] = mb_substr(
                (string) ($error['title'] ?? $error['message'] ?? 'تعذّر التسليم'),
                0,
                190
            );

            $this->notifySender($message, (string) $fields['error_title']);
        }

        $message->forceFill($fields)->save();

        return true;
    }

    /**
     * إنشاءُ رسالةٍ صادرة في الخيط — قبل إرسالها فعلاً.
     *
     * الحفظُ أوّلاً مقصود: لو سقط الخادم بين الضغط والإرسال بقيت
     * الرسالةُ ظاهرةً بحالة «في الانتظار» بدل أن تختفي كأنّ المحامي
     * لم يكتبها.
     */
    public function queueOutgoing(
        WhatsAppConversation $conversation,
        string $type,
        ?string $body,
        ?User $sender = null,
        array $extra = [],
    ): WhatsAppMessage {
        $message = WhatsAppMessage::create(array_merge([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => $type,
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sent_by' => $sender?->id,
        ], $extra));

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    /** ملاحظةٌ داخلية — تُحفظ ولا تُرسَل أبداً. */
    public function addInternalNote(WhatsAppConversation $conversation, string $body, User $author): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_SENT,
            'sent_by' => $author->id,
            'is_internal' => true,
            'sent_at' => now(),
        ]);
    }

    /**
     * حفظُ وسيطٍ وارد كمستندٍ في ملف القضية.
     *
     * ═══ لماذا لا يُحفظ كلُّ وارد تلقائياً ═══
     *
     * ملفُّ القضية سجلٌّ رسمي: صورةُ ترحيبٍ أو ملصقٌ فيه تُفسده. فالحفظ
     * فعلٌ يقرّره موظّفٌ ويُنسب إليه، والملفّ يبقى عند واتساب حتى ذلك.
     */
    public function saveMediaAsDocument(
        WhatsAppMessage $message,
        int $caseId,
        User $actor,
        ?int $folderId = null,
        ?string $title = null,
    ): ?Document {
        if (!$message->hasMedia() || $message->document_id !== null) {
            return null;
        }

        $provider = WhatsAppManager::provider();

        if (!$provider) {
            return null;
        }

        $meta = $provider->mediaMeta((string) $message->media_id);

        if (!$meta || !filled($meta['url'])) {
            return null;
        }

        $binary = $provider->downloadMedia($meta['url']);

        if ($binary === null || $binary === '') {
            return null;
        }

        // الامتداد من نوع المحتوى الذي تقوله Meta لا من اسمٍ يرسله
        // المستخدم: اسمُ ملفٍّ وارد قد يحمل «..» أو امتداداً مضلّلاً.
        $extension = $this->extensionFor((string) ($meta['mime'] ?? $message->media_mime));
        $path = 'documents/' . Str::uuid()->toString() . ($extension ? '.' . $extension : '');

        Storage::disk('private')->put($path, $binary);

        $document = Document::create([
            'case_id' => $caseId,
            'case_folder_id' => $folderId,
            'uploaded_by' => $actor->id,
            'title' => $title ?: ($message->media_name ?: 'مستند من واتساب'),
            'doc_type' => 'other',
            'doc_date' => now()->toDateString(),
            'file_path' => $path,
            'file_type' => (string) ($meta['mime'] ?? $message->media_mime ?: 'application/octet-stream'),
            'file_size' => strlen($binary),
            'access_level' => Document::ACCESS_TEAM,
            'client_visible' => false,
        ]);

        $message->forceFill(['document_id' => $document->id])->save();

        return $document;
    }

    // ── داخلي ────────────────────────────────────────────────────

    /**
     * جهةُ الاتصال — تُنشأ إن لم تكن، وتُربط بالموكّل إن عُرف يقيناً.
     */
    protected function contactFor(string $waId, ?string $profileName): WhatsAppContact
    {
        $contact = WhatsAppContact::firstOrCreate(
            ['wa_id' => $waId],
            ['profile_name' => $profileName]
        );

        $changes = [];

        if (filled($profileName) && $contact->profile_name !== $profileName) {
            $changes['profile_name'] = $profileName;
        }

        // الربطُ التلقائي مرّةً واحدة: من رُبط يدوياً بموكّلٍ لا يُعاد
        // ربطُه بمطابقةِ رقمٍ قد تكون أضعف من حكم الموظّف.
        if ($contact->client_id === null) {
            $client = Client::findByPhone($waId);

            if ($client) {
                $changes['client_id'] = $client->id;
            }
        }

        if ($changes !== []) {
            $contact->forceFill($changes)->save();
        }

        return $contact;
    }

    protected function conversationFor(WhatsAppContact $contact): WhatsAppConversation
    {
        return WhatsAppConversation::firstOrCreate(
            ['contact_id' => $contact->id],
            ['status' => WhatsAppConversation::STATUS_OPEN, 'unread_count' => 0]
        );
    }

    protected function profileNameFor(string $waId, array $contacts): ?string
    {
        foreach ($contacts as $entry) {
            if (WhatsAppContact::normalizeWaId((string) ($entry['wa_id'] ?? '')) === $waId) {
                $name = (string) ($entry['profile']['name'] ?? '');

                return $name !== '' ? mb_substr($name, 0, 120) : null;
            }
        }

        return null;
    }

    /**
     * قراءةُ محتوى الرسالة على اختلاف نوعها.
     *
     * ما لا نفهمه يُحفظ بنوعه واسمِه بلا محتوى — فيرى الموظّف أنّ
     * شيئاً وصل ويسأل عنه، بدل أن يختفي الوارد بلا أثر.
     */
    protected function parseContent(array $message): array
    {
        $type = (string) ($message['type'] ?? 'text');
        $out = [
            'type' => $type,
            'body' => null,
            'media_id' => null,
            'media_mime' => null,
            'media_name' => null,
            'media_size' => null,
        ];

        switch ($type) {
            case 'text':
                $out['body'] = (string) ($message['text']['body'] ?? '');
                break;

            case 'button':
                $out['body'] = (string) ($message['button']['text'] ?? '');
                break;

            case 'interactive':
                $out['body'] = (string) (
                    $message['interactive']['button_reply']['title']
                    ?? $message['interactive']['list_reply']['title']
                    ?? ''
                );
                break;

            case 'image':
            case 'document':
            case 'audio':
            case 'video':
            case 'sticker':
                $media = (array) ($message[$type] ?? []);
                $out['media_id'] = isset($media['id']) ? (string) $media['id'] : null;
                $out['media_mime'] = isset($media['mime_type']) ? (string) $media['mime_type'] : null;
                $out['media_name'] = isset($media['filename']) ? mb_substr((string) $media['filename'], 0, 190) : null;
                $out['media_size'] = isset($media['file_size']) ? (int) $media['file_size'] : null;
                $out['body'] = isset($media['caption']) ? (string) $media['caption'] : null;
                break;

            case 'location':
                $loc = (array) ($message['location'] ?? []);
                $out['body'] = trim(($loc['name'] ?? '') . ' ' . ($loc['address'] ?? ''))
                    ?: (($loc['latitude'] ?? '') . ',' . ($loc['longitude'] ?? ''));
                break;

            case 'contacts':
                $out['body'] = 'بطاقة جهة اتصال';
                break;

            default:
                $out['body'] = null;
        }

        return $out;
    }

    /**
     * كلمةُ إيقافٍ من العميل تُحترم فوراً.
     *
     * ليست ترفاً: مواصلةُ إرسال إشعارات لمن طلب التوقّف تُبلَّغ عنها
     * فيهبط تقييمُ جودة الرقم عند Meta، وقد يُقيَّد إرسالُه كلُّه.
     */
    protected function honourOptOut(WhatsAppContact $contact, ?string $body): void
    {
        $text = trim(mb_strtolower((string) $body));

        if ($text === '') {
            return;
        }

        $stopWords = ['stop', 'unsubscribe', 'إيقاف', 'ايقاف', 'الغاء', 'إلغاء', 'توقف', 'لا تراسلني'];
        $resumeWords = ['start', 'subscribe', 'اشتراك', 'تفعيل', 'استئناف'];

        foreach ($stopWords as $word) {
            if (str_contains($text, $word)) {
                $contact->forceFill(['opted_out_at' => now(), 'opted_in_at' => null])->save();

                return;
            }
        }

        foreach ($resumeWords as $word) {
            if ($text === $word) {
                $contact->forceFill(['opted_out_at' => null, 'opted_in_at' => now()])->save();

                return;
            }
        }
    }

    /** إشعارٌ داخلي لمن أرسل رسالةً فشل تسليمُها. */
    protected function notifySender(WhatsAppMessage $message, string $reason): void
    {
        if ($message->sent_by === null) {
            return;
        }

        Notify::send(
            userId: $message->sent_by,
            titleKey: 'app.notif_wa_failed_title',
            messageKey: 'app.notif_wa_failed_body',
            params: ['reason' => $reason],
            type: 'error',
        );
    }

    protected function timestamp(mixed $unix): \Illuminate\Support\Carbon
    {
        if (is_numeric($unix) && (int) $unix > 0) {
            return \Illuminate\Support\Carbon::createFromTimestamp((int) $unix);
        }

        return now();
    }

    /** امتدادٌ آمن من نوع المحتوى — لا يُؤخذ من اسمٍ يرسله الطرف الآخر. */
    protected function extensionFor(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/aac' => 'm4a',
            'audio/amr' => 'amr',
            'video/mp4' => 'mp4',
            'video/3gp' => '3gp',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
