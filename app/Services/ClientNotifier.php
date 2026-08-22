<?php

namespace App\Services;

use App\Models\LegalCase;
use App\Support\ClientMessage;
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
                    Mail::raw(ClientMessage::caseUpdate($case), function ($m) use ($client, $case) {
                        $m->from(ClientMessage::fromAddress(), ClientMessage::officeName());
                        $m->to($client->email)
                            ->subject(ClientMessage::updateSubject($case));
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
