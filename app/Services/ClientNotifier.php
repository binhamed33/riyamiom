<?php

namespace App\Services;

use App\Models\LegalCase;
use App\Mail\ClientCaseMail;
use App\Mail\ClientEventMail;
use App\Mail\MailKind;
use App\Support\ClientMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClientNotifier
{
    /**
     * إشعارُ الموكّل بجلسةٍ قادمة — مُطفأٌ حتى يطلبه المكتب.
     *
     * وتفاصيلُ الجلسة تُبنى هنا حيث تُعرف، لا في طبقة البريد.
     */
    public static function notifySession(LegalCase $case, \App\Models\Session $session): void
    {
        try {
            $case->loadMissing('client');
            $client = $case->client;

            if (!$client) {
                return;
            }

            $when = $session->date
                ? \Illuminate\Support\Carbon::parse($session->date)->locale('ar')->isoFormat('dddd D MMMM YYYY')
                : null;

            $lines = array_filter([
                'نُفيدكم بأنّ لقضيتكم جلسةً مسجَّلة لدى المكتب.',
                $case->case_number ? 'رقم القضية: ' . $case->case_number : null,
                $when ? 'التاريخ: ' . $when : null,
                $session->location ? 'المحكمة/المكان: ' . $session->location : null,
            ]);

            OfficeMailer::send($client->email, new ClientEventMail(
                MailKind::SessionNotice,
                'إشعار بجلسة',
                implode("\n\n", $lines),
                (string) $client->name,
                $case->case_number,
            ));
        } catch (\Throwable $e) {
            Log::error('Session notice failed for case ' . $case->id . ': ' . $e->getMessage());
        }
    }

    /**
     * إشعارُ الموكّل بمستندٍ أُتيح له — مُطفأٌ حتى يطلبه المكتب.
     *
     * ولا يُذكر اسمُ الملف في البريد: قد يحمل اسماً يكشف ما لا يُراد
     * كشفُه في صندوق بريدٍ قد يقرؤه غيرُه. يُقال إنّ مستنداً أُتيح،
     * ويُقرأ في البوابة خلف تحقّقٍ من الهوية.
     */
    public static function notifyDocument(\App\Models\Document $document): void
    {
        try {
            $document->loadMissing('case.client');
            $case = $document->case;
            $client = $case?->client;

            if (!$client) {
                return;
            }

            $lines = array_filter([
                'نُفيدكم بإتاحة مستندٍ جديد في بوابة متابعة قضيتكم.',
                $case->case_number ? 'رقم القضية: ' . $case->case_number : null,
                'يمكنكم الاطّلاع عليه بعد الدخول إلى البوابة.',
            ]);

            OfficeMailer::send($client->email, new ClientEventMail(
                MailKind::DocumentNotice,
                'إشعار بمستند',
                implode("\n\n", $lines),
                (string) $client->name,
                $case->case_number,
            ));
        } catch (\Throwable $e) {
            Log::error('Document notice failed for document ' . $document->id . ': ' . $e->getMessage());
        }
    }

    public static function notifyCaseUpdate(LegalCase $case): void
    {
        try {
            $case->loadMissing('client');
            $client = $case->client;

            if (!$client) {
                return;
            }

            // البريد يمرّ من البابِ الواحد: يُجدوَل ولا يُرسَل داخل الطلب،
            // ولا يُفشِل شيئاً مهما جرى.
            OfficeMailer::send(
                $client->email,
                new ClientCaseMail(MailKind::CaseUpdated, $case, (string) $client->name),
            );

            // واتسابُ الموكّل من بابه الواحد وحده (منظومة الإشعارات
            // برابط البوابة) — المسارُ القديم هنا كان يرسل رسالةً
            // ثانية عن الحدث نفسِه، وأُغلق بقرارٍ نصّي: «ما أريد غير
            // هذا الشيء فقط». البريدُ يبقى من هنا كما هو.
        } catch (\Throwable $e) {
            Log::error('Client update notification failed for case ' . $case->id . ': ' . $e->getMessage());
        }
    }

    public static function updateMessage(): string
    {
        return ClientMessage::caseUpdate();
    }

    /**
     * هل طلب صاحبُ هذا الرقم إيقافَ المراسلة؟
     *
     * يُسأل قبل كلّ إرسال — بالطريق الجديد والقديم معاً. وغيابُ سجلٍّ
     * لجهة الاتصال يعني أنّه لم يُسجَّل رفضٌ، وهو مسموح.
     */
    private static function hasOptedOut(string $phone): bool
    {
        try {
            $waId = \App\Models\WhatsAppContact::normalizeWaId($phone);

            if ($waId === '') {
                return false;
            }

            return \App\Models\WhatsAppContact::where('wa_id', $waId)
                ->whereNotNull('opted_out_at')
                ->exists();
        } catch (\Throwable) {
            // جدولٌ غير مهاجَر بعد: لا رفضَ مسجَّلاً، ولا يُعطَّل الإشعار
            return false;
        }
    }

    /**
     * كتابةُ الإشعار في خيط واتساب ودفعُه للطابور.
     *
     * يعيد null إن تعذّر — فيسقط النداءُ إلى المسار القديم بدل أن
     * يُحرَم الموكّل إشعارَه لأنّ الطريق الجديد لم يكتمل.
     */
    private static function queueThroughInbox(string $phone, LegalCase $case): ?bool
    {
        try {
            $waId = \App\Models\WhatsAppContact::normalizeWaId($phone);

            if ($waId === '') {
                return null;
            }

            $contact = \App\Models\WhatsAppContact::firstOrCreate(
                ['wa_id' => $waId],
                ['client_id' => $case->client_id]
            );

            // من طلب إيقاف المراسلة لا يُراسَل — ولا يُحاوَل بالمسار
            // القديم أيضاً: «false» لا «null»، فالرفضُ قرارُ العميل
            // لا عطلٌ نتجاوزه.
            if (!$contact->acceptsNotifications()) {
                return false;
            }

            if ($contact->client_id === null && $case->client_id) {
                $contact->forceFill(['client_id' => $case->client_id])->save();
            }

            $conversation = \App\Models\WhatsAppConversation::firstOrCreate(
                ['contact_id' => $contact->id],
                ['status' => \App\Models\WhatsAppConversation::STATUS_OPEN, 'unread_count' => 0]
            );

            if ($conversation->case_id === null) {
                $conversation->forceFill(['case_id' => $case->id])->save();
            }

            $inbox = app(\App\Services\WhatsApp\InboxService::class);
            $template = \App\Support\WhatsAppSettings::templateName(
                \App\Support\WhatsAppSettings::KEY_SESSION_TEMPLATE
            );

            // داخل النافذة: نصٌّ حرّ. خارجها: لا يمرّ إلا قالبٌ معتمَد —
            // وبلا قالبٍ مضبوط لا نتظاهر بالإرسال، بل نُسقط للقديم.
            if ($conversation->windowOpen()) {
                $message = $inbox->queueOutgoing($conversation, 'text', self::updateMessage());
            } elseif ($template !== '') {
                $message = $inbox->queueOutgoing(
                    $conversation,
                    'template',
                    json_encode([$case->case_number ?: '—', ClientMessage::portalUrl()], JSON_UNESCAPED_UNICODE),
                    null,
                    ['template_name' => $template],
                );
            } else {
                return null;
            }

            \App\Jobs\SendWhatsAppMessage::dispatch($message->id);

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp inbox notify failed, falling back: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * إشعارُ الموكّل عبر واتساب.
     *
     * ═══ لماذا يمرّ بصندوق الوارد أوّلاً ═══
     *
     * كان هذا الطريق يُطلق نداءً مباشراً إلى Meta وينسى: لا أثرَ في
     * النظام لما أُرسل، ولا حالةَ تسليم، ولا يرى المحامي في محادثة
     * موكّله أنّ النظام راسله. فإن ردّ الموكّل «أيّ قضية؟» لم يجد
     * الموظّفُ سياقاً.
     *
     * فصار يُكتب في الخيط ويُرسَل من الطابور — فالمرسَل مرئيٌّ وحالتُه
     * متتبَّعة، والردُّ يقع في مكانه. والمسارُ القديم يبقى احتياطاً
     * للمكاتب التي لم تربط رقمها بعد.
     */
    public static function sendWhatsApp(?string $phone, LegalCase $case): bool
    {
        if (!$phone) {
            return false;
        }

        // ═══ الرفضُ والإطفاءُ يحكمان كلَّ الطرق لا الطريقَ الجديد وحده ═══
        //
        // كان المسارُ القديم (النداء المباشر إلى Meta من ملف البيئة)
        // يُنفَّذ حين لا يتحقّق شرطُ المسار الجديد — فمكتبٌ أطفأ إشعارات
        // تحديث القضايا يظلّ يرسلها، وموكّلٌ كتب «إيقاف» يظلّ يتلقّاها.
        //
        // وتجاهلُ الرفض ليس خطأً في الأدب فحسب: البلاغاتُ عنه تُنزل
        // تقييمَ جودة الرقم عند Meta وقد تُقيّد إرسالَه كلَّه، فيُحرَم
        // كلُّ موكّلي المكتب من إشعاراتهم بسبب واحد.
        if (!\App\Support\WhatsAppSettings::flag(\App\Support\WhatsAppSettings::KEY_NOTIFY_CASE_UPDATES)) {
            return false;
        }

        if (self::hasOptedOut($phone)) {
            return false;
        }

        if (\App\Services\WhatsApp\WhatsAppManager::isConnected()) {
            $queued = self::queueThroughInbox($phone, $case);

            if ($queued !== null) {
                return $queued;
            }
        }

        $metaToken = config('services.whatsapp.meta_token', '');
        $metaPhoneId = config('services.whatsapp.meta_phone_id', '');
        $waTemplate = config('services.whatsapp.template', '');
        $waUrl = config('services.whatsapp.url', '');
        $waToken = config('services.whatsapp.token', '');

        $phoneDigits = preg_replace('/^\+/', '', $phone);

        if ($metaToken && $metaPhoneId && $waTemplate) {
            try {
                $response = Http::withToken($metaToken)
                    ->timeout(30)
                    ->post("https://graph.facebook.com/v21.0/{$metaPhoneId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $phoneDigits,
                        'type' => 'template',
                        'template' => [
                            'name' => $waTemplate,
                            'language' => ['code' => 'ar'],
                            'components' => [
                                ['type' => 'body', 'parameters' => [
                                    ['type' => 'text', 'text' => $case->case_number ?: '—'],
                                    ['type' => 'text', 'text' => ClientMessage::portalUrl()],
                                ]],
                            ],
                        ],
                    ]);
                if ($response->successful()) {
                    return true;
                }
                Log::error('ClientNotifier whatsapp (meta) failed: ' . $response->body());
            } catch (\Throwable $e) {
                Log::error('ClientNotifier whatsapp (meta) exception: ' . $e->getMessage());
            }
            return false;
        }

        if ($waUrl && $waToken) {
            try {
                $chatId = str_contains($phoneDigits, '@') ? $phoneDigits : $phoneDigits . '@c.us';
                $response = Http::timeout(30)
                    ->post(rtrim($waUrl, '/') . '/sendMessage/' . $waToken, [
                        'chatId' => $chatId,
                        'message' => self::updateMessage(),
                    ]);
                if ($response->successful()) {
                    return true;
                }
                Log::error('ClientNotifier whatsapp (green) failed: ' . $response->body());
            } catch (\Throwable $e) {
                Log::error('ClientNotifier whatsapp (green) exception: ' . $e->getMessage());
            }
            return false;
        }

        return false;
    }
}
