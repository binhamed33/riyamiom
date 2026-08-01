<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function analyze(string $prompt): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 2048,
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('Gemini API error: ' . $response->status() . ' - ' . $response->body());
                return null;
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            return $text;
        } catch (\Exception $e) {
            Log::error('Gemini API exception: ' . $e->getMessage());
            return null;
        }
    }
}
