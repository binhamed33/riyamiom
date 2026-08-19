<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;

    public function getLastError(): ?string
    {
        return $this->lastError ?? null;
    }

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?: null;
        $this->model = config('services.gemini.model', 'gemini-flash-latest') ?: 'gemini-flash-latest';
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
            $this->lastError = $e->getMessage();
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

    protected ?int $lastStatus = null;
    protected ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    protected function generate(array $payload): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $models = array_values(array_unique(array_filter([
            $this->model,
            ...config('services.gemini.fallback_models', []),
        ])));

        $lastTransientError = null;
        foreach ($models as $model) {
            try {
                return $this->callModel($model, $payload);
            } catch (\RuntimeException $e) {
                $isTransient = in_array($this->lastStatus, [429, 503], true);
                if (!$isTransient) {
                    throw $e;
                }
                $lastTransientError = $e;
            }
        }

        $tried = implode(', ', $models);
        $this->lastError = $lastTransientError?->getMessage() ?? ('Gemini API error — tried models: ' . $tried);
        throw $lastTransientError
            ?? new \RuntimeException('Gemini API error — tried models: ' . $tried);
    }

    protected function callModel(string $model, array $payload): string
    {
        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-goog-api-key' => $this->apiKey,
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent', array_merge($payload, [
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 8192,
                    ],
                ]));

            $this->lastStatus = $response->status();

            if (!$response->successful()) {
                $status = $response->status();
                $errorBody = $response->body();
                Log::error('Gemini API error (' . $model . '): ' . $status . ' - ' . $errorBody);
                if ($status === 429) {
                    throw new \RuntimeException('تم تجاوز الحصة المجانية من خدمة Gemini أو أن مفتاح API غير صالح. يرجى إنشاء مفتاح جديد من https://aistudio.google.com/apikey ثم تحديث GEMINI_API_KEY في ملف .env');
                }
                if ($status === 503) {
                    throw new \RuntimeException('النموذج ' . $model . ' مزدحم حاليًا من خدمة Gemini. سيتم تجربة نموذج احتياطي تلقائيًا، وإن استمرت المشكلة حاول لاحقًا.');
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
            Log::error('Gemini API exception (' . $model . '): ' . $e->getMessage());
            throw new \RuntimeException('Gemini connection error: ' . $e->getMessage());
        }
    }
}
