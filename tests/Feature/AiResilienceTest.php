<?php

namespace Tests\Feature;

use App\Services\Ai\GeminiProvider;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * المساعد لا يستسلم من أوّل تعثّر.
 *
 * ═══ العطل الذي وُضع له ═══
 *
 * ردٌّ ناجحٌ بلا نصّ — وهو يقع كثيراً: مرشِّح أمانٍ حجب، أو انتهت
 * الحصّة الرمزيّة في «التفكير» قبل أوّل حرف — كان يُرمى فوراً بلا
 * إعادةٍ ولا انتقالٍ إلى نموذجٍ احتياطيّ، لأنّ حالة الردّ ٢٠٠ وقائمةُ
 * ما يُعاد عليه لا تعرف إلا ٤٠٤ و٤٢٩ و٥٠٠ و٥٠٢ و٥٠٣.
 *
 * فيرى المحامي «تعذر الحصول على رد من الذكاء الاصطناعي» على سؤالٍ
 * كانت إعادةُ إرساله وحدها تكفي للإجابة عليه.
 */
class AiResilienceTest extends TestCase
{
    use RefreshDatabase;

    private const MODEL = 'gemini-flash-latest';

    protected function setUp(): void
    {
        parent::setUp();
        AiSettings::store('gemini', 'AIzaTestKey123', self::MODEL);
        config()->set('ai.retry.base_delay_ms', 0);   // لا انتظارَ في الاختبار
    }

