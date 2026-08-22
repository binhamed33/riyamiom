<?php

namespace App\Services\Ai;

/**
 * عقد مزوّد الذكاء الاصطناعي.
 *
 * إضافة مزوّد جديد = صنف يطبّق هذا العقد + مدخل في config/ai.php
 * بقيمة implemented => true. لا شيء آخر في النظام يحتاج تعديلاً.
 */
interface AiProvider
{
    /** هل لهذا المكتب مفتاح صالح للاستعمال؟ */
    public function isConfigured(): bool;

    /** طلب نصّي واحد. يرجع null عند الفشل مع تسجيل السبب في getLastError. */
    public function analyze(string $prompt): ?string;

    /** محادثة متعددة الأدوار مع تعليمات نظام. */
    public function chat(array $history, string $systemPrompt): ?string;

    public function getLastError(): ?string;

    /**
     * فحص اتصال حقيقي بالمزوّد.
     * يرجع ['ok' => bool, 'message' => string] — والرسالة لا تتضمن المفتاح أبداً.
     */
    public function testConnection(): array;
}
