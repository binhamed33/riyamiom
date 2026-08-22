<?php

namespace Tests\Feature;

use App\Jobs\DeliverSuggestionJob;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * الاقتراح لا يضيع ولا يُفشل عمل الموظف.
 *
 * الحفظ عند الموظف أولاً، والتسليم إلى اللوحة بعده وخارج طلبه. فلو
 * تعذّر ديسكورد أو تعذّرت اللوحة أو تعطّل الطابور، اقتراحه محفوظ
 * ويُسلَّم لاحقاً.
 */
class SuggestionDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('suggestions:127.0.0.1');
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function payload(): array
    {
        return [
            'title' => 'فلترة القضايا حسب المحكمة',
            'content' => 'أقترح إضافة فلترة للقضايا حسب المحكمة، فأنا أبحث يدوياً كل يوم.',
        ];
    }

    public function test_a_suggestion_is_saved_and_queued_for_delivery(): void
    {
        Queue::fake();

        $this->actingAs($this->staff())
            ->post(route('suggestions.store'), $this->payload())
            ->assertSessionHas('success');

        $suggestion = Suggestion::firstOrFail();
        $this->assertSame('pending', $suggestion->delivery_state);

        Queue::assertPushed(DeliverSuggestionJob::class,
            fn ($job) => $job->suggestionId === $suggestion->id);
    }

    /** عطل ديسكورد شأن تشغيلي لا يجوز أن يمنع الموظف من الاقتراح */
    public function test_a_notifier_failure_never_blocks_the_employee(): void
    {
        Queue::fake();
        config(['discord.log_webhook' => 'https://discord.test/hook']);
        Http::fake(['*' => fn () => throw new \RuntimeException('discord down')]);

        $this->actingAs($this->staff())
            ->post(route('suggestions.store'), $this->payload())
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $this->assertSame(1, Suggestion::count(), 'الاقتراح يجب أن يُحفظ رغم عطل الإبلاغ.');
    }

    /** مكتب غير مربوط: ليس إخفاقاً — لا وجهة أصلاً */
    public function test_an_unlinked_office_marks_delivery_skipped(): void
    {
        config(['panel.ingest_url' => null, 'panel.ingest_token' => null]);

        $suggestion = Suggestion::create([
            'user_id' => $this->staff()->id,
            'title' => 'اقتراح',
            'content' => str_repeat('ن', 40),
        ]);

        (new DeliverSuggestionJob($suggestion->id))->handle();

        $this->assertSame('skipped', $suggestion->fresh()->delivery_state);
    }

    public function test_a_linked_office_delivers_and_records_it(): void
    {
        config(['panel.ingest_url' => 'https://panel.test', 'panel.ingest_token' => 'tok']);
        Http::fake(['panel.test/*' => Http::response(['ok' => true], 201)]);

        $suggestion = Suggestion::create([
            'user_id' => $this->staff()->id,
            'title' => 'اقتراح',
            'content' => str_repeat('ن', 40),
        ]);

        (new DeliverSuggestionJob($suggestion->id))->handle();

        $suggestion->refresh();
        $this->assertSame('sent', $suggestion->delivery_state);
        $this->assertNotNull($suggestion->delivered_at);
        $this->assertSame(1, $suggestion->delivery_attempts);
    }

    /** تعذّر التسليم يُبقيه معلّقاً ليُعاد — لا يُفقده */
    public function test_a_failed_delivery_keeps_the_suggestion_for_retry(): void
    {
        config(['panel.ingest_url' => 'https://panel.test', 'panel.ingest_token' => 'tok']);
        Http::fake(['panel.test/*' => Http::response(['error' => 'x'], 500)]);

        $suggestion = Suggestion::create([
            'user_id' => $this->staff()->id,
            'title' => 'اقتراح',
            'content' => str_repeat('ن', 40),
        ]);

        try {
            (new DeliverSuggestionJob($suggestion->id))->handle();
        } catch (\Throwable) {
            // متوقَّع: يرمي ليعيد الطابور المحاولة
        }

        $suggestion->refresh();
        $this->assertSame('pending', $suggestion->delivery_state);
        $this->assertSame(1, $suggestion->delivery_attempts);
        $this->assertNotNull($suggestion->delivery_error);
        $this->assertDatabaseCount('suggestions', 1);
    }

    public function test_the_retry_command_picks_up_stuck_suggestions(): void
    {
        Queue::fake();
        config(['panel.ingest_url' => 'https://panel.test', 'panel.ingest_token' => 'tok']);

        $user = $this->staff();
        foreach (['pending', 'failed', 'skipped', 'sent'] as $state) {
            Suggestion::create([
                'user_id' => $user->id,
                'title' => $state,
                'content' => str_repeat('ن', 40),
                'delivery_state' => $state,
            ]);
        }

        $this->artisan('suggestions:retry-delivery')->assertSuccessful();

        // الثلاثة العالقة تُعاد، والمُسلَّم لا يُعاد
        Queue::assertPushed(DeliverSuggestionJob::class, 3);
    }

    /** إعادة التسليم لا تُنشئ نسخة ثانية عند اللوحة */
    public function test_delivering_twice_does_not_duplicate(): void
    {
        config(['panel.ingest_url' => 'https://panel.test', 'panel.ingest_token' => 'tok']);
        Http::fake(['panel.test/*' => Http::response(['ok' => true], 201)]);

        $suggestion = Suggestion::create([
            'user_id' => $this->staff()->id,
            'title' => 'اقتراح',
            'content' => str_repeat('ن', 40),
        ]);

        (new DeliverSuggestionJob($suggestion->id))->handle();
        (new DeliverSuggestionJob($suggestion->id))->handle();

        // الثانية تخرج فوراً لأنه «مُسلَّم» — فلا نداء ثانٍ
        Http::assertSentCount(1);
        $this->assertSame(1, $suggestion->fresh()->delivery_attempts);
    }

    /** رقم الموظف يُرسَل مع الاقتراح */
    public function test_the_employee_code_travels_with_the_suggestion(): void
    {
        config(['panel.ingest_url' => 'https://panel.test', 'panel.ingest_token' => 'tok']);
        Http::fake(['panel.test/*' => Http::response(['ok' => true], 201)]);

        $user = $this->staff();
        $this->actingAs($user)->post(route('suggestions.store'), $this->payload());

        $suggestion = Suggestion::firstOrFail();
        (new DeliverSuggestionJob($suggestion->id))->handle();

        Http::assertSent(function ($request) use ($user) {
            $body = $request->data();

            return ($body['employee_code'] ?? null) === 'EMP-' . str_pad((string) $user->id, 3, '0', STR_PAD_LEFT)
                && ($body['remote_user_id'] ?? null) === $user->id;
        });
    }

    /**
     * تجاوز حدّ الإرسال كان يسقط في المعالج العام فيخرج «حدث خطأ أثناء
     * تنفيذ العملية: Too Many Attempts» — رسالة تُقلق ولا تُفهم، وتُرسل
     * الموظف إلى لوحة التحكم بعيداً عن نموذجه. وهذا أرجح سبب للعطل الذي
     * ظلّ يُبلَّغ عنه.
     */
    public function test_hitting_the_rate_limit_explains_itself(): void
    {
        Queue::fake();
        $user = $this->staff();

        // الحدّ عشرة اقتراحات ناجحة لكل عشر دقائق
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(route('suggestions.store'), [
                'title' => 'اقتراح ' . $i,
                'content' => str_repeat('ن', 40) . $i,
            ]);
        }

        $this->actingAs($user)->post(route('suggestions.store'), $this->payload());

        $message = (string) session('error');

        // الرسالة من ملف اللغة — تُقارَن به لا بنصّ عربي مكتوب حرفياً
        $this->assertNotSame('', $message, 'لا رسالة تشرح سبب المنع.');
        $this->assertStringContainsString(
            \Illuminate\Support\Str::before(__('app.suggestion_rate_limited'), ':minutes'),
            $message,
        );
        $this->assertStringNotContainsString('Too Many Attempts', $message);
        $this->assertStringNotContainsString('حدث خطأ أثناء تنفيذ العملية', $message);
    }

    /**
     * خطأ الكتابة ليس محاولة إغراق.
     *
     * كان الحدّ في المسار فيُستهلك قبل التحقّق: من كتب نصّاً أقصر من
     * عشرين حرفاً خمس مرّات يُحبس عشر دقائق ويظنّ صندوق الاقتراحات
     * معطّلاً. الحدّ الآن بعد نجاح التحقّق.
     */
    public function test_a_short_message_does_not_burn_the_rate_limit(): void
    {
        Queue::fake();
        $user = $this->staff();

        // عشر محاولات كلها مرفوضة لقِصَر النصّ
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(route('suggestions.store'), [
                'title' => 'قصير',
                'content' => 'قصير جداً',
            ]);
        }

        // ثم اقتراح صحيح — يجب أن يُقبل لا أن يُحبَس
        $this->actingAs($user)
            ->post(route('suggestions.store'), $this->payload())
            ->assertSessionHas('success');

        $this->assertDatabaseCount('suggestions', 1);
    }

    /** لا تفاصيل تقنية للمستخدم — تُسجَّل ويُعطى مرجعاً */
    public function test_an_unexpected_failure_shows_a_reference_not_a_stack_trace(): void
    {
        $handler = new \ReflectionClass(app(\Illuminate\Contracts\Debug\ExceptionHandler::class));

        // نتحقّق من العقد نفسه: الرسالة العامة لم تعد تحمل نص الاستثناء
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringNotContainsString(
            "'حدث خطأ أثناء تنفيذ العملية: ' . \$e->getMessage()",
            $bootstrap,
            'نص الاستثناء لا يجوز أن يصل المستخدم.'
        );
        $this->assertStringContainsString('أبلغ الدعم بالرمز', $bootstrap);
    }
}
