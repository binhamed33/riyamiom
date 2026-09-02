<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AiHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * صحة المساعد (§88-91): سجلٌّ بلا نصوص، وعدّادات، وحدّ إغراق.
 */
class AiHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_writes_numbers_not_texts(): void
    {
        AiHealth::record('ok', 'gemini', 'gemini-3.6-flash', 850, null);
        AiHealth::record('error', 'gemini', 'gemini-3.6-flash', 1200, 'http_429');

        $this->assertSame(2, DB::table('ai_requests')->count());

        $columns = array_keys((array) DB::table('ai_requests')->first());
        $this->assertNotContains('prompt', $columns, 'نصّ السؤال لا يُسجَّل');
        $this->assertNotContains('response', $columns);

        $snap = AiHealth::snapshot();
        $this->assertSame(2, $snap['counts']['today']);
        $this->assertSame(1, $snap['counts']['today_errors']);
        $this->assertNotNull($snap['last_success_at']);
        $this->assertSame('http_429', $snap['last_error']['type']);
    }

    public function test_recording_failure_never_breaks_the_caller(): void
    {
        DB::statement('DROP TABLE ai_requests');

        AiHealth::record('ok', 'gemini', 'x', 1, null);

        $this->assertTrue(true, 'لم يُرمَ استثناء رغم غياب الجدول');
    }

    /**
     * شريطُ صحّة المساعد للمطوّر وحدَه.
     *
     * كان يظهر لمدير المكتب، وأرقامُه لا تعنيه: المساعدُ يعمل بمفتاح
     * مُداوَلة المركزيّ، فـ«آخرُ خطأ http_400» سطرٌ يُقلقه ولا يملك
     * له فعلاً. ومضى مع بطاقة الذكاء الاصطناعيّ كلِّها إلى المطوّر.
     */
    public function test_the_health_strip_shows_for_the_developer_alone(): void
    {
        $dev = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        $this->actingAs($dev)->get('/settings')->assertOk()->assertSee('آخر نجاح', false);

        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $response = $this->actingAs($user)->get('/settings');

            $this->assertTrue(
                $response->getStatusCode() !== 200
                || !str_contains($response->getContent(), 'آخر نجاح'),
                "شريط الصحة يجب ألا يظهر لـ{$role}"
            );
        }
    }

    public function test_the_assistant_is_rate_limited_per_user(): void
    {
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $status = null;
        for ($i = 0; $i < 21; $i++) {
            $status = $this->actingAs($lawyer)
                ->postJson('/ai-assistant', ['message' => 'سؤال ' . $i])
                ->getStatusCode();
        }

        $this->assertSame(429, $status, 'الطلب الحادي والعشرون في الدقيقة يجب أن يُرفض');
    }
}
