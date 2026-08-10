<?php

namespace App\Services;

use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StaffDiscordService
{
    protected const STATUS_STORAGE = 'staff-discord-status.json';

    public function reportLogin(User $user, ?string $ip = null, ?string $userAgent = null): void
    {
        if ($user->role === 'client' || !$this->eventsWebhook()) {
            return;
        }

        try {
            LoginSession::create([
                'user_id'    => $user->id,
                'login_at'   => now(),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            $this->sendEvent('login', $user, null, $ip);
            $this->updateStatus();
        } catch (\Throwable $e) {
            Log::warning('staff discord login report failed: ' . $e->getMessage());
        }
    }

    public function reportLogout(User $user, ?string $ip = null): void
    {
        if ($user->role === 'client' || !$this->eventsWebhook()) {
            return;
        }

        try {
            $session = LoginSession::where('user_id', $user->id)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first();

            $duration = null;
            if ($session) {
                $end = now();
                $duration = $session->login_at ? max(0, (int) $session->login_at->diffInSeconds($end)) : null;
                $session->update([
                    'logout_at'        => $end,
                    'duration_seconds' => $duration,
                ]);
            }

            $this->sendEvent('logout', $user, $duration, $ip);
            $this->updateStatus();
        } catch (\Throwable $e) {
            Log::warning('staff discord logout report failed: ' . $e->getMessage());
        }
    }

    public function closeStaleSessions(): void
    {
        if (!$this->webhook()) {
            return;
        }

        try {
            $cutoff = now()->subHours(12);
            $stale = LoginSession::whereNull('logout_at')
                ->where('login_at', '<', $cutoff)
                ->with('user')
                ->get();

            foreach ($stale as $session) {
                $end = now();
                $duration = $session->login_at ? max(0, (int) $session->login_at->diffInSeconds($end)) : null;
                $session->update([
                    'logout_at'        => $end,
                    'duration_seconds' => $duration,
                ]);
            }

            if ($stale->isNotEmpty()) {
                $this->updateStatus();
            }
        } catch (\Throwable $e) {
            Log::warning('staff discord stale close failed: ' . $e->getMessage());
        }
    }

    public function updateStatus(): void
    {
        if (!$this->webhook()) {
            return;
        }

        try {
            $this->sendOrUpdateStatus();
        } catch (\Throwable $e) {
            Log::warning('staff discord status update failed: ' . $e->getMessage());
        }
    }

    protected function sendEvent(string $type, User $user, ?int $duration, ?string $ip): void
    {
        $isLogin = $type === 'login';

        $fields = [
            ['name' => 'الموظف', 'value' => $user->name, 'inline' => true],
            ['name' => 'الوظيفة', 'value' => $user->role ?: 'موظف', 'inline' => true],
            ['name' => 'الوقت', 'value' => now()->format('Y-m-d H:i:s'), 'inline' => true],
        ];

        if ($isLogin) {
            $fields[] = ['name' => 'IP', 'value' => $ip ?: 'غير معروف', 'inline' => true];
        } else {
            $fields[] = ['name' => 'مدة الجلسة', 'value' => $duration !== null ? $this->formatDuration($duration) : 'غير معروفة', 'inline' => true];
        }

        $online = LoginSession::whereNull('logout_at')->whereHas('user', fn($q) => $q->where('role', '!=', 'client'))->distinct('user_id')->count('user_id');
        $fields[] = ['name' => 'المتصلون الآن', 'value' => (string) $online, 'inline' => true];

        Http::timeout(5)->post($this->eventsWebhook(), [
            'content' => null,
            'embeds' => [[
                'title' => $isLogin ? '🟢 دخول موظف' : '🔴 خروج موظف',
                'color' => $isLogin ? 0x2ECC71 : 0xE74C3C,
                'fields' => $fields,
                'footer' => ['text' => 'LexPro - نظام حضور الموظفين'],
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);
    }

    protected function sendOrUpdateStatus(): void
    {
        $webhook = $this->webhook();
        $payload = json_encode([
            'embeds' => [$this->statusEmbed()],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $messageId = $this->storedMessageId();

        if ($messageId) {
            $ch = curl_init("$webhook/messages/$messageId");
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $res = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http === 404) {
                $this->forgetStoredMessageId();
                $this->sendOrUpdateStatus();
            }

            return;
        }

        $ch = curl_init($webhook . '?wait=true');
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
                $this->storeMessageId($data['id']);
            }
        }
    }

    protected function statusEmbed(): array
    {
        $todayStart = now()->startOfDay();

        $staff = User::where('role', '!=', 'client')->orderBy('name')->get();

        $open = LoginSession::whereNull('logout_at')
            ->with('user')
            ->orderBy('login_at')
            ->get()
            ->where('user.role', '!=', 'client')
            ->groupBy('user_id');

        $today = LoginSession::where('login_at', '>=', $todayStart)
            ->whereHas('user', fn($q) => $q->where('role', '!=', 'client'))
            ->get()
            ->groupBy('user_id');

        $lines = $staff->map(function (User $u) use ($open, $today) {
            $openSessions = $open->get($u->id, collect());
            $isOpen = $openSessions->isNotEmpty();

            $lastAct = Cache::get('staff_active_' . $u->id);
            $icon = '🔴';
            if ($isOpen && $lastAct && $lastAct->gte(now()->subMinutes(5))) {
                $icon = '🟢';
            } elseif ($isOpen) {
                $icon = '🌙';
            }

            $hours = $today->get($u->id, collect())->sum(function ($s) {
                $end = $s->logout_at ?? now();
                $start = $s->login_at ?? $end;
                return (int) $start->diffInSeconds($end);
            });

            return sprintf('%s **%s** — %s', $icon, $u->name, $this->formatDuration($hours));
        });

        if ($lines->isEmpty()) {
            $lines = collect(['لا يوجد موظفون']);
        }

        $fields = [
            ['name' => '👥 حالة الموظفين', 'value' => $lines->implode("\n"), 'inline' => false],
            ['name' => '🟢 متصل  •  🌙 داخل الموقع دون نشاط آخر 5 دقائق  •  🔴 غير متصل', 'value' => 'آخر تحديث: ' . now()->format('Y-m-d H:i:s'), 'inline' => false],
        ];

        return [
            'title' => '📊 لوحة تواجد الموظفين',
            'color' => 0x3498DB,
            'fields' => $fields,
            'footer' => ['text' => 'يحدَّث تلقائياً عند الدخول/الخروج وكل 5 دقائق'],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return $h > 0 ? "{$h} س {$m} د" : "{$m} د";
    }

    protected function webhook(): string
    {
        return (string) config('services.discord.staff_webhook', '');
    }

    protected function eventsWebhook(): string
    {
        return (string) config('services.discord.staff_events_webhook', '') ?: $this->webhook();
    }

    protected function storedMessageId(): ?string
    {
        $data = json_decode((string) Storage::get(self::STATUS_STORAGE), true) ?: [];
        return $data['id'] ?? null;
    }

    protected function storeMessageId(string $id): void
    {
        Storage::put(self::STATUS_STORAGE, json_encode(['id' => $id]));
    }

    protected function forgetStoredMessageId(): void
    {
        Storage::delete(self::STATUS_STORAGE);
    }
}