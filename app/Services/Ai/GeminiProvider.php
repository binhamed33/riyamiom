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

    /** ردٌّ ناجحٌ بلا نصّ — حالتُه ٢٠٠ وهو مع ذلك يستحقّ إعادة. */
    protected bool $lastWasEmpty = false;

    /** ثوانٍ يطلب المزوّد الانتظارَها قبل الإعادة (Retry-After). */
    protected ?int $retryAfter = null;

    /**
     * ٤٢٩ نوعان لا يستويان: حدُّ دقيقةٍ يزول بانتظار ثوانٍ، وحصّةُ
     * يومٍ نفدت لا يردُّها إلا منتصف الليل. الإصرار على النموذج نفسه
     * في الثانية كان يحرق ميزانيّة الطلب كلّها قبل بلوغ النموذج
     * الاحتياطي — وحصّتُه مستقلّةٌ وربما ممتلئة.
     */
    protected bool $dailyQuotaHit = false;

    /** سبب الردّ الفارغ (SAFETY/MAX_TOKENS/…) — بدونه لا يُشخَّص العطل. */
    protected ?string $lastEmptyReason = null;

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

    /**
     * هل يقول المزوّد صراحةً إنّ المفتاح باطل؟
     *
     * التمييز مهمّ: ازدحامٌ لحظيّ يزول بإعادةٍ بعد ثوانٍ، ومفتاحٌ باطل
     * لا تُصلحه ألفُ إعادة. وخلطُهما يدفع المدير إلى تبديل مفتاحٍ سليم.
     */
    protected function looksLikeBadKey(string $body): bool
    {
        foreach (['API_KEY_INVALID', 'API key not valid', 'PERMISSION_DENIED', 'API_KEY_SERVICE_BLOCKED'] as $needle) {
            if (stripos($body, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /** ما يطلبه المزوّد من انتظارٍ قبل الإعادة — أدقُّ من تخميننا. */
    protected function retryAfterSeconds(\Illuminate\Http\Client\Response $response): ?int
    {
        $header = $response->header('Retry-After');
        if ($header !== '' && is_numeric($header)) {
            return max(0, (int) $header);
        }

        if (preg_match('#"retryDelay"\s*:\s*"(\d+)s"#', (string) $response->body(), $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * فاصلُ الإعادة: يتضاعف مع كل محاولة، وفيه رجفةٌ عشوائيّة.
     *
     * الفاصل الثابت (١٫٥ ثانية مرّتين) لا يكفي حدَّ معدّلٍ يُقاس
     * بالدقيقة، والرجفةُ تمنع أن ترتدّ كلُّ مكاتب الخادم في اللحظة
     * نفسها فتُعيد إنتاج الازدحام الذي تهرب منه.
     */
    protected function backoffMs(int $attempt): int
    {
        $base = (int) config('ai.retry.base_delay_ms', 1500);
        if ($base <= 0) {
            return 0;
        }

        if ($this->retryAfter !== null) {
            $wait = min($this->retryAfter * 1000, (int) config('ai.retry.max_delay_ms', 8000));
            $this->retryAfter = null;

            return $wait;
        }

        $delay = (int) min($base * (2 ** max(0, $attempt - 1)), (int) config('ai.retry.max_delay_ms', 8000));

        return $delay + random_int(0, (int) min(400, $delay));
    }

    protected function generate(array $payload): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $modelChain = array_values(array_unique(array_filter([
            $this->model,
            ...config('ai.providers.gemini.fallback_models', []),
        ])));

        // ═══ سلسلةُ المفاتيح فوق سلسلة النماذج ═══
        //
        // النماذجُ كلُّها على مفتاحٍ نفدت حصّتُه تفشل معاً: الحصّةُ
        // للمفتاح لا للنموذج وحده. فبعد استنفاد النماذج على المفتاح
        // الأوّل يُعاد المشوارُ على الذي يليه — مفتاحُ المكتب أوّلاً
        // احتراماً لاختياره، ثم المركزيُّ المدفوع من .env.
        $keys = \App\Support\AiSettings::keyChain();

        // مفتاحٌ مُرّر يدوياً للمنشئ (اختبارُ اتصالٍ بمفتاحٍ بعينه)
        // يتقدّم؛ وفي التشغيل العاديّ رأسُ السلسلة هو حسابُ المالك
        if ($this->apiKey && !in_array($this->apiKey, $keys, true)) {
            array_unshift($keys, $this->apiKey);
        }

        $keyQueue = $keys === [] ? [$this->apiKey] : $keys;
        $this->apiKey = array_shift($keyQueue);

        $models = $modelChain;
        $tried = [];
        $startedAt = microtime(true);
        $elapsed = fn (): int => (int) ((microtime(true) - $startedAt) * 1000);

        $lastTransientError = null;
        $maxAttemptsPerModel = max(1, (int) config('ai.retry.attempts_per_model', 3));

        // ميزانيّةُ وقتٍ للطلب كلّه: بلا سقفٍ قد تلتهم المحاولاتُ
        // المتتالية أكثر ممّا يصبر عليه الخادم أو المتصفّح، فيسقط
        // الاتّصال ويرى المحامي عطلاً بلا رسالة — أسوأ من رسالةٍ صريحة.
        $budgetMs = max(5000, (int) config('ai.retry.budget_ms', 100000));

        // القائمة تُستهلك لا تُدار بـforeach: النموذج الذي يسمّيه المزوّد
        // بديلاً يُضاف إلى آخرها أثناء التنفيذ، فيُجرَّب في نفس الطلب
        // بدل أن ينتظر تعديلاً منّا.
        nextKey:

        while (($model = array_shift($models)) !== null) {
            if (in_array($model, $tried, true)) {
                continue;
            }
            $tried[] = $model;

            for ($attempt = 1; $attempt <= $maxAttemptsPerModel; $attempt++) {
                if ($elapsed() >= $budgetMs) {
                    break 2;
                }
                try {
                    $text = $this->callModel($model, $payload);
                    \App\Support\AiHealth::record('ok', 'gemini', $model, $elapsed(), null);

                    return $text;
                } catch (\RuntimeException $e) {
                    // الردّ الفارغ حالتُه ٢٠٠ ومع ذلك يُعاد عليه
                    $retryable = $this->lastWasEmpty
                        || in_array($this->lastStatus, [404, 429, 500, 502, 503], true);
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

                    // حصّة اليوم نفدت لهذا النموذج → الانتظار عبث حتى
                    // منتصف الليل، والنموذج التالي حصّتُه مستقلّة. القفز
                    // إليه فوراً بدل ثلاث محاولاتٍ ميّتة كان الفرق بين
                    // جوابٍ من الاحتياطي و«سؤالك محفوظ» على كل سؤال.
                    if ($this->lastStatus === 429 && $this->dailyQuotaHit) {
                        $this->retryAfter = null;

                        break;
                    }

                    // ازدحامٌ مؤقّت أو ردٌّ فارغ → أعد بعد فاصلٍ متضاعف،
                    // ما دام في الميزانيّة متّسعٌ لمحاولةٍ أخرى.
                    if ($attempt < $maxAttemptsPerModel) {
                        $wait = $this->backoffMs($attempt);
                        if ($elapsed() + $wait >= $budgetMs) {
                            // الانتظار الذي يلتهم بقيّة الميزانيّة لا
                            // يُدفَع. لكنّ `break 2` هنا كان يتخلّى أيضاً
                            // عن النماذج التي لم تُجرَّب — ومحاولةٌ فوريّة
                            // على نموذجٍ جديد أرخص من الاستسلام.
                            if ($models === []) {
                                break 2;
                            }

                            break;
                        }
                        if ($wait > 0) {
                            usleep($wait * 1000);
                        }
                    }
                }
            }
        }

        // نفدت النماذجُ على هذا المفتاح وثمّة مفتاحٌ بعده والميزانيّةُ
        // تتّسع: يُعاد المشوارُ به. الحصصُ مستقلّةٌ بين المفاتيح، فما
        // فشل بالأوّل لسقفه قد ينجح بالثاني من أوّل محاولة.
        if ($keyQueue !== [] && $elapsed() < $budgetMs) {
            $this->apiKey = array_shift($keyQueue);
            $models = $modelChain;
            $tried = [];
            $lastTransientError = null;

            goto nextKey;
        }

        if ($lastTransientError === null || $this->lastError === null || $this->lastStatus === 429 || $this->lastWasEmpty) {
            $this->lastError = 'خدمة الذكاء الاصطناعي مزدحمة حاليًا. حُفظ سؤالك وتُعاد المحاولة تلقائياً.';
        }
        // «فارغ» بلا سببٍ لا يُشخَّص — أما empty_SAFETY فيقول فوراً:
        // المشكلة مرشِّح حجب، لا ازدحام ولا مفتاح.
        $errorType = $this->lastWasEmpty
            ? 'empty_' . ($this->lastEmptyReason ?: 'x')
            : 'exhausted_' . ($this->lastStatus ?: 'x');
        \App\Support\AiHealth::record('error', 'gemini', $tried !== [] ? end($tried) : $this->model, $elapsed(), $errorType);

        throw $lastTransientError
            ?? new \RuntimeException('Gemini API error — tried models: ' . implode(', ', $tried));
    }

    /**
     * نماذج 2.5/3.x «تفكّر» قبل أن تجيب افتراضياً — ثوانٍ صامتة تُحرق
     * قبل أول حرف، والمستخدم يراها تجمّداً. سُلَّم إطفاء ثلاثي:
     * thinkingBudget=0 (جيل 2.5) ← thinkingLevel=low (جيل 3.x الذي
     * يرفض budget فيبقى تفكيره شغالاً بأدنى درجة) ← بلا حقل إطلاقاً.
     * كل رفض بـ400 يسمّي «thinking» ينزل درجةً في نفس الطلب.
     */
    protected int $thinkingStep = 0;

    protected const THINKING_LADDER = [
        ['thinkingBudget' => 0],
        ['thinkingLevel' => 'low'],
        null,
    ];

    protected function callModel(string $model, array $payload): string
    {
        // أعلام المحاولة السابقة لا تصف هذه المحاولة — بقاؤها يقفز
        // بالطلب فوق نموذجٍ سليم أو يشخّص عطلاً بغير سببه.
        $this->dailyQuotaHit = false;
        $this->lastEmptyReason = null;

        try {
            $config = [
                'temperature' => 0.4,
                'maxOutputTokens' => 8192,
            ];

            $response = null;
            while (true) {
                $thinking = self::THINKING_LADDER[$this->thinkingStep];
                if ($thinking !== null) {
                    $config['thinkingConfig'] = $thinking;
                } else {
                    unset($config['thinkingConfig']);
                }

                $response = $this->post($model, array_merge($payload, ['generationConfig' => $config]));

                if ($response->status() === 400
                    && $this->thinkingStep < count(self::THINKING_LADDER) - 1
                    && stripos((string) $response->body(), 'thinking') !== false) {
                    $this->thinkingStep++;

                    continue;
                }

                break;
            }

            $this->lastStatus = $response->status();

            if (!$response->successful()) {
                $status = $response->status();
                Log::error('Gemini API error (' . $model . '): ' . $status . ' - ' . $response->body());

                if ($status === 404) {
                    $this->suggestedByProvider = $this->suggestedModel((string) $response->body());
                }
                if ($status === 429) {
                    $this->retryAfter = $this->retryAfterSeconds($response);

                    // حصّة اليوم تُعرَف من اسمها في الردّ (…PerDay…) أو
                    // من مهلةٍ يطلبها المزوّد أطول من سقف فواصلنا.
                    $this->dailyQuotaHit = stripos((string) $response->body(), 'PerDay') !== false
                        || ($this->retryAfter !== null && $this->retryAfter * 1000 > (int) config('ai.retry.max_delay_ms', 8000));

                    // ٤٢٩ حدُّ معدّلٍ في الغالب — يزول بعد دقيقة. وكانت
                    // الرسالة تأمر المدير بتغيير مفتاحه، فيُبطل مفتاحاً
                    // سليماً على عطلٍ يزول وحده. لا يُتّهم المفتاح إلا
                    // إذا قال المزوّد ذلك صراحةً.
                    throw new \RuntimeException($this->looksLikeBadKey((string) $response->body())
                        ? 'المفتاح غير صالح أو نفدت حصّته كلياً. حدّثه من الإعدادات ← الذكاء الاصطناعي.'
                        : 'ازدحامٌ لحظيّ لدى المزوّد. تُعاد المحاولة تلقائياً.');
                }
                if ($status === 503) {
                    throw new \RuntimeException('النموذج ' . $model . ' مزدحم حاليًا. سيتم تجربة نموذج احتياطي تلقائيًا، وإن استمرت المشكلة حاول لاحقًا.');
                }
                throw new \RuntimeException($this->humanError($status));
            }

            $this->lastWasEmpty = false;
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
                // ردٌّ ناجحٌ بلا نصّ: مرشِّح أمانٍ حجب، أو نفدت الحصّة
                // الرمزيّة في «التفكير» قبل أوّل حرف. حالتُه ٢٠٠ فلم
                // يكن يُعاد عليه ولا يُنتقل منه إلى نموذجٍ احتياطيّ —
                // فيرى المحامي عطلاً على سؤالٍ كانت إعادتُه تكفيه.
                $this->lastWasEmpty = true;

                // السبب يذهب إلى سجلّ ai_requests — كان «لم تُرجع الخدمة
                // أي محتوى» بلا سبب، فلا يفرّق التشخيص بين حجبِ أمانٍ
                // يتكرّر على كلّ سؤال وبين نفاد رموزٍ يعالَج بالإعدادات.
                $reason = $data['promptFeedback']['blockReason']
                    ?? $data['candidates'][0]['finishReason']
                    ?? null;
                $this->lastEmptyReason = is_string($reason) ? $reason : null;

                throw new \RuntimeException('لم تُرجع الخدمة أي محتوى'
                    . ($this->lastEmptyReason ? ' (' . $this->lastEmptyReason . ')' : '') . '.');
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
        // مهلة الاتصال منفصلة عن مهلة القراءة: خادمٌ لا يُجيب المصافحة
        // أصلاً يُكتشف في ثوانٍ، ولا يُحجز المحامي ١٢٠ ثانية على TCP
        // معلّق. ومهلة القراءة من الإعدادات — فالطلب التفاعليّ يستعجل
        // والمهمّة الخلفيّة تصبر.
        return Http::connectTimeout(10)
            ->timeout((int) config('ai.http_timeout_s', 90))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-goog-api-key' => $this->apiKey,
            ])
            ->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent', $body);
    }
}
