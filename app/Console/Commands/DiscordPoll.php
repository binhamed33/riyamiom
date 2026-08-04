<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\Setting;
use App\Services\DiscordNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordPoll extends Command
{
    protected $signature = 'discord:poll';

    protected $description = 'Check Discord for developer replies and sync them back to the chat';

    public function handle(): int
    {
        $token = config('services.discord.bot_token', '');
        $channelId = DiscordNotifier::channelId();

        if (!$token) {
            $this->error('DISCORD_BOT_TOKEN غير مضبوط في .env');
            Log::warning('discord:poll skipped — bot token missing');
            return 1;
        }

        if (!$channelId) {
            $this->error('تعذر تحديد القناة (DISCORD_CHANNEL_ID أو ويبهوك غير صالح)');
            Log::warning('discord:poll skipped — channel id unresolved');
            return 1;
        }

        $query = ['limit' => 50];
        $cursor = Setting::get('discord_last_poll_id');
        if ($cursor) {
            $query['after'] = $cursor;
        }

        $response = Http::withHeaders(['Authorization' => 'Bot ' . $token])
            ->timeout(20)
            ->get("https://discord.com/api/v10/channels/{$channelId}/messages", $query);

        if (!$response->successful()) {
            $this->error('فشل الاتصال بديسكورد: ' . $response->status() . ' ' . $response->body());
            Log::error('Discord poll failed: ' . $response->status() . ' ' . $response->body());
            return 1;
        }

        $discordMessages = collect($response->json());
        if ($discordMessages->isEmpty()) {
            $this->info('لا توجد رسائل جديدة');
            return 0;
        }

        $processed = null;
        $created = 0;
        $unmatched = 0;

        foreach ($discordMessages->sortBy('id') as $discordMessage) {
            $processed = $discordMessage['id'];
            $reference = $discordMessage['message_reference'] ?? null;

            if (empty($reference['message_id'])) {
                $unmatched++;
                continue;
            }

            $original = Message::where('discord_message_id', $reference['message_id'])->first();

            if (!$original) {
                $unmatched++;
                $this->line('تجاهل رد غير مرتبط برسالة من الموقع (id: ' . $reference['message_id'] . ')');
                continue;
            }

            $developer = $original->conversation->participants()
                ->where('role', 'developer')
                ->where('user_id', '!=', $original->user_id)
                ->first();

            $replyText = trim($discordMessage['content'] ?? '');

            if (!$developer) {
                $unmatched++;
                $this->line('تجاهل: لا يوجد مطور في المحادثة #' . $original->conversation_id);
                continue;
            }

            if ($replyText === '') {
                $unmatched++;
                $this->line('تجاهل رد فارغ على رسالة #' . $original->id);
                continue;
            }

            Message::create([
                'conversation_id' => $original->conversation_id,
                'user_id' => $developer->id,
                'message' => $replyText,
                'discord_message_id' => (string) $discordMessage['id'],
            ]);

            $original->update(['discord_replied_at' => now()]);
            $original->conversation->touch();
            $created++;

            $this->line("تم تحويل رد المطور إلى محادثة #{$original->conversation_id}");
        }

        if ($processed) {
            Setting::set('discord_last_poll_id', (string) $processed);
        }

        $this->info("اكتمل الفحص: {$created} ردود محوّلة، {$unmatched} تم تجاهلها");

        return 0;
    }
}
