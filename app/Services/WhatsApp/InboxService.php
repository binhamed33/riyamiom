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
            $this->honourHandoffRequest($conversation, $parsed['body']);

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

        // ═══ حدُّ الحجم قبل التنزيل لا بعده ═══
        //
        // التنزيلُ يجلب الملفَّ كلَّه إلى سلسلةٍ في الذاكرة داخل طلبِ
        // ويب. ومستندٌ من مئة ميغابايت — وMeta تقبل ذلك — يستهلك من
        // ذاكرة PHP أضعافَ حجمه، فيسقط الطلب بلا رسالة مفهومة ويُبطئ
        // المكتب كلَّه معه. والسقفُ كان مكتوباً في config/whatsapp.php
        // ولا يقرؤه أحد.
        $max = (int) config('whatsapp.auto_download_max', 20 * 1024 * 1024);
        $size = (int) ($meta['size'] ?? 0);

        if ($max > 0 && $size > $max) {
            WhatsAppSettings::recordError(
                'ملفٌّ أكبر من الحدّ المسموح (' . round($size / 1048576, 1) . ' م.ب) — لم يُحفظ.'
            );

            return null;
        }

        // ونوعٌ لا تعرفه القائمة لا يُحفظ في ملفّ قضيّة: ما لا نعرف
        // نوعَه لا نعرف أنّه يُفتح، ووضعُه في السجلّ الرسمي يوهم بوجود
        // مستندٍ لا يُقرأ.
        if (!$this->mimeAllowed((string) ($meta['mime'] ?? $message->media_mime))) {
            WhatsAppSettings::recordError('نوع الملف غير مدعوم في واتساب — لم يُحفظ.');

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
        //
        // ═══ ولماذا لا تكفي البصمة وحدها ═══
        //
        // بصمةُ الهاتف تُحسب على آخر ثمانية أرقام — وهذا يجعل رقماً من
        // دولةٍ أخرى ينتهي بها يطابق موكّلاً عُمانياً. ومن ملك رقماً
        // كذلك ورسل رسالةً واحدة صار خيطُه يحمل اسمَ موكّلٍ حقيقي، ثمّ
        // يلتقطه تذكيرُ الجلسات فيُرسل إليه اسمَ الموكّل ورقمَ قضيّته
        // وموعدَ جلسته.
        //
        // فالبصمةُ مُرشِّحٌ رخيص، والحكمُ للرقم كاملاً بمفتاح دولته.
        // وما لم يتطابق يُترك لإنسان يربطه — وهو الإخفاق الآمن.
        if ($contact->client_id === null) {
            $client = Client::findByPhone($waId);

            if ($client && WhatsAppContact::normalizeWaId((string) $client->phone) === $waId) {
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

    /**
     * اسمُ ملفّ المُرسِل — نصٌّ يكتبه هو، فيُنظَّف قبل حفظه.
     *
     * ═══ لماذا يُنظَّف وBlade يهرّب أصلاً ═══
     *
     * لأنّ هذا الاسم لا يبقى في Blade: يدخل في نصّ إشعارٍ يُقرأ
     * بجافاسكربت، وفي عنوان مهمّة، وفي تصدير CSV. وكلُّ مخرجٍ من هذه
     * له قواعدُ تهريبٍ مختلفة، وكفايةُ واحدةٍ منها لا تكفي البقيّة.
     * فيُقلَّم عند الباب مرّةً بدل أن يُتذكَّر عند كلّ مخرج.
     *
     * والمحارفُ الحاكمة (U+202E وأخواتُها) تُحذف بالذات: اسمٌ يحمل
     * أحدَها يقلب اتجاهَ ما بعده في قائمة المحادثات، فيُرى رقمُ موكّلٍ
     * أو نصُّ رسالةٍ معكوساً — تزويرٌ بصريٌّ لا يكشفه النظر.
     */
    protected function profileNameFor(string $waId, array $contacts): ?string
    {
        foreach ($contacts as $entry) {
            if (WhatsAppContact::normalizeWaId((string) ($entry['wa_id'] ?? '')) === $waId) {
                $name = self::sanitizeProfileName((string) ($entry['profile']['name'] ?? ''));

                return $name !== '' ? $name : null;
            }
        }

        return null;
    }

    public static function sanitizeProfileName(string $raw): string
    {
        // المحارفُ الحاكمة والمخفيّة: أصفارُ العرض وقالباتُ الاتجاه
        $clean = (string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $raw);

        // محارفُ التحكّم (بما فيها السطرُ الجديد): الاسمُ سطرٌ واحد
        $clean = (string) preg_replace('/[\x{0000}-\x{001F}\x{007F}]/u', ' ', $clean);

        // الزوايا لا معنى لها في اسم، ووجودُها نيّةٌ لا سهو
        $clean = str_replace(['<', '>'], '', $clean);

        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return mb_substr($clean, 0, 120);
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
        $text = self::normalizeArabic((string) $body);

        if ($text === '') {
            return;
        }

        // الإيقافُ تعليمةٌ لا موضوع.
        //
        // ═══ العطل الذي وُضع له هذا الشرط ═══
        //
        // كانت المطابقةُ str_contains على الكلمة داخل الرسالة، فجملةٌ
        // عاديّةٌ تماماً في مكتب محاماة — «أريد إلغاء الوكالة» أو «هل
        // يمكن إلغاء العقد؟» — تقطع الموكّلَ عن كلّ إشعارات مكتبه إلى
        // الأبد. ولا يعرف أحدٌ لماذا توقّفت رسائلُه، ولا كان يُعيدها
        // إلا رسالةٌ من كلمةٍ واحدة بعينها لا يخطر ببال أحد.
        //
        // فالآن: الرسالةُ إمّا أن تكون التعليمةَ نفسها، أو قصيرةً جداً
        // (ثلاث كلماتٍ فأقلّ) تتضمّنها. أمّا جملةٌ فيها كلامٌ آخر فهي
        // حديثٌ عن الإلغاء لا طلبٌ لإيقاف المراسلة.
        $stopPhrases = ['stop', 'unsubscribe', 'ايقاف', 'توقف', 'لا تراسلني',
                        'الغاء الاشتراك', 'ايقاف الرسائل', 'لا ترسل'];
        $resumePhrases = ['start', 'subscribe', 'اشتراك', 'تفعيل', 'استئناف', 'ابدا'];

        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $short = count($words) <= 3;

        foreach ($stopPhrases as $phrase) {
            if ($text === $phrase || ($short && str_contains($text, $phrase))) {
                $contact->forceFill(['opted_out_at' => now(), 'opted_in_at' => null])->save();

                return;
            }
        }

        foreach ($resumePhrases as $phrase) {
            if ($text === $phrase || ($short && str_contains($text, $phrase))) {
                $contact->forceFill(['opted_out_at' => null, 'opted_in_at' => now()])->save();

                return;
            }
        }
    }

    /**
     * «موظف» — طلبُ إنسان.
     *
     * ═══ العطل الذي يمنعه ═══
     *
     * كلُّ ردٍّ آلي يُذيَّل بـ«للتحدث مع موظف اكتب: موظف». وكان لا أحد
     * يقرأ تلك الكلمة: يكتبها العميل، فيردّ عليه الآليُّ ثانيةً بنفس
     * الذيل، فيكتبها ثانيةً. وعدٌ في رسالةٍ باسم المكتب لا يفي به
     * النظام — وقد يكون الرجل في ميعادٍ يسقط.
     *
     * فصارت الكلمةُ تُقرأ: يُختم الخيطُ بالتحويل (فيصمت الآليُّ
     * بـaiMayReply)، ويُنبَّه الموظّف، ويُطمأنُ العميل بأنّ طلبه وصل.
     *
     * وتُقاس بنفس ميزان «إيقاف»: تعليمةٌ لا موضوع. فمن كتب «تعاملتُ مع
     * موظف عندكم أمس» لا يُحوَّل خيطُه ولا يُنبَّه أحدٌ بلا سبب.
     *
     * والردُّ حرٌّ داخل النافذة بلا قالب: العميل راسلَنا في هذه اللحظة،
     * فالنافذةُ مفتوحةٌ بالضرورة.
     */
    protected function honourHandoffRequest(WhatsAppConversation $conversation, ?string $body): void
    {
        // محوَّلٌ أصلاً: لا يُعاد التنبيه ولا التطمين مع كل رسالة
        if ($conversation->handoff_at !== null) {
            return;
        }

        $text = self::normalizeArabic((string) $body);

        if ($text === '') {
            return;
        }

        // بعد التوحيد: «موظّفة» تصير «موظفه»، و«إنسان» تصير «انسان»
        $phrases = ['موظف', 'موظفه', 'محامي', 'انسان', 'بشري', 'human', 'agent'];

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $short = count($words) <= 3;
        $asked = false;

        foreach ($phrases as $phrase) {
            if ($text === $phrase || ($short && str_contains($text, $phrase))) {
                $asked = true;
                break;
            }
        }

        if (!$asked) {
            return;
        }

        $conversation->forceFill([
            'handoff_at' => now(),
            'status' => WhatsAppConversation::STATUS_OPEN,
        ])->save();

        $this->notifyHandoff($conversation);

        // ومن سجّل رفضَ المراسلة لا يُطمأن آلياً: التحويلُ والتنبيهُ
        // تمّا، فيتولّاه إنسانٌ يقرّر. والرفضُ لا تنقضه رسالةٌ يكتبها
        // النظامُ من عنده.
        if (!$conversation->contact?->acceptsNotifications()) {
            return;
        }

        $confirmation = $this->queueOutgoing(
            $conversation,
            'text',
            'وصلَنا طلبكم. سيتواصل معكم أحد موظّفي المكتب في أقرب وقت خلال ساعات العمل.',
        );

        // الدفعُ بعد الالتزام لا داخله: عاملُ الطابور يقرأ من نفس
        // القاعدة، فمهمّةٌ تُدفع داخل معاملةٍ لم تُلتزم بعد قد يلتقطها
        // العامل قبل وجود صفّ الرسالة فيسقط ويُعاد بلا داعٍ
        DB::afterCommit(function () use ($confirmation) {
            \App\Jobs\SendWhatsAppMessage::dispatch($confirmation->id);
        });
    }

    /** تنبيهُ من يتابع الخيط — أو أوّلِ محامٍ نشط إن لم يُسنَد بعد. */
    protected function notifyHandoff(WhatsAppConversation $conversation): void
    {
        $assignee = $conversation->assigned_to
            ?? User::whereIn('role', ['admin', 'lawyer'])->where('is_active', true)->value('id');

        if (!$assignee) {
            return;
        }

        Notify::send(
            userId: (int) $assignee,
            titleKey: 'app.notif_wa_handoff_title',
            messageKey: 'app.notif_wa_handoff_body',
            params: ['name' => $conversation->contact?->displayName() ?? 'مستفسر'],
            type: 'warning',
        );
    }

    /**
     * توحيدُ العربية قبل المقارنة.
     *
     * «إلغاء» و«الغاء» و«الغــاء» كلمةٌ واحدة يكتبها الناس بثلاث صور،
     * ومقارنةٌ حرفيّةٌ تقبل واحدةً وترفض اثنتين — فيكتب الموكّل طلب
     * الإيقاف ولا يُسمع.
     */
    public static function normalizeArabic(string $value): string
    {
        $text = mb_strtolower(trim($value));

        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ـ' => '',
        ]);

        // التشكيل وعلاماتُ الترقيم لا تُغيّر المعنى ولا تُقارَن
        $text = (string) preg_replace('/[\x{064B}-\x{0652}]/u', '', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
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

    /**
     * طابعُ Meta الزمني بتوقيت التطبيق — لا بـUTC.
     *
     * ═══ العطل الذي وُضع له ═══
     *
     * ‏createFromTimestamp تُرجع لحظةً بإزاحة ‎+00:00‎، وما يُكتب في
     * قاعدة البيانات هو ساعةُ الحائط لا اللحظة. فبينما تكتب now()
     * ساعةَ مسقط ‎(+04:00)‎، يُحفظ الوارد متأخّراً أربعَ ساعات — وتُقاس
     * عليه نافذةُ الأربع والعشرين ساعة، فتصير عشرين. ويُمنع المحامي من
     * الردّ الحرّ وMeta ما زالت تقبله.
     */
    protected function timestamp(mixed $unix): \Illuminate\Support\Carbon
    {
        if (is_numeric($unix) && (int) $unix > 0) {
            return \Illuminate\Support\Carbon::createFromTimestamp((int) $unix)
                ->setTimezone(config('app.timezone'));
        }

        return now();
    }

    /** هل هذا النوع ضمن ما تقبله Meta وما أعلنّاه في الإعدادات؟ */
    protected function mimeAllowed(?string $mime): bool
    {
        if ($mime === null || $mime === '') {
            return false;
        }

        foreach ((array) config('whatsapp.media', []) as $type) {
            if (in_array($mime, (array) ($type['mimes'] ?? []), true)) {
                return true;
            }
        }

        return false;
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
