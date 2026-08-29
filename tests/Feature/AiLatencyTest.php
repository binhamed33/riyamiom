<?php

namespace Tests\Feature;

use App\Services\Ai\GeminiProvider;
use App\Support\AiHealth;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * البطء المشكوّ منه: نماذج 2.5/3.x «تفكّر» افتراضياً قبل أول حرف —
 * ثوانٍ صامتة يراها المستخدم تجمّداً. التفكير يُطفأ، ومن يرفض الحقل
 * من النماذج يُعاد نداؤه بدونه، وزمن الرد يظهر رقماً للإدارة.
 */
class AiLatencyTest extends TestCase
{
    use RefreshDatabase;

    private const OK = ['candidates' => [['content' => ['parts' => [['text' => 'جواب']]]]]];

    protected function setUp(): void
    {
        parent::setUp();
        AiSettings::store('gemini', 'AIzaTestKey123', 'gemini-flash-latest');
    }

    public function test_thinking_is_disabled_on_every_request(): void
    {
        Http::fake(['*' => Http::response(self::OK, 200)]);

        (new GeminiProvider())->chat([['role' => 'user', 'content' => 'اهلا']], 'نظام');

        Http::assertSent(function ($request) {
            $config = $request->data()['generationConfig'] ?? [];

            return ($config['thinkingConfig']['thinkingBudget'] ?? null) === 0;
        });
    }

    public function test_the_thinking_ladder_descends_on_each_rejection(): void
    {
        // جيل 3.x يرفض thinkingBudget ثم قد يرفض thinkingLevel — كل رفض
        // ينزل درجة في نفس الطلب حتى بلا حقل، والسؤال لا يضيع
        $sent = [];
        Http::fake(function ($request) use (&$sent) {
            $sent[] = $request->data()['generationConfig']['thinkingConfig'] ?? null;

            return count($sent) <= 2
                ? Http::response(['error' => [
                    'code' => 400,
                    'message' => 'Unknown name "thinkingConfig": thinking is not supported.',
                    'status' => 'INVALID_ARGUMENT',
                ]], 400)
                : Http::response(self::OK, 200);
        });

        $answer = (new GeminiProvider())->chat([['role' => 'user', 'content' => 'اهلا']], 'نظام');

        $this->assertSame('جواب', $answer);
        $this->assertSame([
            ['thinkingBudget' => 0],
            ['thinkingLevel' => 'low'],
            null,
        ], $sent, 'الدرجات الثلاث بالترتيب');
    }

    public function test_health_snapshot_exposes_response_times(): void
    {
        DB::table('ai_requests')->insert([
            ['provider' => 'gemini', 'model' => 'm', 'status' => 'ok', 'duration_ms' => 4000, 'created_at' => now()],
            ['provider' => 'gemini', 'model' => 'm', 'status' => 'ok', 'duration_ms' => 6000, 'created_at' => now()],
            ['provider' => 'gemini', 'model' => 'm', 'status' => 'error', 'duration_ms' => 90000, 'created_at' => now()],
        ]);

        $snapshot = AiHealth::snapshot();

        $this->assertSame(5000, $snapshot['avg_ms'], 'المتوسط للناجحة وحدها — الفاشلة ليست زمن ردّ');
        $this->assertSame(6000, $snapshot['last_ms']);
    }

    public function test_snapshot_survives_an_empty_log(): void
    {
        $snapshot = AiHealth::snapshot();

        $this->assertNull($snapshot['avg_ms']);
        $this->assertNull($snapshot['last_ms']);
    }
}
