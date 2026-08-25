<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Support\ClientMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * بريد الموكّل يحمل اسم مكتبه هو ورابط بوابته هو.
 *
 * كان الاسم والرابط مكتوبين حرفياً في الكود، فيرسل كل مكتب رسالة
 * موقّعة باسم مكتب آخر تحيل موكّليه إلى بوابة مكتب آخر.
 */
class ClientMessageTest extends TestCase
{
    use RefreshDatabase;

    private const INHERITED_NAME = 'شركة حمد الريامي للمحاماة';

    public function test_the_message_carries_the_office_that_sends_it(): void
    {
        Setting::set('office_name', 'مكتب الميزان للمحاماة');

        $invite = ClientMessage::portalInvite();

        $this->assertStringContainsString('مكتب الميزان للمحاماة', $invite);
        $this->assertStringNotContainsString(self::INHERITED_NAME, $invite);
        $this->assertStringNotContainsString('office.riyami.om', $invite);
    }

    public function test_the_update_notice_carries_it_too(): void
    {
        Setting::set('office_name', 'مكتب النور للمحاماة');

        $update = ClientMessage::caseUpdate();

        $this->assertStringContainsString('مكتب النور للمحاماة', $update);
        $this->assertStringNotContainsString(self::INHERITED_NAME, $update);
        $this->assertStringNotContainsString('office.riyami.om', $update);
    }

    public function test_the_subject_line_names_the_sending_office(): void
    {
        Setting::set('office_name', 'مكتب البركة للمحاماة');

        $this->assertStringContainsString('مكتب البركة للمحاماة', ClientMessage::inviteSubject());
        $this->assertStringContainsString('مكتب البركة للمحاماة', ClientMessage::updateSubject());
        $this->assertStringNotContainsString(self::INHERITED_NAME, ClientMessage::inviteSubject());
        $this->assertStringNotContainsString(self::INHERITED_NAME, ClientMessage::updateSubject());
    }

    public function test_the_link_points_at_this_offices_own_portal(): void
    {
        config()->set('app.url', 'https://mizan.example.om');
        \Illuminate\Support\Facades\URL::forceRootUrl('https://mizan.example.om');

        $invite = ClientMessage::portalInvite();

        $this->assertStringContainsString('mizan.example.om/client-access', $invite);
        $this->assertStringNotContainsString('office.riyami.om', $invite);
    }

    public function test_an_office_that_set_no_name_falls_back_to_its_own_app_name(): void
    {
        config()->set('app.name', 'مكتب بلا إعدادات');

        $this->assertSame('مكتب بلا إعدادات', ClientMessage::officeName());
        $this->assertStringNotContainsString(self::INHERITED_NAME, ClientMessage::portalInvite());
    }

    public function test_the_instructions_match_how_the_portal_actually_logs_a_client_in(): void
    {
        // البوابة تسأل: رقم الهوية، ثم آخر ثلاثة أرقام من الهاتف.
        // رسالة تطلب غير ذلك تُخرج الموكّل من الباب.
        foreach ([ClientMessage::portalInvite(), ClientMessage::caseUpdate()] as $message) {
            $this->assertStringContainsString('رقم الهوية', $message);
            $this->assertStringContainsString('آخر ثلاثة أرقام', $message);
            $this->assertStringNotContainsString('البريد الإلكتروني المسجل', $message);
        }
    }

    public function test_the_case_number_appears_when_there_is_one(): void
    {
        $case = $this->case();

        $this->assertStringContainsString($case->case_number, ClientMessage::portalInvite($case));
        $this->assertStringNotContainsString('رقم القضية', ClientMessage::portalInvite());
    }

    public function test_the_sender_is_the_offices_own_address(): void
    {
        Setting::set('office_email', 'info@mizan.example.om');

        $this->assertSame('info@mizan.example.om', ClientMessage::fromAddress());
    }

    /**
     * البريد صار مؤجَّلاً، فيُفحَص ما دخل الطابور لا ما خرج من الناقل.
     *
     * وتبدّل معه المُرسِل: عنوانُه بريدُ مُداوَلة المركزي دائماً لأنّ
     * Gmail لا يرسل باسم عنوانٍ لا يملكه، واسمُ المكتب في الاسم المعروض
     * وبريدُه في Reply-To. والمقصودُ الأصلي باقٍ: لا يظهر في بريد مكتبٍ
     * اسمُ مكتبٍ آخر ولا نطاقُه.
     */
    public function test_the_email_that_actually_leaves_carries_this_office_only(): void
    {
        Setting::set('office_name', 'مكتب العدالة الذهبية');
        Setting::set('office_email', 'info@adala.example.om');

        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'mudawalah@gmail.com',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'mudawalah@gmail.com',
            // ركنٌ في isConfigured(): مكتبٌ بلا كلمة مرور لا يُرسل
            'mail.mailers.smtp.password' => 'sixteenlowercase',
        ]);

        \Illuminate\Support\Facades\Mail::fake();

        \App\Services\ClientNotifier::notifyCaseUpdate($this->case());

        \Illuminate\Support\Facades\Mail::assertQueued(
            \App\Mail\ClientCaseMail::class,
            function ($mail) {
                $envelope = $mail->envelope();
                $body = $mail->render() . ' ' . $envelope->subject;

                $this->assertStringContainsString('مكتب العدالة الذهبية', $body);
                $this->assertStringNotContainsString(self::INHERITED_NAME, $body);
                $this->assertStringNotContainsString('office.riyami.om', $body);

                $this->assertSame('mudawalah@gmail.com', $envelope->from->address);
                $this->assertSame('مكتب العدالة الذهبية', $envelope->from->name);
                $this->assertSame('info@adala.example.om', $envelope->replyTo[0]->address);

                return $mail->hasTo('client@example.om');
            },
        );
    }

    private function case(): LegalCase
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $client = Client::factory()->create([
            'name' => 'موكّل الاختبار',
            'email' => 'client@example.om',
            'phone' => '96890000000',
        ]);

        return LegalCase::factory()->create([
            'case_number' => 'C-2026-77',
            'client_id' => $client->id,
            'created_by' => $user->id,
        ]);
    }
}
