<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\LegalCase;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\SetupDoctor;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * عيوبُ التواصل التي رصدتها بروفةُ الإعلان: واتساب والمحادثات والمساعد.
 *
 * كلُّ اختبارٍ هنا يقرأ ما يراه الموظّف في الصفحة المرسومة فعلاً — لا
 * إعداداً ولا دالّةً بمعزلٍ عن قالبها — لأنّ العيوب كلَّها كانت في
 * الرسم: رقمٌ مقلوب، ووحدةٌ إنجليزيّة، وعددٌ لا يوافق معدودَه.
 */
class CommsFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // الأحد ١٨ أكتوبر — فالخميسُ قبل ثلاثة أيام والسبتُ «أمس»
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00', 'Asia/Muscat'));

        // لا اتّصالَ حقيقيّ بأيّ خدمة من هنا
        Http::preventStrayRequests();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        // المكاتبُ عربيّة: لغةُ المستخدم هي ما يقرّر لغةَ الصفحة المرسومة
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'locale' => 'ar']);
    }

    /** مزوّدُ Meta مربوطاً، وصندوقُ الوارد ظاهراً. */
    private function metaConnected(): void
    {
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-value-for-testing'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_WABA_ID, '999888777', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');
    }

    private function thread(array $contact = [], array $conversation = []): WhatsAppConversation
    {
        $contact = WhatsAppContact::create(array_merge(['wa_id' => '96890001502'], $contact));

        return WhatsAppConversation::create(array_merge([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now()->subHours(3),
            'last_message_at' => now()->subHours(3),
        ], $conversation));
    }

    private function inbound(WhatsAppConversation $conversation, array $over = []): WhatsAppMessage
    {
        return WhatsAppMessage::create(array_merge([
            'conversation_id' => $conversation->id,
            'wamid' => 'wamid.' . bin2hex(random_bytes(6)),
            'direction' => WhatsAppMessage::IN,
            'type' => 'text',
            'body' => 'السلام عليكم',
            'status' => WhatsAppMessage::STATUS_DELIVERED,
            'sent_at' => now(),
        ], $over));
    }

    // ── ١) مستفسرٌ بلا اسم ───────────────────────────────────────

    /**
     * ‏«+968 9000 1502» في سطرٍ عربيّ بلا عزلٍ تنقلب مجموعاتُه
     * ‏«1502 9000 968+»، وحرفُ الصورة يصير «+».
     */
    public function test_an_unnamed_contact_is_shown_by_its_number_isolated_left_to_right(): void
    {
        $this->metaConnected();
        $conversation = $this->thread();
        $this->inbound($conversation);

        $inbox = $this->actingAs($this->admin)->get(route('whatsapp.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<bdi dir="ltr">+968 9000 1502</bdi>', $inbox, 'الرقمُ في قائمة الوارد بلا عزلٍ يساريّ');
        $this->assertStringContainsString('data-wa-avatar-phone', $inbox, 'حرفُ الصورة ما زال «+» بدل رمز الهاتف');

        $thread = $this->actingAs($this->admin)->get(route('whatsapp.show', $conversation))->assertOk()->getContent();
        $this->assertStringContainsString('<bdi dir="ltr">+968 9000 1502</bdi>', $thread, 'الرقمُ في ترويسة الخيط بلا عزلٍ يساريّ');
        $this->assertStringContainsString('data-wa-avatar-phone', $thread);
        $this->assertSame(1, substr_count($thread, '+968 9000 1502'), 'الرقمُ عنوانٌ ثمّ يتكرّر سطراً تحته');
    }

    /** ومن له اسمٌ يبقى كما كان: حرفُ اسمه، ورقمُه سطراً تحته. */
    public function test_a_named_contact_keeps_its_initial_and_number_line(): void
    {
        $this->metaConnected();
        $conversation = $this->thread(['profile_name' => 'سالم']);
        $this->inbound($conversation);

        $thread = $this->actingAs($this->admin)->get(route('whatsapp.show', $conversation))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-wa-avatar-phone', $thread);
        $this->assertStringContainsString('+968 9000 1502', $thread);
        $this->assertStringNotContainsString('<bdi dir="ltr">سالم</bdi>', $thread);
    }

    // ── ٢) فواصلُ الأيام في خيط واتساب ───────────────────────────

    public function test_the_whatsapp_thread_separates_days(): void
    {
        $this->metaConnected();
        $conversation = $this->thread(['profile_name' => 'سالم']);

        $this->inbound($conversation, ['body' => 'رسالة الخميس', 'sent_at' => now()->subDays(3)->setTime(10, 0)]);
        $this->inbound($conversation, ['body' => 'رسالة السبت ١', 'sent_at' => now()->subDay()->setTime(9, 0)]);
        $this->inbound($conversation, ['body' => 'رسالة السبت ٢', 'sent_at' => now()->subDay()->setTime(11, 0)]);
        $this->inbound($conversation, ['body' => 'رسالة اليوم', 'sent_at' => now()->setTime(8, 0)]);

        $html = $this->actingAs($this->admin)->get(route('whatsapp.show', $conversation))->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-day-separator'), 'فاصلٌ واحدٌ لكل يوم — لا أكثر ولا أقلّ');
        $this->assertStringContainsString('الخميس 15 أكتوبر 2026', $html);
        $this->assertSame(1, substr_count($html, '>أمس<'));
        $this->assertSame(1, substr_count($html, '>اليوم<'));

        // والترتيبُ: الخميس قبل رسالته، وأمس قبل رسائل السبت
        $this->assertLessThan(mb_strpos($html, 'رسالة الخميس'), mb_strpos($html, 'الخميس 15 أكتوبر 2026'));
        $this->assertLessThan(mb_strpos($html, 'رسالة السبت ١'), mb_strpos($html, '>أمس<'));
        $this->assertGreaterThan(mb_strpos($html, 'رسالة السبت ٢'), mb_strpos($html, '>اليوم<'));
    }

    // ── ٣ و٤) بطاقةُ المرفق ──────────────────────────────────────

    public function test_attachment_sizes_use_arabic_units(): void
    {
        $this->metaConnected();
        $conversation = $this->thread(['profile_name' => 'سالم']);

        $this->inbound($conversation, [
            'type' => 'document', 'body' => null, 'media_id' => 'media-1',
            'media_name' => 'عقد.pdf', 'media_size' => (int) (1.3 * 1048576),
        ]);

        $html = $this->actingAs($this->admin)->get(route('whatsapp.show', $conversation))->assertOk()->getContent();

        $this->assertStringContainsString('data-wa-media-meta>مستند · 1.3 م.ب<', $html);
        $this->assertDoesNotMatchRegularExpression('/\d\s*(MB|KB)\b/', $html, 'وحدةُ حجمٍ إنجليزيّة في بطاقة المرفق');
    }

    /** صورةٌ بلا اسمِ ملفّ: «صورة» عنواناً، والحجمُ وحده تحته. */
    public function test_an_unnamed_image_does_not_repeat_its_type(): void
    {
        $this->metaConnected();
        $conversation = $this->thread(['profile_name' => 'سالم']);

        $this->inbound($conversation, [
            'type' => 'image', 'body' => null, 'media_id' => 'media-2', 'media_size' => 348 * 1024,
        ]);
        $this->inbound($conversation, [
            'type' => 'audio', 'body' => null, 'media_id' => 'media-3', 'media_size' => null,
        ]);

        $html = $this->actingAs($this->admin)->get(route('whatsapp.show', $conversation))->assertOk()->getContent();

        $this->assertStringContainsString('data-wa-media-meta>348 ك.ب<', $html);
        $this->assertStringNotContainsString('صورة · ', $html, 'نوعُ الصورة مكرَّرٌ تحت عنوانها');
        $this->assertStringNotContainsString('رسالة صوتية · ', $html);
        $this->assertSame(1, substr_count($html, 'data-wa-media-meta'), 'بصمةٌ بلا حجمٍ ولا اسم رسمت سطراً فارغاً أو مكرَّراً');
    }

    // ── ١٠) جسرُ Evolution بلا نافذة ─────────────────────────────

    public function test_no_window_chip_renders_when_the_provider_has_no_window(): void
    {
        config([
            'whatsapp.default' => 'evolution',
            'whatsapp.evolution.base_url' => 'https://bridge.test',
            'whatsapp.evolution.api_key' => 'bridge-key-0123456789',
        ]);
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');
        Http::fake(['bridge.test/*' => Http::response(['instance' => ['state' => 'open']], 200)]);

        $conversation = $this->thread(['profile_name' => 'سالم'], ['last_inbound_at' => now()->subDays(5)]);
        $this->inbound($conversation);

        foreach ([route('whatsapp.index'), route('whatsapp.show', $conversation)] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString(__('app.wa_window_closed'), $html, "«النافذة مغلقة» على مزوّدٍ بلا نافذة: $url");
            $this->assertStringNotContainsString(__('app.wa_window_open'), $html, "شريحةُ نافذةٍ على مزوّدٍ بلا نافذة: $url");
            $this->assertStringNotContainsString(__('app.wa_window_left', ['minutes' => 0]), $html, "«يتبقّى 0 دقيقة»: $url");
        }
    }

    /** وعلى Meta تبقى الشريحةُ كما كانت. */
    public function test_the_window_chip_still_renders_on_meta(): void
    {
        $this->metaConnected();
        $conversation = $this->thread(['profile_name' => 'سالم'], ['last_inbound_at' => now()->subDays(2)]);
        $this->inbound($conversation);

        $this->actingAs($this->admin)->get(route('whatsapp.index'))->assertOk()->assertSee(__('app.wa_window_closed'));
        $this->actingAs($this->admin)->get(route('whatsapp.show', $conversation))->assertOk()->assertSee(__('app.wa_window_closed'));
    }

    // ── ٥) لا اسمَ مزوّدٍ في ملفّ القضيّة ─────────────────────────

    public function test_the_case_ai_panels_do_not_name_the_vendor(): void
    {
        $case = LegalCase::factory()->create(['client_id' => Client::factory()->create()->id]);
        $developer = User::factory()->create(['role' => 'developer', 'is_active' => true, 'locale' => 'ar']);

        $this->actingAs($developer)->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee(__('app.ai_chat_title'))
            ->assertDontSee('Gemini');
    }

    // ── ٦) أوقاتُ المساعد بالأرقام الغربيّة ──────────────────────

    public function test_assistant_times_use_western_digits(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString("toLocaleTimeString('ar-OM-u-nu-latn'", $html);
        $this->assertStringNotContainsString("toLocaleTimeString('ar-OM'", $html);

        // ويُشغَّل المكوّنُ كما يُشحن إن وُجد Node — فهو ما يقرّر الأرقام لا النصّ
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            return;
        }

        $start = mb_strpos($html, "Alpine.data('assistant'");
        $this->assertNotFalse($start, 'مكوّن المساعد غير موجود في الصفحة');
        $end = mb_strpos($html, '}));', $start);
        $component = mb_substr($html, $start, $end - $start + 4);

        $script = "let c; const Alpine = { data: (n, f) => { c = f(); } };\n"
            . $component
            . "\nprocess.stdout.write(c.when('2026-10-18T06:05:00Z'));\n";
        $file = tempnam(sys_get_temp_dir(), 'aiw') . '.js';
        file_put_contents($file, $script);

        exec(escapeshellarg($node) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
        unlink($file);

        $this->assertSame(0, $code, implode("\n", $out));
        $time = implode('', $out);
        $this->assertMatchesRegularExpression('/\d{1,2}:\d{2}/', $time, "الوقتُ بلا أرقامٍ غربيّة: $time");
        $this->assertDoesNotMatchRegularExpression('/[٠-٩]/u', $time, "أرقامٌ هنديّة في وقت المساعد: $time");
    }

    // ── ٧) قائمةُ المحادثات: اسمٌ طويل لا يلتصق بالوقت ────────────

    public function test_a_long_conversation_name_keeps_a_gap_from_the_time(): void
    {
        Setting::set('feature_chat', '0', 'features');

        $mona = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'منى', 'locale' => 'ar']);
        $hilal = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'المحامي هلال بن سعيد الحارثي', 'locale' => 'ar']);

        $conversation = Conversation::create(['type' => 'private']);
        $conversation->participants()->attach([$mona->id, $hilal->id]);
        $message = Message::create(['conversation_id' => $conversation->id, 'user_id' => $hilal->id, 'message' => 'صباح الخير']);
        $message->timestamps = false;
        $message->created_at = now()->subHours(4);
        $message->save();

        $html = $this->actingAs($mona)->get(route('chat.index'))->assertOk()->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $title = (new \DOMXPath($dom))
            ->query("//a[@data-conv-row]//p[contains(normalize-space(.), 'المحامي هلال بن سعيد الحارثي')]")
            ->item(0);

        $this->assertNotNull($title, 'صفُّ المحادثة لم يُرسم');

        $titleClasses = preg_split('/\s+/', (string) $title->getAttribute('class'));
        $rowClasses = preg_split('/\s+/', (string) $title->parentNode->getAttribute('class'));

        $this->assertContains('truncate', $titleClasses);
        $this->assertContains('min-w-0', $titleClasses, 'الاسمُ لا يضيق فلا يُقصّ');
        $this->assertNotEmpty(preg_grep('/^gap-/', $rowClasses), 'لا فجوةَ بين الاسم والوقت');
    }

    // ── ٨) عددُ القوالب بالعربيّة ────────────────────────────────

    public function test_the_template_count_agrees_with_its_number(): void
    {
        $reason = function (): string {
            foreach (app(SetupDoctor::class)->report()['steps'] as $step) {
                if ($step['key'] === 'templates') {
                    return (string) $step['reason'];
                }
            }

            $this->fail('لا خطوةَ قوالب');
        };

        $expected = [1 => 'قالبٌ واحد معتمَد.', 2 => 'قالبان معتمَدان.', 4 => '4 قوالب معتمَدة.', 11 => '11 قالباً معتمَداً.'];

        $made = 0;
        foreach ($expected as $count => $sentence) {
            for (; $made < $count; $made++) {
                WhatsAppTemplate::create([
                    'name' => 'tpl_' . $made, 'language' => 'ar', 'status' => 'APPROVED', 'body' => 'نصّ {{1}}',
                ]);
            }

            $this->assertSame($sentence, $reason(), "عددُ القوالب لا يوافق معدودَه عند $count");
        }
    }

    /** ورسالةُ زرّ المزامنة بالقاعدة نفسِها. */
    public function test_the_template_sync_message_agrees_with_its_number(): void
    {
        $this->metaConnected();

        Http::fake([
            '*/999888777/message_templates*' => Http::response(['data' => array_map(fn ($i) => [
                'name' => 'tpl_' . $i, 'language' => 'ar', 'status' => 'APPROVED', 'category' => 'UTILITY',
                'components' => [['type' => 'BODY', 'text' => 'نصّ {{1}}']],
            ], range(1, 4))], 200),
        ]);

        $this->actingAs($this->admin)
            ->from(route('settings.index'))
            ->post(route('settings.whatsapp.templates.sync'))
            ->assertRedirect()
            ->assertSessionHas('success', 'تمّت مزامنة 4 قوالب من Meta.');
    }

    // ── ٩) إشعارُ المحادثة لا يفترض جنسَ المُرسِل ─────────────────

    public function test_the_chat_notification_title_is_gender_neutral(): void
    {
        Setting::set('feature_chat', '0', 'features');
        // لغةُ النظام في المكاتب عربيّة — وبها يُحفظ نصُّ العنوان الاحتياطيّ
        config(['app.locale' => 'ar']);

        $maryam = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'مريم', 'locale' => 'ar']);
        $conversation = Conversation::create(['type' => 'private']);
        $conversation->participants()->attach([$maryam->id, $this->admin->id]);

        $this->actingAs($maryam)
            ->postJson(route('chat.messages.send', $conversation), ['message' => 'هل وصلت المذكرة؟'])
            ->assertOk();

        $notification = Notification::where('user_id', $this->admin->id)
            ->where('type', Notification::TYPE_CHAT)
            ->firstOrFail();

        $this->assertSame('رسالة جديدة من مريم', $notification->localizedTitle('ar'));
        $this->assertSame('New message from مريم', $notification->localizedTitle('en'));

        // والإشعارُ المحدَّث برسالةٍ ثانية بالصياغة نفسِها
        $this->actingAs($maryam)
            ->postJson(route('chat.messages.send', $conversation), ['message' => 'ومعها الحافظة'])
            ->assertOk();

        $this->assertSame('رسالة جديدة من مريم', $notification->fresh()->localizedTitle('ar'));
        $this->assertSame('رسالة جديدة من مريم', $notification->fresh()->title);
    }
}
