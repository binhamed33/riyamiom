<?php

namespace Tests\Feature;

use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * محادثة المساعد تبقى.
 *
 * كانت تعيش في الجلسة: من أغلق المتصفّح، أو دخل من حاسوب المكتب بعد
 * هاتفه، أو انتهت جلسته — وجدها قد ذهبت بما فيها. والمحامي يبني
 * سؤاله على ما قبله، فذهابُها ذهابُ العمل نفسه.
 */
class AssistantMemoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    public function test_a_conversation_survives_a_new_session()
    {
        $user = $this->user();

        AssistantMessage::write($user->id, 'user', 'ما مدة الطعن بالاستئناف؟');
        AssistantMessage::write($user->id, 'assistant', 'المدة ثلاثون يوماً من تاريخ النطق بالحكم.');

        // جلسة جديدة تماماً — كأنّ المتصفّح أُغلق وفُتح
        $messages = $this->actingAs($user)->get('/ai-assistant/history')->json('messages');

        $this->assertCount(2, $messages);
        $this->assertSame('ما مدة الطعن بالاستئناف؟', $messages[0]['content']);
        $this->assertSame('assistant', $messages[1]['role']);
    }

    public function test_each_employee_sees_only_their_own_conversation()
    {
        $salim = $this->user();
        $hind = $this->user();

        AssistantMessage::write($salim->id, 'user', 'سؤال سالم');
        AssistantMessage::write($hind->id, 'user', 'سؤال هند');

        $messages = $this->actingAs($hind)->get('/ai-assistant/history')->json('messages');

        $this->assertCount(1, $messages);
        $this->assertSame('سؤال هند', $messages[0]['content']);
    }

    public function test_clearing_empties_only_the_asker()
    {
        $salim = $this->user();
        $hind = $this->user();

        AssistantMessage::write($salim->id, 'user', 'سؤال سالم');
        AssistantMessage::write($hind->id, 'user', 'سؤال هند');

        $this->actingAs($hind)->post('/ai-assistant/clear')->assertOk();

        $this->assertSame(0, AssistantMessage::where('user_id', $hind->id)->count());
        $this->assertSame(1, AssistantMessage::where('user_id', $salim->id)->count());
    }

    public function test_messages_carry_a_stable_id_across_reads()
    {
        $user = $this->user();
        AssistantMessage::write($user->id, 'user', 'سؤال');

        $first = $this->actingAs($user)->get('/ai-assistant/history')->json('messages.0.id');
        $second = $this->actingAs($user)->get('/ai-assistant/history')->json('messages.0.id');

        // كان uniqid() فيتغيّر مع كل قراءة ويُعاد رسم المحادثة كلّها
        $this->assertSame($first, $second);
    }

    public function test_an_old_conversation_is_trimmed_not_lost_wholesale()
    {
        $user = $this->user();

        for ($i = 1; $i <= AssistantMessage::KEEP + 12; $i++) {
            AssistantMessage::write($user->id, 'user', 'سؤال رقم ' . $i);
        }

        $kept = AssistantMessage::of($user->id)->get();

        $this->assertCount(AssistantMessage::KEEP, $kept);
        // الأحدث هو الباقي، والأقدم هو الساقط
        $this->assertSame('سؤال رقم ' . (AssistantMessage::KEEP + 12), $kept->last()->content);
        $this->assertSame('سؤال رقم 13', $kept->first()->content);
    }

    public function test_the_context_sent_to_the_model_is_bounded_and_in_order()
    {
        $user = $this->user();

        for ($i = 1; $i <= 30; $i++) {
            AssistantMessage::write($user->id, $i % 2 ? 'user' : 'assistant', 'رسالة ' . $i);
        }

        $context = AssistantMessage::contextFor($user->id);

        $this->assertCount(AssistantMessage::CONTEXT, $context);
        $this->assertSame('رسالة 11', $context[0]['content']);
        $this->assertSame('رسالة 30', $context[count($context) - 1]['content']);
    }

    public function test_a_question_is_kept_even_when_the_model_never_answers()
    {
        $user = $this->user();

        // بلا مفتاح مضبوط لا يصل الطلب إلى النموذج أصلاً
        $response = $this->actingAs($user)->postJson('/ai-assistant', [
            'message' => 'سؤال لن يُجاب',
        ]);

        $this->assertSame(400, $response->getStatusCode());
        // المساعد غير مهيّأ: لا يُحفظ سؤال لم يُرسل أصلاً
        $this->assertSame(0, AssistantMessage::where('user_id', $user->id)->count());
    }

    public function test_a_guest_reaches_no_conversation()
    {
        $this->get('/ai-assistant/history')->assertRedirect('/login');
        $this->post('/ai-assistant/clear')->assertRedirect('/login');
    }

    public function test_the_panel_offers_a_way_to_start()
    {
        $html = $this->actingAs($this->user())->get('/dashboard')->getContent();

        $this->assertStringContainsString(__('app.ai_starter_1'), $html);
        $this->assertStringContainsString('starters', $html);
        // ونسخُ الجواب، وإعادة المحاولة
        $this->assertStringContainsString(__('app.ai_chat_copy'), $html);
        $this->assertStringContainsString(__('app.ai_chat_retry'), $html);
    }
}
