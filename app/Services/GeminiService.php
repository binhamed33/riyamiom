<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?: null;
        $this->model = config('services.gemini.model', 'gemini-2.0-flash') ?: 'gemini-2.0-flash';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function analyze(string $prompt): ?string
    {
        try {
            return $this->generate([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Gemini analyze failed: ' . $e->getMessage());
            return null;
        }
    }

    public function chat(array $history, string $systemPrompt): ?string
    {
        $contents = [];
        foreach ($history as $message) {
            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            $last = end($contents);
            if ($last !== false && $last['role'] === $role) {
                $contents[array_key_last($contents)]['parts'][] = ['text' => $message['content']];
            } else {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $message['content']]],
                ];
            }
        }

        if (empty($contents) || $contents[0]['role'] !== 'user') {
            array_unshift($contents, [
                'role' => 'user',
                'parts' => [['text' => 'مرحباً، أريد الاستفسار عن قضيتي.']],
            ]);
        }

        return $this->generate([
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
        ]);
    }

    protected function generate(array $payload): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->apiKey, array_merge($payload, [
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 2048,
                    ],
                ]));

            if (!$response->successful()) {
                $status = $response->status();
                $errorBody = $response->body();
                Log::error('Gemini API error: ' . $status . ' - ' . $errorBody);
                if ($status === 429) {
                    throw new \RuntimeException('تم تجاوز الحصة المجانية من خدمة Gemini أو أن مفتاح API غير صالح. يرجى إنشاء مفتاح جديد من https://aistudio.google.com/apikey ثم تحديث GEMINI_API_KEY في ملف .env');
                }
                throw new \RuntimeException('Gemini API responded with status ' . $status . ': ' . $errorBody);
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if ($text === null) {
                throw new \RuntimeException('Gemini returned an empty or unexpected response');
            }

            return $text;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Gemini API exception: ' . $e->getMessage());
            throw new \RuntimeException('Gemini connection error: ' . $e->getMessage());
        }
    }
}
