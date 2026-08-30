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
}
