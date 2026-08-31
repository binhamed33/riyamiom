<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * تذكيرُ الجلسات — إلى صاحبه وحده، ومرّةً لكلّ جلسة.
 *
 * ═══ ما تحرسه هذه الاختبارات ═══
 *
 * ١) التذكيرُ يذهب إلى الرقم المكتوب في سجلّ الموكّل لا إلى أيّ رقمٍ
 *    ارتبط باسمه يوماً. فالتذكيرُ يحمل اسمَه ورقمَ قضيّته وموعدَ جلسته
 *    ومحكمتَها — ووصولُه إلى غيره إفشاءٌ لسرّ المهنة.
 *
 * ٢) جلستان في يومٍ واحد تُذكَّران كلتاهما. وابتلاعُ الثانية بوصفها
 *    «تكراراً» يُغيّب الموكّل عن جلسةٍ قد يصدر فيها حكمٌ غيابيّ.
 *
 * ٣) والجلسةُ الواحدة لا تُذكَّر مرّتين: البلاغُ عن التكرار يُنزل تقييمَ
 *    جودة رقم المكتب عند Meta وقد يُقيَّد إرسالُه كلُّه.
 */
class WhatsAppRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-value-for-testing'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_NOTIFY_SESSIONS, '1', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_SESSION_TEMPLATE, 'session_reminder', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_REMINDER_HOURS, '24', 'whatsapp');

        WhatsAppTemplate::create([
            'name' => 'session_reminder', 'language' => 'ar', 'status' => 'APPROVED',
            'body' => 'تذكير {{1}} بقضية {{2}} جلسة {{3}} في {{4}}',
        ]);

        User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->client = Client::create([
            'name' => 'سالم البوسعيدي', 'phone' => '91234567', 'type' => 'individual',
        ]);
        $this->case = LegalCase::create([
            'case_number' => '2026/77', 'title' => 'قضية سالم', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'المحكمة الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $this->client->id,
        ]);
    }

    private function hearing(string $at, string $location = 'الابتدائية'): CourtSession
    {
        return CourtSession::create([
            'case_id' => $this->case->id,
            'date' => $at,
            'location' => $location,
            'status' => 'upcoming',
        ]);
    }

    /**
     * رقمٌ آخرُ ارتبط بالموكّل لا يتلقّى تذكيرَه.
     *
     * كان الأمرُ يسقط إلى `where('client_id', …)` حين لا يجد صفّاً لرقم
     * الموكّل — وذلك صفٌّ برقمٍ مختلفٍ بالضرورة.
     */
    public function test_a_reminder_never_goes_to_another_number_linked_to_the_client(): void
    {
        Queue::fake();

        // صفٌّ مربوطٌ بالموكّل لكن برقمٍ ليس رقمَه (رقمٌ قديم، أو راسل
        // المكتبَ مرّةً فرُبط يدوياً)
        $stranger = WhatsAppContact::create(['wa_id' => '96899999999', 'client_id' => $this->client->id]);
        WhatsAppConversation::create([
            'contact_id' => $stranger->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
        ]);

        $this->hearing(now()->addHours(24)->format('Y-m-d H:i:s'));

        $this->artisan('whatsapp:session-reminders')->assertSuccessful();

        $this->assertSame(
            0,
            WhatsAppMessage::where('conversation_id', $stranger->conversation?->id)->count(),
            'ذهب تذكيرٌ يحمل اسمَ الموكّل ورقمَ قضيّته إلى رقمٍ ليس رقمَه',
        );

        // ويُنشأ بدلاً منه صفٌّ لرقم الموكّل الحقيقي
        $this->assertDatabaseHas('whatsapp_contacts', ['wa_id' => '96891234567']);
    }

    /** جلستان بينهما ساعتان: تذكيران لا واحد. */
    public function test_two_hearings_hours_apart_both_get_a_reminder(): void
    {
        Queue::fake();

        $first = $this->hearing(now()->addHours(24)->format('Y-m-d H:i:s'), 'الابتدائية');
        $this->artisan('whatsapp:session-reminders')->assertSuccessful();

        // ثانيةٌ بعد الأولى بساعتين — تقع في دلوِ التشغيل التالي
        $second = CourtSession::create([
            'case_id' => $this->case->id,
            'date' => $first->date->copy()->addHours(2),
            'location' => 'الاستئناف',
            'status' => 'upcoming',
        ]);

        $this->travel(2)->hours();
        $this->artisan('whatsapp:session-reminders')->assertSuccessful();

        $this->assertSame(1, WhatsAppMessage::where('session_id', $first->id)->count());
        $this->assertSame(
            1,
            WhatsAppMessage::where('session_id', $second->id)->count(),
            'ابتُلع تذكيرُ الجلسة الثانية بوصفه تكراراً — والموكّل لا يعلم بها',
        );
    }

    /** والجلسةُ الواحدة مرّةً واحدة مهما تكرّر التشغيل. */
    public function test_the_same_hearing_is_never_reminded_twice(): void
    {
        Queue::fake();

        $session = $this->hearing(now()->addHours(24)->format('Y-m-d H:i:s'));

        $this->artisan('whatsapp:session-reminders')->assertSuccessful();
        $this->artisan('whatsapp:session-reminders')->assertSuccessful();

        $this->assertSame(1, WhatsAppMessage::where('session_id', $session->id)->count());
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }

    /** ومن سجّل رفضَ المراسلة لا يُذكَّر. */
    public function test_an_opted_out_contact_is_not_reminded(): void
    {
        Queue::fake();

        WhatsAppContact::create([
            'wa_id' => '96891234567',
            'client_id' => $this->client->id,
            'opted_out_at' => now(),
        ]);

        $this->hearing(now()->addHours(24)->format('Y-m-d H:i:s'));

        $this->artisan('whatsapp:session-reminders')->assertSuccessful();

        $this->assertSame(0, WhatsAppMessage::count());
        Queue::assertNothingPushed();
    }
}
