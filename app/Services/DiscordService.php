<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('discord.webhook_url', '');
    }

    public function logException(\Throwable $e): bool
    {
        if (empty($this->webhookUrl)) {
            return false;
        }

        $payload = [
            'content' => '',
            'embeds' => [
                [
                    'title' => '🔴 مُداوَلة Exception: ' . class_basename($e),
                    'color' => 0xFF0000,
                    'fields' => [
                        ['name' => 'Message', 'value' => mb_substr($e->getMessage(), 0, 1000), 'inline' => false],
                        ['name' => 'File', 'value' => $e->getFile() . ':' . $e->getLine(), 'inline' => true],
                        ['name' => 'Code', 'value' => (string) $e->getCode(), 'inline' => true],
                    ],
                    'footer' => ['text' => 'مُداوَلة Error Monitor • ' . now()->format('Y-m-d H:i:s')],
                    'timestamp' => now()->toIso8601String(),
                ]
            ]
        ];

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $payload);
            return $response->successful();
        } catch (\Exception $ex) {
            Log::error('Failed to send exception to Discord: ' . $ex->getMessage());
            return false;
        }
    }

    public function serverStatus(array $stats): bool
    {
        if (empty($this->webhookUrl)) {
            return false;
        }

        $uptime = $this->formatUptime($stats['uptime']);
        $memUsage = $stats['memory_total'] > 0 ? round($stats['memory_used'] / $stats['memory_total'] * 100, 1) : 0;

        $statusColor = 0x00FF00;
        if ($memUsage > 80) $statusColor = 0xFF6600;
        if ($memUsage > 95) $statusColor = 0xFF0000;
        if (!$stats['laravel_running']) $statusColor = 0xFF0000;

        $statusEmoji = $stats['laravel_running'] ? '🟢' : '🔴';

        $payload = [
            'content' => '',
            'embeds' => [
                [
                    'title' => "{$statusEmoji} مُداوَلة Server Status",
                    'color' => $statusColor,
                    'fields' => [
                        ['name' => '⏱ Uptime', 'value' => $uptime, 'inline' => true],
                        ['name' => '📡 Response', 'value' => $stats['response_time'] . 'ms', 'inline' => true],
                        ['name' => '💾 Memory', 'value' => "{$memUsage}% ({$stats['memory_used']}MB/{$stats['memory_total']}MB)", 'inline' => true],
                        ['name' => '📁 Disk', 'value' => $stats['disk_used'] . 'GB / ' . $stats['disk_total'] . 'GB', 'inline' => true],
                        ['name' => '🗄 Database', 'value' => $stats['db_size'] . 'MB', 'inline' => true],
                        ['name' => '📦 Backups', 'value' => $stats['backup_count'] . ' files (' . $stats['backup_size'] . 'MB)', 'inline' => true],
                        ['name' => '👥 Users', 'value' => "{$stats['total_users']} total ({$stats['active_users']} active)", 'inline' => true],
                        ['name' => '⚖️ Cases', 'value' => "{$stats['total_cases']} total ({$stats['active_cases']} active)", 'inline' => true],
                        ['name' => '📋 Tasks', 'value' => "{$stats['total_tasks']} total ({$stats['pending_tasks']} pending)", 'inline' => true],
                    ],
                    'footer' => ['text' => 'مُداوَلة Monitor • ' . now()->format('Y-m-d H:i:s')],
                    'timestamp' => now()->toIso8601String(),
                ]
            ]
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->webhookUrl, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Discord status webhook failed: ' . $e->getMessage());
            return false;
        }
    }

    private function formatUptime(float $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        $parts[] = "{$minutes}m";

        return implode(' ', $parts);
    }
}
