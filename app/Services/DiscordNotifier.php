<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordNotifier
{
    public static function webhookUrl(): string
    {
        return config('services.discord.webhook_url', '');
    }

    public static function channelId(): string
    {
        $channelId = config('services.discord.channel_id', '');
        if ($channelId) {
            return $channelId;
        }

        $webhook = self::webhookUrl();
        if (!$webhook) {
            return '';
        }

        try {
            $response = Http::timeout(10)->get($webhook);
            return $response->successful() ? (string) ($response->json('channel_id') ?? '') : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function sendChatMessage(Message $message): ?string
    {
        $webhook = self::webhookUrl();
        if (!$webhook) {
            return null;
        }

        $sender = $message->user;
        if (!$sender) {
            return null;
        }

        $text = trim($message->message ?? '');
        $description = mb_strlen($text) > 3500
            ? mb_substr($text, 0, 3500) . '…'
            : ($text ?: '*(مرفق ملف فقط)*');

        $embed = [
            'title' => 'رسالة جديدة إلى المطورين',
            'color' => 0xE67E22,
            'description' => $description,
            'fields' => [
                ['name' => 'الموظف', 'value' => $sender->name, 'inline' => true],
                ['name' => 'الدور', 'value' => self::roleLabel($sender->role), 'inline' => true],
                ['name' => 'الهاتف', 'value' => $sender->phone ?: '—', 'inline' => true],
                ['name' => 'البريد', 'value' => $sender->email ?: '—', 'inline' => true],
            ],
            'footer' => ['text' => 'نظام المكتب • محادثة #' . $message->conversation_id],
            'timestamp' => ($message->created_at ?? now())->toIso8601String(),
        ];

        $payload = [
            'username' => 'نظام المكتب — مُداوَلة',
            'embeds' => [$embed],
        ];

        $attachments = [];
        if ($message->attachment_path) {
            $filePath = storage_path('app/public/' . $message->attachment_path);
            $fileName = $message->attachment_name ?: basename($filePath);
            $size = $message->attachment_size ?: (file_exists($filePath) ? filesize($filePath) : 0);

            if (file_exists($filePath) && $size <= 8 * 1024 * 1024) {
                $attachments[] = [$filePath, $fileName];
            } else {
                $embed['description'] = ($text ?: '') . "\n\n⚠️ الملف «{$fileName}» كبير جداً لرفعه على ديسكورد";
                $payload['embeds'] = [$embed];
            }
        }

        try {
            $request = Http::timeout(30)->asMultipart();
            foreach ($attachments as $i => [$path, $name]) {
                $request = $request->attach('files[' . $i . ']', fopen($path, 'r'), $name);
            }

            $response = $request->post(self::webhookUrl() . '?wait=true', [
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            if ($response->successful()) {
                return (string) ($response->json('id') ?? '');
            }

            Log::error('Discord webhook send failed: ' . $response->body());
        } catch (\Throwable $e) {
            Log::error('Discord webhook send exception: ' . $e->getMessage());
        }

        return null;
    }

    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            'developer' => 'مطور',
            'admin' => 'مدير',
            'lawyer' => 'محامي',
            'staff' => 'موظف إداري',
            'client' => 'عميل',
            default => '—',
        };
    }

    public static function sendSuggestion($user, $suggestion): bool
    {
        $webhook = self::webhookUrl();
        if (!$webhook || !$user) {
            return false;
        }

        // نقبل نصاً أو نموذج اقتراح — حفاظاً على التوافق مع أي استدعاء قديم
        $content = is_string($suggestion) ? $suggestion : (string) $suggestion->content;
        $title = is_string($suggestion) ? null : $suggestion->title;
        $context = is_string($suggestion) ? [] : (array) ($suggestion->context ?? []);

        $text = trim($content);
        $description = mb_strlen($text) > 3500 ? mb_substr($text, 0, 3500) . '…' : $text;

        $office = $context['office'] ?? [];
        $origin = $context['origin'] ?? [];
        $device = $context['device'] ?? [];

        $fields = [
            ['name' => 'المكتب', 'value' => ($office['name'] ?? '—') . "\n`" . ($office['domain'] ?? '—') . '`', 'inline' => true],
            ['name' => 'المُرسِل', 'value' => $user->name . "\n" . self::roleLabel($user->role), 'inline' => true],
            ['name' => 'المعرّف والبريد', 'value' => '#' . $user->id . "\n" . $user->email, 'inline' => true],
        ];

        if (!empty($origin['page'])) {
            $fields[] = ['name' => 'الصفحة', 'value' => '`' . $origin['page'] . '`', 'inline' => true];
        }

        if ($device !== []) {
            $fields[] = [
                'name' => 'الجهاز',
                'value' => implode(' · ', array_filter([$device['type'] ?? null, $device['platform'] ?? null, $device['browser'] ?? null])),
                'inline' => true,
            ];
        }

        if (!is_string($suggestion) && $suggestion->id) {
            $fields[] = ['name' => 'رقم الاقتراح', 'value' => '#' . $suggestion->id, 'inline' => true];
        }

        $payload = [
            'username' => 'نظام المكتب — مُداوَلة',
            'embeds' => [[
                'title' => $title ? ('اقتراح: ' . mb_substr($title, 0, 200)) : 'اقتراح',
                'color' => 0x2563EB,
                'description' => $description,
                'fields' => $fields,
                'footer' => ['text' => 'مُداوَلة • صندوق الاقتراحات'],
                'timestamp' => now()->toIso8601String(),
            ]],
        ];

        try {
            $response = Http::timeout(30)->asMultipart()->post($webhook . '?wait=true', [
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Discord suggestion send failed: ' . $response->body());
        } catch (\Throwable $e) {
            Log::error('Discord suggestion send exception: ' . $e->getMessage());
        }

        return false;
    }
}
