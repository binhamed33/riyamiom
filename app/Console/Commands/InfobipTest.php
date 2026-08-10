<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class InfobipTest extends Command
{
    protected $signature = 'infobip:test {phone} {--name=موكل تجريبي} {--case=1234/2026}';

    protected $description = 'اختبار إرسال واتساب عبر Infobip وعرض الاستجابة كاملة';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('services.infobip.base_url'), '/');
        $apiKey = (string) config('services.infobip.api_key');
        $sender = (string) config('services.infobip.sender');
        $template = (string) config('services.infobip.template');
        $language = (string) config('services.infobip.language');
        $vars = array_filter(array_map('trim', explode(',', (string) config('services.infobip.template_vars'))));

        $this->info('base_url: ' . ($baseUrl ?: 'فارغ'));
        $this->info('sender: ' . ($sender ?: 'فارغ'));
        $this->info('template: ' . ($template ?: 'فارغ'));

        if (!$baseUrl || !$apiKey || !$sender || !$template) {
            $this->error('أكمل في .env: INFOBIP_BASE_URL / INFOBIP_API_KEY / INFOBIP_SENDER / INFOBIP_TEMPLATE_NAME');
            return 1;
        }

        $phone = preg_replace('/[^0-9+]/', '', $this->argument('phone'));
        $phone = ltrim($phone, '+');

        $values = [
            'name' => $this->option('name'),
            'case' => $this->option('case'),
        ];

        $placeholders = [];
        foreach ($vars as $var) {
            $placeholders[] = $values[$var] ?? '';
        }

        $payload = [
            'messages' => [[
                'from' => $sender,
                'to' => $phone,
                'messageId' => (string) \Illuminate\Support\Str::uuid(),
                'content' => [
                    'templateName' => $template,
                    'templateData' => [
                        'body' => ['placeholders' => $placeholders],
                    ],
                    'language' => $language,
                ],
            ]],
        ];

        $this->line('إرسال إلى: ' . $phone . ' (placeholders: ' . implode(' | ', $placeholders) . ')');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'App ' . $apiKey,
                'Accept' => 'application/json',
            ])->timeout(30)->post("{$baseUrl}/whatsapp/1/message/template", $payload);

            $this->line('--- HTTP ' . $response->status() . ' ---');
            $this->line($response->body());

            if ($response->successful()) {
                $this->info('نجح الإرسال عبر Infobip');
                return 0;
            }

            $this->error('فشل الإرسال: ' . $response->body());
            return 1;
        } catch (\Throwable $e) {
            $this->error('استثناء: ' . $e->getMessage());
            return 1;
        }
    }
}