    private function text(string $body): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $body]]]]]];
    }

    /** ردٌّ بلا نصّ ثمّ ردٌّ بنصّ — والمحامي يرى الجواب لا الخطأ. */
    public function test_an_empty_reply_is_retried_instead_of_surfacing_as_failure(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return $calls === 1
                ? Http::response(['candidates' => [['content' => ['parts' => []]]]], 200)
                : Http::response($this->text('حقوق العامل عند إنهاء الخدمة…'), 200);
        });

        $reply = (new GeminiProvider())->chat(
            [['role' => 'user', 'content' => 'ما حقوق العامل؟']],
            'أنت مساعد قانوني عُماني.'
        );

        $this->assertSame('حقوق العامل عند إنهاء الخدمة…', $reply, 'استسلم من أوّل ردٍّ فارغ');
        $this->assertSame(2, $calls, 'لم يُعِد المحاولة');
    }

    /** وازدحامٌ لحظيّ (٤٢٩) يُعاد عليه فيَنجح. */
    public function test_a_rate_limit_is_retried_and_succeeds(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return $calls === 1
                ? Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded']], 429)
                : Http::response($this->text('الجواب.'), 200);
        });

        $reply = (new GeminiProvider())->chat([['role' => 'user', 'content' => 'س']], 'ن');

        $this->assertSame('الجواب.', $reply);
    }

    /**
     * وازدحامٌ لحظيّ لا يُقال لصاحب المكتب إنّ مفتاحه غير صالح.
     *
     * ٤٢٩ حدُّ معدّلٍ في الغالب، يزول بعد دقيقة. وكانت الرسالة تأمر
     * المدير بتغيير المفتاح — تشخيصٌ خاطئ يدفعه إلى إبطال مفتاحٍ سليم.
     */
    public function test_a_transient_rate_limit_does_not_blame_the_key(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 429, 'message' => 'Resource exhausted']], 429)]);

        $provider = new GeminiProvider();
        try {
            $provider->chat([['role' => 'user', 'content' => 'س']], 'ن');
        } catch (\Throwable) {
            // متوقَّع: نفدت المحاولات
        }

        $this->assertStringNotContainsString('غير صالح', (string) $provider->getLastError());
        $this->assertStringNotContainsString('حدّثه', (string) $provider->getLastError());
    }

    /** ومفتاحٌ غير صالحٍ فعلاً يُقال صراحةً — فذاك لا تُصلحه إعادة. */
    public function test_an_actually_invalid_key_is_named_as_such(): void
    {
        Http::fake(['*' => Http::response([
            'error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'API key not valid. Please pass a valid API key.'],
        ], 400)]);

        $provider = new GeminiProvider();
        try {
            $provider->chat([['role' => 'user', 'content' => 'س']], 'ن');
        } catch (\Throwable) {
        }

        $this->assertStringContainsString('المفتاح', (string) $provider->getLastError());
    }

    /** والردّ الفارغ المتكرّر ينتقل إلى النموذج الاحتياطي. */
    public function test_a_persistently_empty_model_falls_through_to_the_fallback(): void
    {
        config()->set('ai.providers.gemini.fallback_models', ['gemini-3.6-flash']);

        $seen = [];
        Http::fake(function ($request) use (&$seen) {
            preg_match('#/models/([^:]+):#', $request->url(), $m);
            $seen[] = $m[1] ?? '?';

            return str_contains($request->url(), 'gemini-3.6-flash')
                ? Http::response($this->text('جواب الاحتياطي.'), 200)
                : Http::response(['candidates' => [['content' => ['parts' => []]]]], 200);
        });

        $reply = (new GeminiProvider())->chat([['role' => 'user', 'content' => 'س']], 'ن');

        $this->assertSame('جواب الاحتياطي.', $reply);
        $this->assertContains('gemini-3.6-flash', $seen, 'لم ينتقل إلى النموذج الاحتياطي');
    }

    /**
     * حصّةُ اليوم إذا نفدت فالانتظار عبثٌ حتى منتصف الليل — يُقفَز
     * فوراً إلى النموذج الاحتياطي ذي الحصّة المستقلّة.
     *
     * قبل الإصلاح: ثلاث محاولاتٍ ميّتة على النموذج الميّت تحرق
     * الميزانيّة التفاعليّة (~٢٠ ثانية) قبل بلوغ الاحتياطي، فيرى
     * المحامي «سؤالك محفوظ» على كلّ سؤالٍ بقيّةَ اليوم.
     */
    public function test_a_daily_quota_429_jumps_to_the_fallback_without_burning_attempts(): void
    {
        config()->set('ai.providers.gemini.fallback_models', ['gemini-3.6-flash']);

        $perModel = [];
        Http::fake(function ($request) use (&$perModel) {
            preg_match('#/models/([^:]+):#', $request->url(), $m);
            $model = $m[1] ?? '?';
            $perModel[$model] = ($perModel[$model] ?? 0) + 1;

            return $model === 'gemini-3.6-flash'
                ? Http::response($this->text('جواب الاحتياطي.'), 200)
                : Http::response(['error' => [
                    'code' => 429, 'status' => 'RESOURCE_EXHAUSTED',
                    'message' => 'You exceeded your current quota.',
                    'details' => [[
                        '@type' => 'type.googleapis.com/google.rpc.RetryInfo',
                        'retryDelay' => '45s',
                    ]],
                ]], 429);
        });

        $reply = (new GeminiProvider())->chat([['role' => 'user', 'content' => 'س']], 'ن');

        $this->assertSame('جواب الاحتياطي.', $reply);
        $this->assertSame(1, $perModel[self::MODEL] ?? 0, 'أصرّ على نموذجٍ نفدت حصّته اليوميّة');
    }

    /** أمّا حدُّ الدقيقة (مهلة ثوانٍ) فيُعاد على النموذج نفسه — لا قفز. */
    public function test_a_minute_limit_429_still_retries_the_same_model(): void
    {
        config()->set('ai.providers.gemini.fallback_models', ['gemini-3.6-flash']);

        $perModel = [];
        Http::fake(function ($request) use (&$perModel) {
            preg_match('#/models/([^:]+):#', $request->url(), $m);
            $model = $m[1] ?? '?';
            $n = $perModel[$model] = ($perModel[$model] ?? 0) + 1;

            if ($model === self::MODEL && $n >= 2) {
                return Http::response($this->text('جواب النموذج الأصلي.'), 200);
            }

            return Http::response(['error' => [
                'code' => 429, 'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Rate limit, slow down.',
                'details' => [[
                    '@type' => 'type.googleapis.com/google.rpc.RetryInfo',
                    'retryDelay' => '3s',
                ]],
            ]], 429);
        });

        $reply = (new GeminiProvider())->chat([['role' => 'user', 'content' => 'س']], 'ن');

        $this->assertSame('جواب النموذج الأصلي.', $reply, 'قفز عن نموذجٍ كان انتظارُ ثوانٍ يكفيه');
        $this->assertArrayNotHasKey('gemini-3.6-flash', $perModel, 'استُدعي الاحتياطي بلا داعٍ');
    }

    /** والردّ الفارغ يسجّل سببه — فيُعرف حجبُ الأمان من نفاد الرموز. */
    public function test_an_empty_reply_reason_is_recorded_for_diagnosis(): void
    {
        config()->set('ai.providers.gemini.fallback_models', []);

        Http::fake(['*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']], 200)]);

        (new GeminiProvider())->chat([['role' => 'user', 'content' => 'س']], 'ن');

        $this->assertDatabaseHas('ai_requests', ['status' => 'error', 'error_type' => 'empty_SAFETY']);
    }

    /**
     * إعادةُ المتصفّح للسؤال لا تُضاعفه في المحادثة.
     *
     * السؤال يُحفظ قبل الطلب حتى لا يضيع إن سقط الاتّصال. فإذا أعاد
     * المتصفّح إرساله تلقائياً بعد تعثّرٍ عابر كُتب مرّةً ثانية —
     * فيرى المحامي سؤاله مرّتين، ويُرسَل إلى المزوّد مكرّراً في السياق.
     */
    public function test_a_client_retry_does_not_duplicate_the_question(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        Http::fake(['*' => Http::response($this->text('الجواب.'), 200)]);

        $first = $this->actingAs($user)
            ->postJson(route('assistant.chat'), ['message' => 'ما حقوق العامل؟']);
        $questionId = $first->json('question_id');

        $this->actingAs($user)->postJson(route('assistant.chat'), [
            'message' => 'ما حقوق العامل؟',
            'retry_of' => $questionId,
        ])->assertOk();

        $asked = \App\Models\AssistantMessage::where('user_id', $user->id)
            ->where('role', 'user')->count();

        $this->assertSame(1, $asked, 'كُتب السؤال مرّتين عند إعادة المحاولة');
    }

    /** ومعرّفُ سؤالٍ لغير صاحبه لا يُقبل. */
    public function test_a_retry_id_from_another_user_is_ignored(): void
    {
        $mine = \App\Models\User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $other = \App\Models\User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $theirs = \App\Models\AssistantMessage::write($other->id, 'user', 'سؤال غيري');

        Http::fake(['*' => Http::response($this->text('الجواب.'), 200)]);

        $this->actingAs($mine)->postJson(route('assistant.chat'), [
            'message' => 'سؤالي أنا',
            'retry_of' => $theirs->id,
        ])->assertOk();

        $this->assertSame(1, \App\Models\AssistantMessage::where('user_id', $mine->id)->where('role', 'user')->count());
        $this->assertSame('سؤال غيري', $theirs->fresh()->content, 'مُسّت رسالة مستخدمٍ آخر');
    }

    // ══════════ سلسلة المفاتيح ══════════

    /**
     * ═══ «كأنهم يكتبون لحسابي وهو يردّ عليهم» ═══
     *
     * حسابُ صاحب المنظومة (المركزيُّ في .env) هو رأسُ السلسلة: أوّلُ
     * نداءٍ يخرج به، ولا يُمسّ مفتاحُ المكتب ما دام المركزيُّ يجيب.
     */
    public function test_the_owner_account_answers_first(): void
    {
        config()->set('services.gemini.api_key', 'AIzaCentralPaid456');

        $keysSeen = [];
        Http::fake(function ($request) use (&$keysSeen) {
            $keysSeen[] = $request->header('X-goog-api-key')[0] ?? '';

            return Http::response($this->text('الجواب من حساب المالك'), 200);
        });

        $reply = (new GeminiProvider())->chat(
            [['role' => 'user', 'content' => 'سؤال']],
            'أنت مساعد قانوني.'
        );

        $this->assertSame('الجواب من حساب المالك', $reply);
        $this->assertSame('AIzaCentralPaid456', $keysSeen[0], 'أوّلُ نداءٍ ليس بحساب المالك');
    }

    /**
     * ═══ «ما أريده يتوقف أبداً» ═══
     *
     * نفدت حصّةُ حساب المالك — 429 على كلّ نموذج، والحصّةُ للمفتاح لا
     * للنموذج. فيُعاد المشوارُ بمفتاح المكتب الاحتياطيّ في الطلب
     * نفسِه: السائلُ يرى الجواب ولا يعلم أنّ مفتاحاً نفد ومفتاحاً أنقذ.
     */
    public function test_a_drained_owner_key_falls_back_to_the_office_key(): void
    {
        config()->set('services.gemini.api_key', 'AIzaCentralPaid456');
        config()->set('ai.retry.attempts_per_model', 1);

        $keysSeen = [];
        Http::fake(function ($request) use (&$keysSeen) {
            $key = $request->header('X-goog-api-key')[0] ?? '';
            $keysSeen[] = $key;

            return $key === 'AIzaTestKey123'
                ? Http::response($this->text('الجواب من مفتاح المكتب'), 200)
                : Http::response(['error' => ['code' => 429, 'message' => 'quota exceeded']], 429);
        });

        $reply = (new GeminiProvider())->chat(
            [['role' => 'user', 'content' => 'سؤال']],
            'أنت مساعد قانوني.'
        );

        $this->assertSame('الجواب من مفتاح المكتب', $reply, 'لم يجرَّب الاحتياطيُّ بعد نفاد المالك');
        $this->assertSame('AIzaCentralPaid456', $keysSeen[0], 'لم يبدأ بحساب المالك');
        $this->assertContains('AIzaTestKey123', $keysSeen);
    }

    /** ولا مفتاحَ مركزيّ: يفشل بهدوء الرسالة المعهودة لا باستثناءٍ غريب. */
    public function test_without_a_central_key_the_failure_stays_graceful(): void
    {
        config()->set('services.gemini.api_key', null);
        config()->set('ai.retry.attempts_per_model', 1);

        Http::fake(['*' => Http::response(['error' => ['code' => 429, 'message' => 'quota']], 429)]);

        $provider = new GeminiProvider();
        $reply = $provider->chat([['role' => 'user', 'content' => 'سؤال']], 'نظام');

        $this->assertNull($reply);

        // رسالةٌ عربيةٌ هادئة تعِد بالمحاولة — لا استثناءٌ ولا نصٌّ تقني
        $this->assertMatchesRegularExpression(
            '/تُعاد المحاولة|حُفظ سؤالك/',
            (string) $provider->getLastError(),
        );
    }
}
