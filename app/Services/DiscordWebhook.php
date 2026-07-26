<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DiscordWebhook
{
    const STORAGE_PATH = 'discord-monitor-message.json';

    public function sendOrUpdate(string $webhookUrl, array $embed): void
    {
        $messageId = $this->getStoredMessageId($webhookUrl);

        if ($messageId) {
            $this->editMessage($webhookUrl, $messageId, $embed);
        } else {
            $this->sendMessage($webhookUrl, $embed);
        }
    }

    protected function sendMessage(string $webhookUrl, array $embed): void
    {
        $payload = json_encode([
            'embeds' => [$embed],
            'avatar_url' => null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init($webhookUrl . '?wait=true');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 200 && $res) {
            $data = json_decode($res, true);
            if (isset($data['id'])) {
                $this->storeMessageId($webhookUrl, $data['id']);
            }
        }
    }

    protected function editMessage(string $webhookUrl, string $messageId, array $embed): void
    {
        $payload = json_encode([
            'embeds' => [$embed],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init("$webhookUrl/messages/$messageId");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 404) {
            $this->forgetMessageId($webhookUrl);
            $this->sendMessage($webhookUrl, $embed);
        }
    }

    protected function getStoredMessageId(string $webhookUrl): ?string
    {
        $hash = md5($webhookUrl);
        $data = $this->readStorage();
        return $data[$hash] ?? null;
    }

    protected function storeMessageId(string $webhookUrl, string $messageId): void
    {
        $hash = md5($webhookUrl);
        $data = $this->readStorage();
        $data[$hash] = $messageId;
        $this->writeStorage($data);
    }

    protected function forgetMessageId(string $webhookUrl): void
    {
        $hash = md5($webhookUrl);
        $data = $this->readStorage();
        unset($data[$hash]);
        $this->writeStorage($data);
    }

    protected function readStorage(): array
    {
        if (!Storage::exists(self::STORAGE_PATH)) {
            return [];
        }
        return json_decode(Storage::get(self::STORAGE_PATH), true) ?: [];
    }

    protected function writeStorage(array $data): void
    {
        Storage::put(self::STORAGE_PATH, json_encode($data, JSON_PRETTY_PRINT));
    }
}
