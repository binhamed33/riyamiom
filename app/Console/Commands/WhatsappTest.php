<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappTest extends Command
{
    protected $signature = 'whatsapp:test {phone} {--name=موكل تجريبي} {--case=1234/2026}';

    protected $description = 'إرسال رسالة اختبار عبر Meta Cloud API وعرض الاستجابة كاملة للتشخيص';

    public function handle(): int
    {
        $metaToken = config('services.whatsapp.meta_token', '');
        $metaPhoneId = config('services.whatsapp.meta_phone_id', '');
        $template = config('services.whatsapp.template', '');

        $this->info('token: ' . ($metaToken ? 'مضبوط (' . strlen($metaToken) . ' حرفاً)' : 'فارغ'));
        $this->info('phone_id: ' . ($metaPhoneId ?: 'فارغ'));
        $this->info('template: ' . ($template ?: 'فارغ'));

        if (!$metaToken || !$metaPhoneId || !$template) {
            $this->error('أكمل الإعدادات في .env: WHATSAPP_META_TOKEN / WHATSAPP_META_PHONE_ID / WHATSAPP_TEMPLATE_NAME');
            return 1;
        }

        $phone = preg_replace('/[^0-9+]/', '', $this->argument('phone'));
        $phone = ltrim($phone, '+');

        $this->line('إرسال إلى: ' . $phone . ' (قالب: ' . $template . ')');

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => 'ar'],
                'components' => [
                    ['type' => 'body', 'parameters' => [
                        ['type' => 'text', 'text' => $this->option('name')],
                        ['type' => 'text', 'text' => $this->option('case')],
                        ['type' => 'text', 'text' => 'https://office.riyami.om/client-access'],
                    ]],
                ],
            ],
        ];

        try {
            $response = Http::withToken($metaToken)
                ->timeout(30)
                ->post("https://graph.facebook.com/v21.0/{$metaPhoneId}/messages", $payload);

            $this->line('--- HTTP ' . $response->status() . ' ---');
            $this->line($response->body());

            if ($response->successful()) {
                $this->info('نجح الإرسال. معرف الرسالة: ' . ($response->json('messages.0.id') ?? 'غير معروف'));
                return 0;
            }

            $err = $response->json('error');
            if (is_array($err)) {
                $this->error('رمز الخطأ: ' . ($err['code'] ?? '?') . ' — ' . ($err['message'] ?? '?'));
                if (!empty($err['error_data']['details'])) {
                    $this->error('التفاصيل: ' . $err['error_data']['details']);
                }
            }
            Log::error('whatsapp:test failed: status=' . $response->status() . ' body=' . $response->body());
            return 1;
        } catch (\Throwable $e) {
            $this->error('استثناء: ' . $e->getMessage());
            return 1;
        }
    }
}
