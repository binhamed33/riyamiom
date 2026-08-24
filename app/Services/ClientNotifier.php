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

            self::sendWhatsApp($client->phone, $case);
        } catch (\Throwable $e) {
            Log::error('Client update notification failed for case ' . $case->id . ': ' . $e->getMessage());
        }
    }

    public static function updateMessage(): string
    {
        return ClientMessage::caseUpdate();
    }

    public static function sendWhatsApp(?string $phone, LegalCase $case): bool
    {
        if (!$phone) {
            return false;
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
