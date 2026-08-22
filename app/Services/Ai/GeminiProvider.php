<?php

namespace App\Services\Ai;

use App\Support\AiSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProvider
{
    protected ?string $apiKey;
    protected string $model;
    protected ?int $lastStatus = null;
    protected ?string $lastError = null;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        // المفتاح والنموذج من إعدادات هذا المكتب، لا من ملف مشترك
        $this->apiKey = $apiKey ?: AiSettings::apiKey();
        $this->model = $model ?: AiSettings::model();
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function analyze(string $prompt): ?string
    {
        try {
            return $this->generate([
                'contents' => [['parts' => [['text' => $prompt]]]],
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
            $role = ($message['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $last = end($contents);
            if ($last !== false && $last['role'] === $role) {
                $contents[array_key_last($contents)]['parts'][] = ['text' => $message['content']];
            } else {
                $contents[] = ['role' => $role, 'parts' => [['text' => $message['content']]]];
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
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        ]);
    }

    /**
     * نداء خفيف جداً للتأكد من صلاحية المفتاح.
     * الرسائل هنا تُعرض للمستخدم، فلا تحتوي المفتاح ولا جسم خطأ المزوّد الخام.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'لم يُضبط مفتاح المزوّد بعد.'];
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['X-goog-api-key' => $this->apiKey])
                ->get('https://generativelanguage.googleapis.com/v1beta/models/' . $this->model);

            if ($response->successful()) {
                return ['ok' => true, 'message' => 'الاتصال ناجح — النموذج ' . $this->model . ' متاح.'];
            }

            return ['ok' => false, 'message' => $this->humanError($response->status())];
        } catch (\Throwable $e) {
            Log::warning('AI test connection failed: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'تعذّر الوصول إلى خدمة المزوّد. تأكد من اتصال الخادم بالإنترنت.'];
        }
    }

    /** ترجمة رمز الحالة إلى سبب مفهوم — بلا تسريب المفتاح أو تفاصيل داخلية. */
    protected function humanError(int $status): string
    {
        return match (true) {
            $status === 400, $status === 401, $status === 403 => 'المفتاح غير صالح أو لا يملك صلاحية استخدام هذه الخدمة.',
            $status === 404 => 'النموذج المحدد غير متاح لهذا المفتاح. اختر نموذجاً آخر.',
            $status === 429 => 'تم تجاوز حصة الاستخدام المسموحة لهذا المفتاح حالياً.',
            $status >= 500 => 'خدمة المزوّد غير متاحة مؤقتاً. حاول بعد قليل.',
            default => 'فشل الاتصال (رمز ' . $status . ').',
        };
    }

    protected function generate(array $payload): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $models = array_values(array_unique(array_filter([
            $this->model,
            ...config('ai.providers.gemini.fallback_models', []),
        ])));

        $lastTransientError = null;
        $maxAttemptsPerModel = 2; // محاولة إضافية عند الازدحام قبل الانتقال للنموذج الاحتياطي

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= $maxAttemptsPerModel; $attempt++) {
                try {
                    return $this->callModel($model, $payload);
                } catch (\RuntimeException $e) {
                    $retryable = in_array($this->lastStatus, [404, 429, 500, 502, 503], true);
                    if (!$retryable) {
                        throw $e;
                    }
                    $lastTransientError = $e;

                    // 404 = النموذج غير موجود → انتقل مباشرة للنموذج التالي بدون انتظار
                    if ($this->lastStatus === 404) {
                        break;
                    }

                    // 429/500/502/503 = ازدحام مؤقت → أعد المحاولة بعد فاصل قصير
                    if ($attempt < $maxAttemptsPerModel) {
                        usleep(1500000);
                    }
                }
            }
        }

        $this->lastError = 'خدمة الذكاء الاصطناعي مزدحمة حاليًا، حاول مرة أخرى بعد لحظات.';

        throw $lastTransientError
            ?? new \RuntimeException('Gemini API error — tried models: ' . implode(', ', $models));
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
                Log::error('Gemini API error (' . $model . '): ' . $status . ' - ' . $response->body());
                if ($status === 429) {
                    throw new \RuntimeException('تم تجاوز حصة مفتاح الذكاء الاصطناعي أو أن المفتاح غير صالح. حدّثه من الإعدادات ← الذكاء الاصطناعي.');
                }
                if ($status === 503) {
                    throw new \RuntimeException('النموذج ' . $model . ' مزدحم حاليًا. سيتم تجربة نموذج احتياطي تلقائيًا، وإن استمرت المشكلة حاول لاحقًا.');
                }
                throw new \RuntimeException($this->humanError($status));
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if ($text === null) {
                throw new \RuntimeException('لم تُرجع الخدمة أي محتوى. حاول مرة أخرى.');
            }

            return $text;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Gemini API exception (' . $model . '): ' . $e->getMessage());
            throw new \RuntimeException('تعذّر الاتصال بخدمة الذكاء الاصطناعي.');
        }
    }
}
