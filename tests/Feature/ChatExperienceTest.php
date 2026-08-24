<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** ما تعرضه شاشة المحادثة. */
class ChatExperienceTest extends TestCase
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

    private function say(User $from, string $text, ?string $at = null): Message
    {
        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $from->id,
            'message' => $text,
        ]);

        if ($at !== null) {
            $message->timestamps = false;
            $message->created_at = $at;
            $message->save();
        }

        return $message;
    }

    private function page()
    {
        return $this->actingAs($this->me)->get(route('chat.show', $this->conversation));
    }

    /** يومٌ واحد فاصلٌ واحد: عشرون رسالة في يومٍ لا تحمل عشرين تاريخاً. */
    public function test_each_day_gets_one_separator(): void
    {
        $this->say($this->me, 'أمس ١', now()->subDay()->setTime(9, 0)->toDateTimeString());
        $this->say($this->other, 'أمس ٢', now()->subDay()->setTime(11, 0)->toDateTimeString());
        $this->say($this->me, 'اليوم');

        $html = $this->page()->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '>اليوم<'), 'فاصل «اليوم» مكرَّر');
        $this->assertSame(1, substr_count($html, '>أمس<'), 'فاصل «أمس» مكرَّر');
        $this->assertSame(2, substr_count($html, 'data-day-separator>'));
    }

    /** ✓✓ لا تظهر إلا بعد أن يفتحها الطرف الآخر فعلاً. */
    public function test_a_message_is_marked_read_only_after_the_other_side_opens_it(): void
    {
        $this->say($this->me, 'وصلك؟');

        $this->page()->assertSee('title="أُرسلت"', false)->assertDontSee('title="قُرئت"', false);

        $this->conversation->participants()->updateExistingPivot($this->other->id, [
            'last_read_at' => now()->addMinute(),
        ]);

        $this->page()->assertSee('title="قُرئت"', false);
    }

    /** رسالة الطرف الآخر ليست لي حتى أُعلَم بقراءتها. */
    public function test_receipts_are_shown_only_on_my_own_messages(): void
    {
        $this->say($this->other, 'رسالة منه');

        $this->page()->assertOk()->assertDontSee('title="أُرسلت"', false);
    }

    public function test_an_image_opens_in_a_viewer_and_has_its_own_download(): void
    {
        $this->actingAs($this->me)->post(route('chat.messages.send', $this->conversation), [
            'attachment' => UploadedFile::fake()->image('عقد.png'),
        ])->assertOk();

        $message = Message::firstOrFail();
        $html = $this->page()->assertOk()->getContent();

        $this->assertStringContainsString('data-lightbox="' . e($message->attachment_url) . '"', $html);
        $this->assertStringContainsString(e($message->attachment_download_url), $html);
    }

    /** ملفٌ غير صورة يُعرَض باسمه وحجمه ونوعه، لا كرابطٍ أصمّ. */
    public function test_a_document_shows_its_size_and_kind(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->me->id,
            'message' => '',
            'attachment_path' => 'chat-attachments/x.pdf',
            'attachment_name' => 'صحيفة الدعوى.pdf',
            'attachment_type' => 'application/pdf',
            'attachment_size' => 184320,
        ]);

        \Illuminate\Support\Facades\Storage::disk(\App\Support\Attachments::DISK)
            ->put('chat-attachments/x.pdf', '%PDF-1.4');

        $this->page()->assertOk()
            ->assertSee('صحيفة الدعوى.pdf')
            ->assertSee('180 ك.ب')
            ->assertSee('PDF');
    }

    /**
     * مرفقٌ فُقد ملفُّه من القرص يُقال فيه ذلك.
     *
     * كانت الصورة تظهر مكسورة والرابط يقود إلى 404، فيظنّ الموظف العطلَ
     * في جهازه ويعيد المحاولة بلا طائل.
     */
    public function test_a_missing_file_says_so_instead_of_showing_a_broken_image(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->me->id,
            'message' => '',
            'attachment_path' => 'chat-attachments/ghost.png',
            'attachment_name' => 'مفقود.png',
            'attachment_type' => 'image/png',
        ]);

        $this->page()->assertOk()
            ->assertSee('تعذّر العثور على المرفق')
            ->assertDontSee('data-lightbox="http', false);
    }

    /** الدور يُكتب بالعربية: كانت الترويسة تعرض «lawyer» كما في القاعدة. */
    public function test_the_header_names_the_role_in_arabic(): void
    {
        $this->page()->assertOk()->assertSee('محامٍ')->assertDontSee('>lawyer<', false);
    }

    /** لكل محادثة وموظّف نصٌّ يُبحث فيه من الشريط الجانبي. */
    public function test_the_sidebar_rows_carry_searchable_text(): void
    {
        $this->say($this->other, 'بخصوص القضية');

        $this->page()->assertOk()
            ->assertSee('data-filter-text', false)
            ->assertSee('id="convSearch"', false)
            ->assertSee('id="msgSearch"', false);
    }

    /**
     * اسمُ الملف يكتبه صاحبه. كان يُحقن في الـHTML كما هو عبر جافاسكربت
     * الرسائل الحيّة: ملفٌ اسمُه وسمٌ يُنفَّذ عند كل من في المحادثة.
     */
    public function test_a_crafted_file_name_is_escaped_everywhere_it_appears(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->me->id,
            'message' => '',
            'attachment_path' => 'chat-attachments/y.png',
            'attachment_name' => '"><img src=x onerror=alert(1)>.png',
            'attachment_type' => 'image/png',
        ]);

        \Illuminate\Support\Facades\Storage::disk(\App\Support\Attachments::DISK)
            ->put('chat-attachments/y.png', 'PNG');

        $html = $this->page()->assertOk()->getContent();

        $this->assertStringNotContainsString('"><img src=x', $html, 'الاسم خرج من سياقه فصار وسماً');
        $this->assertStringContainsString('&quot;&gt;&lt;img src=x', $html, 'الاسم لم يُعرض أصلاً');
    }

    /** ولا يُترك بابُ الحقن مفتوحاً في الرسائل الواصلة بلا إعادة تحميل. */
    public function test_the_live_message_builder_escapes_user_values(): void
    {
        $html = $this->page()->assertOk()->getContent();

        $this->assertStringContainsString('const esc =', $html, 'دالّة الهروب غائبة عن بناء الرسائل الحيّة');
        $this->assertStringNotContainsString('${data.attachment_name', $html);
        $this->assertStringNotContainsString('${data.user_name}', $html);
    }
}
