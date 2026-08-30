<?php

namespace Tests\Feature;

use App\Jobs\AnswerAssistantQuestion;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * سؤالٌ لا يُجاب الآن يُجاب لاحقاً — ولا يُترك المحامي أمام حائط.
 *
 * ═══ الحدّ الذي لا يتجاوزه كودٌ مهما احتاط ═══
 *
 * إعادةُ المحاولة تنفع على تعثّرٍ عابر. أمّا انقطاع خدمة المزوّد أو
 * نفاد الحصّة فلا تُصلحه إعادةٌ في ثوانٍ — ولا شيء يُنتج جواباً وقتها.
 * والذي يملكه النظام أن يحفظ السؤال ويعود إليه من نفسه حين تعود
 * الخدمة، بدل أن يقول «حاول لاحقاً» ويترك التذكّر على المحامي.
 */
class AssistantNeverDeadEndsTest extends TestCase
{
    use RefreshDatabase;

    private function lawyer(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        AiSettings::store('gemini', 'AIzaTestKey123', 'gemini-flash-latest');
        config()->set('ai.retry.base_delay_ms', 0);
        // المكاتب على «database» كما في .env.example — وحزمة الاختبار
        // على «sync»، وهو ما يمنع التأجيل عمداً. فيُضبط كالإنتاج هنا،
        // ويُعاد إلى sync في الاختبار الذي يقصده.
        config()->set('queue.default', 'database');
    }

