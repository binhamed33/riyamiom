<?php

namespace Tests\Feature;

use App\Models\AssistantMessage;
use App\Models\User;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * «وش السبب؟» — أمر التشخيص يجيب من الخادم بدل التخمين.
 *
 * حين رُفض «اهلا» في منتصف الليل لم يكن في النظام ما يقول لماذا:
 * حصّةُ المفتاح؟ ازدحامُ المزوّد؟ نموذجٌ متقاعد؟ المحامي يرى رسالةً
 * مهذّبة، وصاحبُ النظام يخمّن.
 */
class AiDoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unconfigured_office_is_told_plainly(): void
    {
        $this->artisan('ai:doctor')
            ->expectsOutputToContain('غير مضبوط')
            ->assertFailed();
    }

    public function test_todays_errors_are_broken_down_by_type(): void
    {
        AiSettings::store('gemini', 'AIzaTestKey123', 'gemini-flash-latest');
        DB::table('ai_requests')->insert([
            ['provider' => 'gemini', 'status' => 'error', 'error_type' => 'http_429', 'created_at' => now()],
            ['provider' => 'gemini', 'status' => 'error', 'error_type' => 'http_429', 'created_at' => now()],
            ['provider' => 'gemini', 'status' => 'ok', 'error_type' => null, 'created_at' => now()],
        ]);

        $this->artisan('ai:doctor')
            ->expectsOutputToContain('http_429')
            ->assertSuccessful();
    }

    public function test_queued_questions_are_counted(): void
    {
        AiSettings::store('gemini', 'AIzaTestKey123', 'gemini-flash-latest');
        config()->set('queue.default', 'database');
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $q = AssistantMessage::write($user->id, 'user', 'س');
        \App\Jobs\AnswerAssistantQuestion::dispatch($user->id, $q->id);

        $this->artisan('ai:doctor')
            ->expectsOutputToContain('أسئلة تنتظر جواباً في الطابور: 1')
            ->assertSuccessful();
    }

    /** الفحص الحيّ يكشف ردّ المزوّد الفعليّ — هنا: حصّةٌ منتهية. */
    public function test_the_live_probe_surfaces_the_provider_answer(): void
    {
        AiSettings::store('gemini', 'AIzaTestKey123', 'gemini-flash-latest');
        config()->set('ai.retry.base_delay_ms', 0);
        Http::fake(['*' => Http::response(['error' => ['code' => 429, 'message' => 'quota']], 429)]);

        $this->artisan('ai:doctor --probe')
            ->expectsOutputToContain('ردٌّ فارغ')
            ->assertSuccessful();
    }
}
