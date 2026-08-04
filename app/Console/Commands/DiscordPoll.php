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

        if (!$token || !$channelId) {
            return 0;
        }

        $query = ['limit' => 50];
        $cursor = Setting::get('discord_last_poll_id');
        if ($cursor) {
            $query['after'] = $cursor;
        }

        $response = Http::withToken($token)
            ->timeout(20)
            ->get("https://discord.com/api/v10/channels/{$channelId}/messages", $query);

        if (!$response->successful()) {
            Log::error('Discord poll failed: ' . $response->status() . ' ' . $response->body());
            return 1;
        }

        $discordMessages = collect($response->json());
        if ($discordMessages->isEmpty()) {
            return 0;
        }

        $processed = null;
        foreach ($discordMessages->sortBy('id') as $discordMessage) {
            $reference = $discordMessage['message_reference'] ?? null;

            if (!empty($reference['message_id'])) {
                $original = Message::where('discord_message_id', $reference['message_id'])->first();

                if ($original) {
                    $developer = $original->conversation->participants()
                        ->where('role', 'developer')
                        ->where('user_id', '!=', $original->user_id)
                        ->first();

                    $replyText = trim($discordMessage['content'] ?? '');

                    if ($developer && $replyText !== '') {
                        Message::create([
                            'conversation_id' => $original->conversation_id,
                            'user_id' => $developer->id,
                            'message' => $replyText,
                            'discord_message_id' => (string) $discordMessage['id'],
                        ]);

                        $original->update(['discord_replied_at' => now()]);
                        $original->conversation->touch();
                    }
                }
            }

            $processed = $discordMessage['id'];
        }

        if ($processed) {
            Setting::set('discord_last_poll_id', (string) $processed);
        }

        return 0;
    }
}
