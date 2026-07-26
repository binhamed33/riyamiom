<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class DiscordHandler extends AbstractProcessingHandler
{
    protected string $webhookUrl;

    public function __construct(string $webhookUrl, array $config = [])
    {
        parent::__construct(
            $config['level'] ?? Level::Error,
            $config['bubble'] ?? true
        );
        $this->webhookUrl = $webhookUrl;
    }

    protected function write(LogRecord $record): void
    {
        $data = [
            'username' => config('app.name', 'LexPro'),
            'avatar_url' => null,
            'content' => null,
            'embeds' => [
                [
                    'title' => $record->level->getName() . ': ' . mb_substr($record->message, 0, 256),
                    'description' => mb_substr($record->message, 0, 2000),
                    'color' => match (true) {
                        $record->level->value >= 600 => 0xDC143C,
                        $record->level->value >= 500 => 0xFF4500,
                        $record->level->value >= 400 => 0xFFA500,
                        default => 0xFFFF00,
                    },
                    'timestamp' => $record->datetime->format('c'),
                    'footer' => ['text' => $record->channel . ' • ' . request()->fullUrl()],
                    'fields' => [],
                ],
            ],
        ];

        $trace = $record->context['exception'] ?? null;
        if ($trace && $trace instanceof \Throwable) {
            $data['embeds'][0]['fields'][] = [
                'name' => 'File',
                'value' => $trace->getFile() . ':' . $trace->getLine(),
                'inline' => true,
            ];
            $data['embeds'][0]['fields'][] = [
                'name' => 'Type',
                'value' => get_class($trace),
                'inline' => true,
            ];
            $data['embeds'][0]['fields'][] = [
                'name' => 'Trace',
                'value' => mb_substr('# ' . implode("\n# ", explode("\n", $trace->getTraceAsString())), 0, 1000),
                'inline' => false,
            ];
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
