<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «كم رسالة؟» و«من أين؟» و«أين تفتح المحادثة؟».
 *
 * ═══ ما اشتكى منه المكتب ═══
 *
 * «أوّل ما يدخل شات يظهّره من منتصف المحادثة لا من تحت، وما يظهر كم إشعار،
 * وما يظهر الرسالة جاية من عند مَن».
 *
 * وثلاثتها في الشفرة لا في الوهم: شارةُ الشريط كانت تُحدَّث من نصٍّ داخل
 * صفحة المحادثات وحدَها؛ وشاراتُ الصفوف تسقط متى فُتحت محادثة لأنّ استعلامَ
 * العدّ لم يكن في تلك الشاشة؛ ورسالةٌ إلى المطوّر كانت تذهب إلى ديسكورد
 * فلا يبقى لها أثرٌ في النظام.
 */
class ChatUnreadTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $other;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('feature_chat', '0', 'features');
        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        $this->me = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->other = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'عبدالله الكندي']);

        $this->conversation = Conversation::create(['type' => 'private']);
        $this->conversation->participants()->attach([$this->me->id, $this->other->id]);
    }

    private function say(User $from, string $text = 'رسالة'): Message
    {
        return Message::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $from->id,
            'message' => $text,
        ]);
    }

    // ══════════ كم ══════════

    /** الشارةُ تظهر في قائمة المحادثات، وتبقى ظاهرةً على البقيّة وواحدةٌ مفتوحة. */
    public function test_the_row_badge_survives_having_a_conversation_open(): void
    {
        $this->say($this->other);
        $this->say($this->other);

        $list = $this->actingAs($this->me)->get(route('chat.index'))->assertOk();
        $this->assertStringContainsString('data-conv-badge="' . $this->conversation->id . '"', $list->getContent());

        // محادثةٌ ثانية مفتوحة: شارةُ الأولى كانت تختفي لأنّ الشاشةَ لا تعدّ
        $second = Conversation::create(['type' => 'private']);
        $second->participants()->attach([$this->me->id, $this->other->id]);

        $open = $this->actingAs($this->me)->get(route('chat.show', $second))->assertOk();
        $badge = $this->badgeFor($open->getContent(), $this->conversation->id);

        $this->assertStringNotContainsString('hidden', $badge, 'الشارةُ اختفت لأنّ الشاشةَ المفتوحة لا تعدّ');
        $this->assertStringContainsString('>2<', $badge);
    }

    /** ورسائلي أنا ليست «غير مقروءة» مهما تأخّر ختمُ القراءة. */
    public function test_my_own_messages_are_never_unread(): void
    {
        $this->say($this->me);
        $this->say($this->me);

        $this->actingAs($this->me)->get(route('chat.unread'))
            ->assertOk()
            ->assertJson(['count' => 0]);

        $this->say($this->other);

        $this->actingAs($this->me)->get(route('chat.unread'))
            ->assertOk()
            ->assertJson(['count' => 1, 'conversations' => [$this->conversation->id => 1]]);
    }

    /** وفتحُ المحادثة يُصفّرها. */
    public function test_opening_the_conversation_clears_its_count(): void
    {
        $this->say($this->other);

        $this->actingAs($this->me)->get(route('chat.show', $this->conversation))->assertOk();

        $this->actingAs($this->me)->get(route('chat.unread'))->assertOk()->assertJson(['count' => 0]);
    }

    /**
     * والشارةُ تنبض في كلّ صفحة لا في صفحة المحادثات وحدَها.
     *
     * من يعمل على القضايا كان لا يعرف أنّ أحداً كلّمه حتى يفتح المحادثات.
     */
    public function test_every_page_polls_the_badge(): void
    {
        $page = $this->actingAs($this->me)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="chatUnreadBadge"', $page);
        $this->assertStringContainsString('mudawalaChatUnread', $page, 'لا نبضةَ للشارة خارج صفحة المحادثات');
        $this->assertStringContainsString(route('chat.unread'), $page);
    }

    // ══════════ من أين ══════════

    /** رسالةٌ إلى المطوّر تترك أثراً في النظام لا في ديسكورد وحدَه. */
    public function test_a_message_to_a_developer_still_notifies_inside_the_system(): void
    {
        $dev = User::factory()->create(['role' => 'developer', 'is_active' => true]);
        $room = Conversation::create(['type' => 'private']);
        $room->participants()->attach([$this->other->id, $dev->id]);

        $this->actingAs($this->other)->post(route('chat.messages.send', $room), ['message' => 'عندي مشكلة'])
            ->assertOk();

        $notification = Notification::where('user_id', $dev->id)->where('type', Notification::TYPE_CHAT)->first();

        $this->assertNotNull($notification, 'المطوّرُ لم يُخطَر داخل النظام');
        $this->assertStringContainsString($this->other->name, $notification->localizedTitle(), 'الإشعارُ لا يقول ممّن');
    }

    /** والإشعارُ المتراكم يحمل اسمَ آخر مرسِل لا أوّلِهم. */
    public function test_a_stacked_notification_names_the_latest_sender(): void
    {
        $third = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'مريم البلوشي']);
        $room = Conversation::create(['type' => 'group']);
        $room->participants()->attach([$this->me->id, $this->other->id, $third->id]);

        $this->actingAs($this->other)->post(route('chat.messages.send', $room), ['message' => 'أوّل'])->assertOk();
        $this->actingAs($third)->post(route('chat.messages.send', $room), ['message' => 'ثانٍ'])->assertOk();

        $notification = Notification::where('user_id', $this->me->id)->where('type', Notification::TYPE_CHAT)->firstOrFail();

        $this->assertSame(2, (int) $notification->message_count);
        $this->assertStringContainsString('مريم البلوشي', $notification->localizedTitle(), 'الإشعارُ باقٍ على اسم أوّل من كتب');
    }

    /** والمجموعةُ تُعرَف بأهلِها لا باسم أوّلِهم. */
    public function test_a_group_is_named_after_its_members(): void
    {
        $third = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'مريم البلوشي']);
        $room = Conversation::create(['type' => 'group']);
        $room->participants()->attach([$this->me->id, $this->other->id, $third->id]);

        $page = $this->actingAs($this->me)->get(route('chat.show', $room))->assertOk();

        $page->assertSee('عبدالله')->assertSee('مريم')->assertSee('3 أعضاء');
        $this->assertTrue($room->fresh()->load('participants')->isGroup());
        $this->assertSame('عبدالله، مريم', $room->fresh()->load('participants')->titleFor($this->me->id));
    }

    // ══════════ أين تفتح ══════════

    /**
     * تُفتح المحادثةُ على آخرها لا على وسطها.
     *
     * التمريرُ «الناعم» إلى مِرساةٍ في الذيل ينتهي حيث كانت المِرساةُ قبل أن
     * تُحمَّل الصور (loading=lazy) وتزيدَ الطول — فتقف الشاشةُ في الوسط.
     */
    public function test_the_conversation_opens_at_its_end(): void
    {
        $this->say($this->other);
        $page = $this->page();

        $this->assertStringContainsString('messagesEl.scrollTo({ top: messagesEl.scrollHeight', $page,
            'الفتحُ لا يقفز إلى آخر المحادثة');
        $this->assertStringNotContainsString("anchor?.scrollIntoView({ behavior: 'smooth' })", $page,
            'ما زال التمريرُ الناعمُ إلى المِرساة — ينتهي في الوسط مع الصور');
        $this->assertStringContainsString("img.addEventListener('load'", $page,
            'الصورُ المتأخّرة لا تُعيد الإلصاقَ بالأسفل');
    }

    private function page(): string
    {
        return $this->actingAs($this->me)->get(route('chat.show', $this->conversation))->assertOk()->getContent();
    }

    private function badgeFor(string $html, int $conversationId): string
    {
        $needle = 'data-conv-badge="' . $conversationId . '"';
        $at = strpos($html, $needle);
        $this->assertNotFalse($at, 'لا شارةَ لهذه المحادثة في القائمة');

        return substr($html, $at, 260);
    }
}
