<?php

namespace App\Services;

use App\Services\Ai\AiManager;
use App\Services\Ai\AiProvider;

/**
 * غلاف توافقي.
 *
 * كان هذا الصنف يقرأ المفتاح من config/.env مباشرة، فكان كل المكاتب
 * تشترك في إعداد واحد. صار الآن يحلّ مزوّد المكتب من إعداداته الخاصة
 * عبر AiManager. أُبقي عليه حتى لا تنكسر أي شيفرة قائمة تستدعيه.
 *
 * @deprecated استخدم App\Services\Ai\AiManager::provider() في الشيفرة الجديدة.
 */
class GeminiService implements AiProvider
{
    protected ?AiProvider $provider;
    protected ?string $resolveError = null;

    public function __construct()
    {
        try {
            $this->provider = AiManager::provider();
        } catch (\RuntimeException $e) {
            $this->provider = null;
            $this->resolveError = $e->getMessage();
        }
    }

    public function isConfigured(): bool
    {
        return $this->provider?->isConfigured() ?? false;
    }

    public function analyze(string $prompt): ?string
    {
        return $this->provider?->analyze($prompt);
    }

    public function chat(array $history, string $systemPrompt): ?string
    {
        return $this->provider?->chat($history, $systemPrompt);
    }

    public function getLastError(): ?string
    {
        return $this->provider?->getLastError() ?? $this->resolveError;
    }

    public function testConnection(): array
    {
        return $this->provider?->testConnection()
            ?? ['ok' => false, 'message' => $this->resolveError ?: 'مزوّد الذكاء الاصطناعي غير مُعدّ.'];
    }
}
