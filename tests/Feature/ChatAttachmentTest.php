<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Attachments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * مرفقات المحادثات.
 *
 * كانت تُحفظ على القرص العام وتُقدَّم بـ Storage::url() عبر الرابط
 * الرمزيّ public/storage — وهو رابطٌ لا يُنشأ في هذا المستودع لأنّ
 * المكان مجلدٌ حقيقيّ متتبَّع في git. فكانت كل صورة تظهر مكسورة وكل
 * تنزيل يقول «File wasn't available on site».
 */
class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    private function conversationOf(User ...$users): Conversation
    {
        $conversation = Conversation::create(['type' => 'private']);
        $conversation->participants()->attach(collect($users)->pluck('id')->all());

        return $conversation;
    }

    private function send(User $sender, Conversation $conversation, UploadedFile $file)
    {
        return $this->actingAs($sender)->post(
            route('chat.messages.send', $conversation),
            ['message' => '', 'attachment' => $file],
        );
    }

    public function test_an_uploaded_image_can_actually_be_opened_again(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        $this->send($sender, $conversation, UploadedFile::fake()->image('IMG_5130.jpg'))
            ->assertOk();

        $message = Message::firstOrFail();

        $this->assertNotNull($message->attachment_path);
        $this->assertTrue(Attachments::exists($message->attachment_path), 'الملف لم يصل القرص');

        $shown = $this->actingAs($sender)->get($message->attachment_url);

        $shown->assertOk();
        $shown->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsString('inline', (string) $shown->headers->get('content-disposition'));
    }

    public function test_the_attachment_url_is_a_guarded_route_not_a_public_storage_path(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        $this->send($sender, $conversation, UploadedFile::fake()->image('a.png'));

        $url = Message::firstOrFail()->attachment_url;

        $this->assertStringNotContainsString('/storage/', $url, 'ما زال يعتمد الرابط الرمزيّ المكسور');
        $this->assertStringContainsString('/attachment', $url);
    }

    public function test_a_stranger_cannot_read_an_attachment_from_a_conversation_he_is_not_in(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        $this->send($sender, $conversation, UploadedFile::fake()->image('عقد.png'));

        $message = Message::firstOrFail();
        $stranger = $this->member();

        $refused = $this->actingAs($stranger)->get($message->attachment_url);

        $refused->assertRedirect(route('dashboard'));
        $this->assertNotSame(200, $refused->getStatusCode());

        $this->post(route('logout'));
        $this->get($message->attachment_url)->assertRedirect(route('login'));
    }

    public function test_download_forces_a_download_and_preview_does_not(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        $this->send($sender, $conversation, UploadedFile::fake()->image('b.png'));
        $message = Message::firstOrFail();

        $inline = $this->actingAs($sender)->get($message->attachment_url);
        $download = $this->actingAs($sender)->get($message->attachment_download_url);

        $this->assertStringContainsString('inline', (string) $inline->headers->get('content-disposition'));
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
    }

    /**
     * الـ SVG صورةٌ في ظاهرها ومستندٌ يحمل سكربتاً في حقيقتها: عرضُه من
     * نطاق المكتب يُشغّل سكربته في جلسة من يفتحه.
     */
    public function test_an_svg_cannot_be_uploaded_at_all(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        $svg = UploadedFile::fake()->createWithContent(
            'x.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->send($sender, $conversation, $svg)->assertSessionHasErrors('attachment');
        $this->assertSame(0, Message::count());
    }

    /** مرفقٌ يُنزَّل يُرسَل بنوعٍ محايد ومنعِ تخمين، فلا يُفسَّر في المتصفّح. */
    public function test_a_downloadable_file_is_sent_with_a_neutral_type(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        $this->send($sender, $conversation, UploadedFile::fake()->create('ملف.zip', 4, 'application/zip'));

        $response = $this->actingAs($sender)->get(Message::firstOrFail()->attachment_url);

        $response->assertHeader('content-type', 'application/octet-stream');
        $response->assertHeader('x-content-type-options', 'nosniff');
    }

    /**
     * ما رُفع قبل هذا التغيير بقي على القرص العام. لا يُنقل ولا يُحذف —
     * يُقرأ من مكانه، فلا يفقد أحدٌ مرفقاً أرسله.
     */
    public function test_an_attachment_saved_on_the_old_disk_still_opens(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        Storage::disk('public')->put('chat-attachments/legacy.png', 'PNGDATA');

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'message' => '',
            'attachment_path' => 'chat-attachments/legacy.png',
            'attachment_name' => 'legacy.png',
            'attachment_type' => 'image/png',
            'attachment_size' => 7,
        ]);

        $this->actingAs($sender)->get($message->attachment_url)->assertOk();
    }

    public function test_deleting_a_message_removes_its_file_from_whichever_disk_holds_it(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        $this->send($sender, $conversation, UploadedFile::fake()->image('c.png'));
        $message = Message::firstOrFail();
        $path = $message->attachment_path;

        $this->actingAs($sender)->delete(route('chat.messages.destroy', $message))->assertOk();

        $this->assertFalse(Attachments::exists($path));
    }

    /** حجمٌ يُقرأ لا رقمٌ خام. */
    public function test_sizes_are_written_for_humans(): void
    {
        $this->assertSame('', Attachments::humanSize(null));
        $this->assertSame('900 بايت', Attachments::humanSize(900));
        $this->assertSame('1.0 ك.ب', Attachments::humanSize(1024));
        $this->assertSame('2.4 م.ب', Attachments::humanSize(2516582));
    }

    /** اسمٌ فيه سطرٌ جديد كان يشقّ ترويسة الردّ إلى ترويستين. */
    public function test_a_crafted_file_name_cannot_split_the_response_header(): void
    {
        $sender = $this->member();
        $conversation = $this->conversationOf($sender, $this->member());

        Storage::disk(Attachments::DISK)->put('chat-attachments/x.png', 'DATA');

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'message' => '',
            'attachment_path' => 'chat-attachments/x.png',
            'attachment_name' => "evil\r\nSet-Cookie: a=b.png",
            'attachment_type' => 'image/png',
        ]);

        $disposition = (string) $this->actingAs($sender)
            ->get($message->attachment_url)
            ->headers->get('content-disposition');

        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
    }
}