    private function down(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 503, 'message' => 'overloaded']], 503)]);
    }

    private function text(string $body): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $body]]]]]];
    }

    /** حين تسقط الخدمة، يُجدوَل السؤال ولا يُرمى. */
    public function test_an_unanswerable_question_is_queued_for_later(): void
    {
        Bus::fake();
        $this->down();
        $user = $this->lawyer();

        $res = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'ما حقوق العامل؟']);

        $res->assertOk()->assertJson(['queued' => true]);
        $this->assertNotNull($res->json('question_id'));
        Bus::assertDispatched(AnswerAssistantQuestion::class);
    }

    /** والمهمّة تُجيب حين تعود الخدمة. */
    public function test_the_queued_job_answers_when_the_service_returns(): void
    {
        $user = $this->lawyer();
        $question = AssistantMessage::write($user->id, 'user', 'ما حقوق العامل؟');

        Http::fake(['*' => Http::response($this->text('حقوق العامل هي…'), 200)]);
        (new AnswerAssistantQuestion($user->id, $question->id))->handle();

        $this->assertDatabaseHas('assistant_messages', [
            'user_id' => $user->id,
            'role' => 'assistant',
        ]);
        $this->assertStringContainsString(
            'حقوق العامل هي…',
            AssistantMessage::where('user_id', $user->id)->where('role', 'assistant')->value('content'),
        );
    }

    /** ولا تُجيب مرّتين لو كان المحامي قد أعاد السؤال بنفسه فنجح. */
    public function test_the_job_does_not_answer_a_question_already_answered(): void
    {
        $user = $this->lawyer();
        $question = AssistantMessage::write($user->id, 'user', 'س');
        AssistantMessage::write($user->id, 'assistant', 'جوابٌ سبق');

        Http::fake(['*' => Http::response($this->text('جوابٌ ثانٍ'), 200)]);
        (new AnswerAssistantQuestion($user->id, $question->id))->handle();

        $this->assertSame(1, AssistantMessage::where('user_id', $user->id)->where('role', 'assistant')->count());
    }

    /** ومحادثةٌ مُسحت لا تُبعث من قبرها. */
    public function test_a_cleared_conversation_is_not_resurrected(): void
    {
        $user = $this->lawyer();
        $question = AssistantMessage::write($user->id, 'user', 'س');
        $id = $question->id;
        AssistantMessage::where('user_id', $user->id)->delete();

        Http::fake(['*' => Http::response($this->text('جواب'), 200)]);
        (new AnswerAssistantQuestion($user->id, $id))->handle();

        $this->assertSame(0, AssistantMessage::where('user_id', $user->id)->count());
    }

    /**
     * وسياق المهمّة هو ما كان عند السؤال، لا ما استجدّ بعده.
     *
     * لو أُخذ السياق كاملاً لأجاب المساعد عن آخر سؤالٍ في المحادثة
     * لا عن السؤال المجدول — فيصل المحامي جوابٌ عن غير ما سأل.
     */
    public function test_the_job_answers_the_question_it_was_given_not_a_newer_one(): void
    {
        $user = $this->lawyer();
        $asked = AssistantMessage::write($user->id, 'user', 'السؤال الأول');
        AssistantMessage::write($user->id, 'user', 'سؤالٌ آخر مختلف تماماً');

        $sent = null;
        Http::fake(function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response($this->text('جواب'), 200);
        });

        (new AnswerAssistantQuestion($user->id, $asked->id))->handle();

        $flat = json_encode($sent, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('السؤال الأول', $flat);
        $this->assertStringNotContainsString('سؤالٌ آخر مختلف تماماً', $flat);
    }

    /**
     * ومكتبٌ طابورُه «sync» لا ينهار طلبه.
     *
     * التأجيل على اتّصالٍ متزامن يعني تشغيل المهمّة داخل الطلب نفسه:
     * تُنادي الخدمةَ الميّتة ثانيةً وترمي — فيتحوّل التأجيل اللطيف إلى
     * خطأ خادم، وهو نقيض الغرض. فلا يُؤجَّل حينئذٍ، بل يُقال الحقّ.
     */
    public function test_a_sync_queue_does_not_turn_a_deferral_into_a_crash(): void
    {
        config()->set('queue.default', 'sync');
        $this->down();
        $user = $this->lawyer();

        $res = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'ما حقوق العامل؟']);

        $res->assertStatus(503);
        $this->assertNotNull($res->json('error'));
        $this->assertNull($res->json('queued'));
        // والسؤال محفوظٌ على كل حال
        $this->assertSame(1, AssistantMessage::where('user_id', $user->id)->where('role', 'user')->count());
    }

    /** وإخفاقُ الطابور نفسه لا يُسقط الطلب. */
    public function test_a_failing_queue_does_not_break_the_request(): void
    {
        $this->down();
        $user = $this->lawyer();
        Bus::fake();
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('طابورٌ معطّل'));

        $res = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'س']);

        $this->assertContains($res->status(), [200, 503]);
        $this->assertNotNull($res->json('question_id'));
    }

    /** والدورة كاملةً: يُؤجَّل، ثمّ يُجاب، ثمّ يظهر في المحادثة. */
    public function test_the_whole_round_trip_ends_with_an_answer_in_the_history(): void
    {
        $user = $this->lawyer();
        Bus::fake();

        // حالةٌ واحدة تتغيّر: مُحاكيات Http تتراكم ولا يُلغي المتأخّرُ
        // منها المتقدّم، فتسجيلُ ردٍّ ثانٍ لا يُبطل الأوّل.
        $down = true;
        Http::fake(function () use (&$down) {
            return $down
                ? Http::response(['error' => ['code' => 503, 'message' => 'overloaded']], 503)
                : Http::response($this->text('حقوق العامل هي…'), 200);
        });

        $questionId = $this->actingAs($user)
            ->postJson(route('assistant.chat'), ['message' => 'ما حقوق العامل؟'])
            ->assertOk()->json('question_id');

        // عادت الخدمة، وعاملُ الطابور صرّف المهمّة
        $down = false;
        (new AnswerAssistantQuestion($user->id, $questionId))->handle();

        $messages = $this->actingAs($user)->getJson(route('assistant.history'))->json('messages');
        $answers = array_filter($messages, fn ($m) => $m['role'] === 'assistant');

        $this->assertCount(1, $answers);
        $this->assertStringContainsString('حقوق العامل هي…', reset($answers)['content']);
    }
}
