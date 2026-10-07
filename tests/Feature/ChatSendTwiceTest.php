<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الرسالةُ الثانية تُرسَل بلا تحديث.
 *
 * ═══ ما وقع ═══
 *
 * حارسُ التخطيط ضدّ الضغط المزدوج كان يستمع لكلّ submit في طور الالتقاط
 * ويعطّل زرَّ الإرسال — ونموذجُ المحادثة يُرسل بـAJAX ولا يغادر الصفحة،
 * فبقي زرُّه معطَّلاً بعد أوّل رسالة، وEnter لا يُرسل لأنّ الإرسالَ
 * الضمنيَّ يُلغى حين يكون زرُّ النموذج معطَّلاً. فلا رسالةَ ثانيةً إلا
 * بعد تحديث الصفحة.
 */
class ChatSendTwiceTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('feature_chat', '0', 'features');
        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        $this->me = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->conversation = Conversation::create(['type' => 'private']);
        $this->conversation->participants()->attach([$this->me->id, $other->id]);
    }

    private function page(): string
    {
        return $this->actingAs($this->me)->get(route('chat.show', $this->conversation))->assertOk()->getContent();
    }

    /** نموذجُ المحادثة خارجَ حارس التخطيط صراحةً. */
    public function test_the_chat_form_opts_out_of_the_layout_guard(): void
    {
        $this->assertMatchesRegularExpression('/<form id="chatForm"[^>]*data-no-progress="1"/', $this->page());
    }

    /** والحارسُ نفسُه لا يعطّل نموذجاً تولّى إرسالَه بنفسه — لأيّ نموذج AJAX آخر. */
    public function test_the_layout_guard_skips_forms_that_prevented_the_default(): void
    {
        $html = $this->page();

        $start = strpos($html, 'منع الضغط المزدوج');
        $end = strpos($html, "'pageshow'", $start);
        $this->assertNotFalse($start);
        $guard = substr($html, $start, $end - $start);

        $this->assertStringContainsString('if (e.defaultPrevented) return;', $guard);
        $this->assertStringNotContainsString('}, true);', $guard, 'ما زال في طور الالتقاط — يسبق معالجَ النموذج فلا يرى defaultPrevented');
    }

    /** وزرُّ المحادثة يعود بعد كلّ طلب — نجح أو خاب. */
    public function test_the_chat_re_enables_its_own_button_after_the_request(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("sendBtn.disabled = true", $html);
        $this->assertMatchesRegularExpression('/\.finally\(\(\) => \{\s*sending = false;\s*if \(sendBtn\) \{ sendBtn\.disabled = false;/', $html);
    }

    /** الخادمُ يقبل رسالتين متتاليتين — العطبُ كان في الواجهة لا فيه. */
    public function test_two_messages_in_a_row_are_accepted_by_the_server(): void
    {
        $this->actingAs($this->me)->postJson(route('chat.messages.send', $this->conversation), ['message' => 'الأولى'])->assertOk();
        $this->actingAs($this->me)->postJson(route('chat.messages.send', $this->conversation), ['message' => 'الثانية'])->assertOk();

        $this->assertSame(2, $this->conversation->messages()->count());
    }
}
