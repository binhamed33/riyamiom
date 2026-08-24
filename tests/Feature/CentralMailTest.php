<?php

namespace Tests\Feature;

use App\Mail\ClientCaseMail;
use App\Mail\MailKind;
use App\Mail\SystemNoticeMail;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Services\OfficeMailer;
use App\Support\MailIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * البريد المركزي.
 *
 * المُرسِل واحدٌ لكل المكاتب — بريد مُداوَلة — واسمُ المكتب في الاسم
 * المعروض وبريدُه في Reply-To. وما دون ذلك: لا يُفشِل عملاً، ولا يسرّب
 * سرّاً، ولا يخلط مكتباً بمكتب.
 */
class CentralMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'mudawalah@gmail.com',
            'mail.from.name' => 'مُداوَلة',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'mudawalah@gmail.com',
            'mail.mailers.smtp.password' => 'secret-app-password',
        ]);

        Setting::set('office_name', 'شركة حمد الريامي للمحاماة', 'general');
        Setting::set('office_email', 'office@example.om', 'general');
    }

    private function caseWithClient(?string $email): LegalCase
    {
        $user = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $client = Client::factory()->create([
            'name' => 'زكريا الاسماعيلي',
            'email' => $email,
        ]);

        return LegalCase::factory()->create([
            'client_id' => $client->id,
            'case_number' => 'C-2026-77',
            'created_by' => $user->id,
        ]);
    }

    // ═══ الهويّة ═══

    /**
     * Gmail لا يسمح لحسابٍ أن يرسل باسم عنوانٍ لا يملكه: إمّا يرفض،
     * وإمّا يستبدله صامتاً. فالمُرسِل هو المركزي مهما ضبط المكتب.
     */
    public function test_the_sender_is_always_the_central_address(): void
    {
        Setting::set('office_email', 'another@office.om', 'general');

        $this->assertSame('mudawalah@gmail.com', MailIdentity::fromAddress());
    }

    public function test_the_office_name_is_what_the_client_sees(): void
    {
        $this->assertSame('شركة حمد الريامي للمحاماة', MailIdentity::fromName());
    }

    /** رسالة النظام تُوقَّع «مُداوَلة» لا باسم المكتب. */
    public function test_a_system_notice_is_signed_by_the_system(): void
    {
        $this->assertSame('مُداوَلة', MailIdentity::fromName(system: true));
    }

    /** الردّ يصل المكتب لا الصندوق المركزي. */
    public function test_replies_go_to_the_office_inbox(): void
    {
        $this->assertSame('office@example.om', MailIdentity::replyTo());

        Setting::set('office_email', 'ليس بريداً', 'general');
        $this->assertNull(MailIdentity::replyTo(), 'بريد مكتبٍ مشوَّه لا يُوضع في Reply-To');
    }

    public function test_the_envelope_carries_all_three(): void
    {
        $case = $this->caseWithClient('client@example.om');
        $envelope = (new ClientCaseMail(MailKind::CaseCreated, $case, 'زكريا'))->envelope();

        $this->assertSame('mudawalah@gmail.com', $envelope->from->address);
        $this->assertSame('شركة حمد الريامي للمحاماة', $envelope->from->name);
        $this->assertSame('office@example.om', $envelope->replyTo[0]->address);
    }

    /** لا اسمَ مطوّرٍ ولا اسمَ شخص في شيءٍ يصل الموكّل. */
    public function test_no_personal_name_reaches_the_client(): void
    {
        $case = $this->caseWithClient('client@example.om');
        $mail = new ClientCaseMail(MailKind::CaseCreated, $case, 'زكريا');

        $rendered = $mail->render();

        foreach (['بن حمد', 'riyami.om/dev', 'binhamed', 'Claude'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $rendered, "«{$forbidden}» ظهر للموكّل");
        }

        $this->assertStringContainsString('شركة حمد الريامي للمحاماة', $rendered);
        $this->assertStringContainsString('مُداوَلة', $rendered);
    }

    // ═══ الإرسال ═══

    public function test_creating_a_case_queues_the_invitation(): void
    {
        Mail::fake();

        $case = $this->caseWithClient('client@example.om');
        $result = OfficeMailer::send('client@example.om', new ClientCaseMail(MailKind::CaseCreated, $case));

        $this->assertSame(OfficeMailer::SENT, $result['status']);
        Mail::assertQueued(ClientCaseMail::class, fn ($m) => $m->hasTo('client@example.om'));
    }

    /** مؤجَّلٌ لا فوري: «حفظ القضية» لا ينتظر مصافحة Gmail. */
    public function test_mail_is_queued_never_sent_inside_the_request(): void
    {
        Mail::fake();

        $case = $this->caseWithClient('client@example.om');
        OfficeMailer::send('client@example.om', new ClientCaseMail(MailKind::CaseCreated, $case));

        Mail::assertQueued(ClientCaseMail::class);
        Mail::assertNothingSent();
    }

    /** وعلى طابوره وحده، فلا يُقحَم على مهامّ الطابور العام. */
    public function test_mail_rides_its_own_queue(): void
    {
        Queue::fake();

        $case = $this->caseWithClient('client@example.om');
        $this->assertSame('mail', (new ClientCaseMail(MailKind::CaseCreated, $case))->queue);
    }

    public function test_it_retries_before_giving_up(): void
    {
        $case = $this->caseWithClient('client@example.om');
        $mail = new ClientCaseMail(MailKind::CaseCreated, $case);

        $this->assertSame(3, $mail->tries);
        $this->assertSame([60, 300, 900], $mail->backoff);
    }

    // ═══ الإخفاق لا يُسقط عملاً ═══

    public function test_a_client_with_no_email_is_skipped_quietly(): void
    {
        Mail::fake();

        $case = $this->caseWithClient(null);
        $result = OfficeMailer::send(null, new ClientCaseMail(MailKind::CaseCreated, $case));

        $this->assertSame(OfficeMailer::NO_ADDRESS, $result['status']);
        $this->assertNotNull($result['reason']);
        Mail::assertNothingQueued();
    }

    public function test_a_malformed_email_is_refused_without_naming_it(): void
    {
        Mail::fake();
        Log::spy();

        $case = $this->caseWithClient('ليس بريدا');
        $result = OfficeMailer::send('ليس بريدا', new ClientCaseMail(MailKind::CaseCreated, $case));

        $this->assertSame(OfficeMailer::BAD_ADDRESS, $result['status']);
        Mail::assertNothingQueued();

        // عنوان الموكّل بيانٌ يخصّه: يُقال «غير صالح» ولا يُدوَّن
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($m) => is_string($m) && !str_contains($m, 'ليس بريدا'))
            ->once();
    }

    public function test_an_unconfigured_server_says_so_instead_of_pretending(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();

        $case = $this->caseWithClient('client@example.om');
        $result = OfficeMailer::send('client@example.om', new ClientCaseMail(MailKind::CaseCreated, $case));

        $this->assertSame(OfficeMailer::NOT_CONFIGURED, $result['status']);
        Mail::assertNothingQueued();
    }

    /**
     * أهمّ شرط: القضية تُقيَّد وإن سقط البريد كلَّه.
     */
    public function test_a_collapsing_mail_layer_never_breaks_case_creation(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        Log::spy();

        $case = $this->caseWithClient('client@example.om');

        $result = OfficeMailer::send('client@example.om', new ClientCaseMail(MailKind::CaseCreated, $case));

        $this->assertSame(OfficeMailer::FAILED, $result['status']);
        $this->assertDatabaseHas('cases', ['id' => $case->id]);
    }

    // ═══ الأنواع والتوسعة ═══

    public function test_a_disabled_kind_sends_nothing(): void
    {
        Mail::fake();
        Setting::set(MailKind::CaseCreated->settingKey(), '0', 'notifications');

        $case = $this->caseWithClient('client@example.om');
        $result = OfficeMailer::send('client@example.om', new ClientCaseMail(MailKind::CaseCreated, $case));

        $this->assertSame(OfficeMailer::DISABLED, $result['status']);
        Mail::assertNothingQueued();
    }

    /** الجلسات والمستندات «عند تفعيلها»: مُطفأة حتى يطلبها المكتب. */
    public function test_sessions_and_documents_stay_off_until_asked_for(): void
    {
        $this->assertFalse(MailKind::SessionNotice->isEnabled());
        $this->assertFalse(MailKind::DocumentNotice->isEnabled());
        $this->assertTrue(MailKind::CaseCreated->isEnabled());
        $this->assertTrue(MailKind::SystemNotice->isEnabled());

        Setting::set(MailKind::SessionNotice->settingKey(), '1', 'notifications');
        $this->assertTrue(MailKind::SessionNotice->isEnabled());
    }

    /** لكل نوعٍ قالبُه — نوعٌ بلا قالب يرمي عند الإرسال لا قبله. */
    public function test_every_kind_has_a_template(): void
    {
        foreach (MailKind::all() as $kind) {
            $this->assertTrue(
                view()->exists($kind->view()),
                'النوع ' . $kind->value . ' بلا قالب: ' . $kind->view(),
            );
        }
    }

    // ═══ السرّ لا يخرج ═══

    public function test_the_password_is_never_printed_by_the_doctor(): void
    {
        $this->artisan('mail:doctor')
            ->assertExitCode(0)
            ->expectsOutputToContain('مضبوطة (لا تُعرض)');

        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringNotContainsString('secret-app-password', $output);
    }

    public function test_diagnostics_carry_no_secret(): void
    {
        $flat = json_encode(MailIdentity::diagnostics(), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('secret-app-password', $flat);
        $this->assertStringNotContainsString('mudawalah@gmail.com', (string) MailIdentity::diagnostics()['المستخدم']);
    }

    /**
     * ردُّ خادم SMTP يُنقل كما هو إلى السجلّ، وبعض الخوادم تُعيد فيه ما
     * أُرسل إليها. وسجلُّ المكتب يُقرأ ويُنسخ ويُرسَل عند الشكوى.
     */
    public function test_a_secret_echoed_by_the_server_is_scrubbed_before_logging(): void
    {
        $echo = 'SMTP error: AUTH failed for secret-app-password on mudawalah@gmail.com';

        $clean = MailIdentity::scrub($echo);

        $this->assertStringNotContainsString('secret-app-password', $clean);
        $this->assertStringNotContainsString('mudawalah@gmail.com', $clean);
        $this->assertStringContainsString('[محجوب]', $clean);
    }

    public function test_a_base64_encoded_secret_is_scrubbed_too(): void
    {
        $encoded = base64_encode('secret-app-password');

        $this->assertStringNotContainsString($encoded, MailIdentity::scrub('AUTH PLAIN ' . $encoded));
    }

    public function test_no_credential_is_committed_to_the_repository(): void
    {
        $tracked = shell_exec('cd ' . escapeshellarg(base_path()) . ' && git ls-files 2>/dev/null') ?: '';

        $this->assertStringNotContainsString('.env' . PHP_EOL, $tracked, 'ملف .env مُتتبَّع في Git');

        $example = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^MAIL_PASSWORD=\s*$/m', $example, 'قالب البيئة يحمل كلمة مرور');
    }

    // ═══ عزل المكاتب ═══

    /**
     * كلُّ مكتبٍ قاعدتُه، فالاسمُ والبريدُ يُقرآن من إعداداته هو. لو
     * قُرئا من ثابتٍ في الكود لأرسل مكتبٌ باسم مكتبٍ آخر.
     */
    public function test_the_identity_follows_the_office_not_the_code(): void
    {
        Setting::set('office_name', 'مكتب آخر للمحاماة', 'general');
        Setting::set('office_email', 'other@office.om', 'general');

        $this->assertSame('مكتب آخر للمحاماة', MailIdentity::fromName());
        $this->assertSame('other@office.om', MailIdentity::replyTo());

        $case = $this->caseWithClient('client@example.om');
        $rendered = (new ClientCaseMail(MailKind::CaseCreated, $case))->render();

        $this->assertStringContainsString('مكتب آخر للمحاماة', $rendered);
        $this->assertStringNotContainsString('شركة حمد الريامي', $rendered);
    }

    /** رابط البوابة من نطاق المكتب نفسه، لا من نطاق مكتبٍ غيره. */
    public function test_the_portal_link_belongs_to_this_office(): void
    {
        $case = $this->caseWithClient('client@example.om');
        $rendered = (new ClientCaseMail(MailKind::CaseCreated, $case))->render();

        $this->assertStringContainsString(rtrim(config('app.url'), '/'), $rendered);
    }

    // ═══ القالب ═══

    /** اسمُ موكّلٍ فيه قوسٌ زاويّ لا يكسر الرسالة ولا يُنفَّذ. */
    public function test_a_hostile_name_cannot_break_the_template(): void
    {
        $rendered = (new SystemNoticeMail(
            heading: 'تنبيه',
            bodyText: 'مرحباً <script>alert(1)</script>',
            recipientName: '<b>خطر</b>',
        ))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        $this->assertStringNotContainsString('<b>خطر</b>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
    }

    // ═══ الجلسات والمستندات ═══

    /** جلسةٌ تُقيَّد ونوعُ الإشعار مُطفأ: لا يصل الموكّل شيء. */
    public function test_a_session_sends_nothing_while_its_kind_is_off(): void
    {
        Mail::fake();

        $case = $this->caseWithClient('client@example.om');
        $session = \App\Models\Session::create([
            'case_id' => $case->id,
            'date' => now()->addWeek(),
            'location' => 'محكمة مسقط',
            'status' => 'upcoming',
        ]);

        \App\Services\ClientNotifier::notifySession($case, $session);

        Mail::assertNotQueued(\App\Mail\ClientEventMail::class);
    }

    /** فإذا فُعِّل، وصل الإشعار بتفاصيل الجلسة. */
    public function test_once_enabled_a_session_notice_carries_its_details(): void
    {
        Mail::fake();
        Setting::set(MailKind::SessionNotice->settingKey(), '1', 'notifications');

        $case = $this->caseWithClient('client@example.om');
        $session = \App\Models\Session::create([
            'case_id' => $case->id,
            'date' => now()->addWeek(),
            'location' => 'محكمة مسقط',
            'status' => 'upcoming',
        ]);

        \App\Services\ClientNotifier::notifySession($case, $session);

        Mail::assertQueued(\App\Mail\ClientEventMail::class, function ($m) {
            $body = $m->render();

            return str_contains($body, 'محكمة مسقط') && str_contains($body, 'C-2026-77');
        });
    }

    /**
     * إشعارُ المستند لا يحمل اسم الملف: قد يكشف ما لا يُراد كشفُه في
     * صندوق بريدٍ قد يقرؤه غيرُ صاحبه.
     */
    public function test_a_document_notice_never_names_the_file(): void
    {
        Mail::fake();
        Setting::set(MailKind::DocumentNotice->settingKey(), '1', 'notifications');

        $case = $this->caseWithClient('client@example.om');
        $document = \App\Models\Document::create([
            'case_id' => $case->id,
            'title' => 'تقرير الطب الشرعي.pdf',
            'file_path' => 'documents/x.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 1024,
            'client_visible' => true,
        ]);

        \App\Services\ClientNotifier::notifyDocument($document);

        Mail::assertQueued(\App\Mail\ClientEventMail::class, function ($m) {
            return !str_contains($m->render(), 'الطب الشرعي');
        });
    }

    public function test_the_template_survives_without_a_portal_link(): void
    {
        $rendered = (new SystemNoticeMail('تنبيه', 'نصّ'))->render();

        $this->assertStringNotContainsString('الدخول إلى بوابة المتابعة', $rendered);
        $this->assertStringContainsString('نصّ', $rendered);
    }
}
