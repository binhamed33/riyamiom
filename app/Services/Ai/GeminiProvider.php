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
    protected ?string $workingModel = null;
    protected ?string $suggestedByProvider = null;

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

        // المحادثة لا ترمي إلى الأعلى.
        //
        // analyze() كانت تلتقط وchat() لا تلتقط، فمفتاحٌ مرفوض (403)
        // يصعد استثناءً غير ملتقَط إلى الواجهة فيرى الموظّف «تعذّر
        // إتمام العملية» على كلمة «اهلا». والسبب الحقيقي — أن المفتاح
        // مرفوض — لا يصل إلى أحد.
        //
        // يُلتقط هنا، ويبقى السبب في getLastError() لمن يعرضه للمسؤول،
        // ويُسجَّل كاملاً في السجل.
        try {
            return $this->generate([
                'contents' => $contents,
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            ]);
        } catch (\Throwable $e) {
            Log::error('Gemini chat failed: ' . $e->getMessage());
            $this->lastError = $e->getMessage();

            return null;
        }
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

    /** آخر نموذج ردّ فعلاً — للتشخيص وللشفاء الذاتي. */
    public function getWorkingModel(): ?string
    {
        return $this->workingModel;
    }

    /**
     * البديل الذي يسمّيه المزوّد في نصّ الخطأ.
     *
     * حين يتقاعد نموذج تقول Google حرفياً:
     *   "This model models/X is no longer available to new users.
     *    Please update your code to use models/Y"
     *
     * وهذا أدقّ من أي قائمة نكتبها نحن: المزوّد يعرف بديله، ونحن
     * نكتب قائمةً تتقادم. فيُقرأ الاسم من الردّ ويُجرَّب.
     */
    protected function suggestedModel(string $body): ?string
    {
        if (!preg_match('#use\s+models/([A-Za-z0-9._-]+)#', $body, $m)) {
            return null;
        }

        return $m[1];
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

        $tried = [];
        $startedAt = microtime(true);
        $elapsed = fn (): int => (int) ((microtime(true) - $startedAt) * 1000);

        $lastTransientError = null;
        $maxAttemptsPerModel = 2; // محاولة إضافية عند الازدحام قبل الانتقال للنموذج الاحتياطي

        // القائمة تُستهلك لا تُدار بـforeach: النموذج الذي يسمّيه المزوّد
        // بديلاً يُضاف إلى آخرها أثناء التنفيذ، فيُجرَّب في نفس الطلب
        // بدل أن ينتظر تعديلاً منّا.
        while (($model = array_shift($models)) !== null) {
            if (in_array($model, $tried, true)) {
                continue;
            }
            $tried[] = $model;

            for ($attempt = 1; $attempt <= $maxAttemptsPerModel; $attempt++) {
                try {
                    $text = $this->callModel($model, $payload);
                    \App\Support\AiHealth::record('ok', 'gemini', $model, $elapsed(), null);

                    return $text;
                } catch (\RuntimeException $e) {
                    $retryable = in_array($this->lastStatus, [404, 429, 500, 502, 503], true);
                    if (!$retryable) {
                        \App\Support\AiHealth::record('error', 'gemini', $model, $elapsed(), 'http_' . ($this->lastStatus ?: 'x'));

                        throw $e;
                    }
                    $lastTransientError = $e;

                    // 404 = النموذج غير موجود → انتقل مباشرة للنموذج التالي.
                    //
                    // وإن سمّى المزوّد بديلاً في نصّ ردّه — وهو يفعل حين
                    // يتقاعد نموذج — فذاك أدقّ من قائمتنا: قائمتنا تتقادم
                    // وهو يعرف بديله. يُقدَّم على ما بقي.
                    if ($this->lastStatus === 404) {
                        if ($this->suggestedByProvider && !in_array($this->suggestedByProvider, $tried, true)) {
                            array_unshift($models, $this->suggestedByProvider);
                            $this->suggestedByProvider = null;
                        }

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
        \App\Support\AiHealth::record('error', 'gemini', $tried !== [] ? end($tried) : $this->model, $elapsed(), 'exhausted_' . ($this->lastStatus ?: 'x'));

        throw $lastTransientError
            ?? new \RuntimeException('Gemini API error — tried models: ' . implode(', ', $tried));
    }

    /**
     * نماذج 2.5/3.x «تفكّر» قبل أن تجيب افتراضياً — ثوانٍ صامتة تُحرق
     * قبل أول حرف، والمستخدم يراها تجمّداً. يُطفأ التفكير لطلبات المكتب
     * (إجاباتنا استرجاعية لا برهانية)، ومن يرفض الحقل من النماذج يُعاد
     * نداؤه بدونه في نفس الطلب — فلا نموذج ينكسر ولا سرعة تضيع.
     */
    protected bool $thinkingFieldRejected = false;

    protected function callModel(string $model, array $payload): string
    {
        try {
            $config = [
                'temperature' => 0.4,
                'maxOutputTokens' => 8192,
            ];
            if (!$this->thinkingFieldRejected) {
                $config['thinkingConfig'] = ['thinkingBudget' => 0];
            }

            $response = $this->post($model, array_merge($payload, ['generationConfig' => $config]));

            // نموذج لا يعرف حقل التفكير يردّ 400 يسمّيه — يُحذف ويُعاد
            if ($response->status() === 400
                && !$this->thinkingFieldRejected
                && stripos((string) $response->body(), 'thinking') !== false) {
                $this->thinkingFieldRejected = true;
                unset($config['thinkingConfig']);
                $response = $this->post($model, array_merge($payload, ['generationConfig' => $config]));
            }

            $this->lastStatus = $response->status();

            if (!$response->successful()) {
                $status = $response->status();
                Log::error('Gemini API error (' . $model . '): ' . $status . ' - ' . $response->body());

                if ($status === 404) {
                    $this->suggestedByProvider = $this->suggestedModel((string) $response->body());
                }
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

            $this->workingModel = $model;

            // شفاءٌ ذاتي: لو ردّ نموذجٌ غير المضبوط، ثُبّت. وإلا بقي
            // المكتب يبدأ من الميّت في كل طلب — محاولةٌ فاشلة وسطرُ
            // خطأ في السجل، إلى الأبد.
            if ($model !== AiSettings::model()) {
                AiSettings::rememberWorkingModel($model);
            }

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

    protected function post(string $model, array $body): \Illuminate\Http\Client\Response
    {
        return Http::timeout(120)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-goog-api-key' => $this->apiKey,
            ])
            ->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent', $body);
    }
}
