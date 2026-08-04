<?php

namespace App\Services;

use App\Models\LegalCase;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClientNotifier
{
    public static function notifyCaseUpdate(LegalCase $case): void
    {
        try {
            $case->loadMissing('client');
            $client = $case->client;

            if (!$client) {
                return;
            }

            if ($client->email) {
                try {
                    Mail::raw(self::updateMessage(), function ($m) use ($client, $case) {
                        $m->from(
                            Setting::get('office_email', config('mail.from.address', 'hello@example.com')),
                            Setting::get('office_name', config('mail.from.name', 'LexPro'))
                        );
                        $m->to($client->email)
                            ->subject('إشعار بتحديث بيانات قضيتك - شركة حمد الريامي للمحاماة (قضية ' . $case->case_number . ')');
                    });
                } catch (\Throwable $e) {
                    Log::error('Client update email failed for case ' . $case->id . ': ' . $e->getMessage());
                }
            }

            self::sendWhatsApp($client->phone, $case);
        } catch (\Throwable $e) {
            Log::error('Client update notification failed for case ' . $case->id . ': ' . $e->getMessage());
        }
    }

    public static function updateMessage(): string
    {
        return <<<TXT
يسرّ **شركة حمد الريامي للمحاماة (شركة مدنية للمحاماة)** إشعاركم بأنه **تم تحديث بياناتكم** في نظام المكتب، وذلك لضمان تقديم خدماتنا القانونية بصورة أكثر كفاءة وسهولة.

يمكنكم الآن الاطلاع على بياناتكم المحدثة، ومتابعة جميع المستجدات المتعلقة بقضاياكم وخدماتكم، من خلال الدخول إلى الرابط التالي:

https://office.riyami.om/client-access

بعد فتح الرابط، يُرجى إدخال **رقم الهاتف** أو **البريد الإلكتروني** المسجل لدى المكتب، لتظهر لكم بياناتكم المحدثة، بالإضافة إلى جميع تفاصيل القضايا وآخر المستجدات المتعلقة بها.

وفي حال واجهتكم أي صعوبة في الدخول أو كانت لديكم أي استفسارات، فإن فريقنا على أتم الاستعداد لخدمتكم والإجابة عن جميع استفساراتكم.

**شركة حمد الريامي للمحاماة (شركة مدنية للمحاماة)**
نعتز بثقتكم، ونسعى دائماً إلى تقديم خدمات قانونية احترافية بأعلى معايير الجودة.
TXT;
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
                                    ['type' => 'text', 'text' => 'https://office.riyami.om/client-access'],
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
