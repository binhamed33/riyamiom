<?php

namespace Tests\Feature;

use App\Mail\ClientCaseMail;
use App\Mail\MailKind;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * هويّة المكتب في بريد موكّليه — وما تعرضه الشاشة عنها.
 *
 * ═══ العطل الذي وُضعت له ═══
 *
 * الاسمُ المعروض ووجهةُ الردّ يُقرآن من حقلين في «معلومات المكتب»،
 * وليس في الحقلين ولا حولهما ما يقول إنّهما يحكمان ما يصل الموكّل.
 * فبقي مكتبٌ بلا بريدٍ مسجَّل، فذهب ردُّ موكّله إلى الصندوق المركزي
 * ولم يره أحد — ولم يُنبَّه أحدٌ إلى ذلك في أي شاشة.
 *
 * فالمحروس هنا شيئان: أنّ الشاشة تقول الحقيقة، وأنّ الحقيقة التي
 * تقولها هي نفسها التي تخرج في الترويسة.
 */
class MailIdentityScreenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private int $seq = 0;

    private function mail(): ClientCaseMail
    {
        // رقمٌ جديد لكل نداء: الاختبار الواحد يبني رسالتين — قبل الحفظ
        // وبعده — ورقمُ القضية فريدٌ في قاعدة البيانات.
        $client = Client::factory()->create(['name' => 'موكّل', 'email' => 'client@example.om']);
        $case = LegalCase::factory()->create([
            'client_id' => $client->id,
            'case_number' => 'C-' . (++$this->seq),
            'created_by' => $this->admin()->id,
        ]);

        return new ClientCaseMail(MailKind::CaseCreated, $case);
    }

    // ─────────────────────────────────────────────── الاسم المعروض

    /** اسمُ المكتب حين يُضبط. */
    public function test_the_office_name_is_what_the_client_sees(): void
    {
        Setting::set('office_name', 'شركة حمد الريامي للمحاماة', 'general');

        $this->assertSame('شركة حمد الريامي للمحاماة', MailIdentity::fromName());
    }

    /**
     * ولا يُسقَط على APP_NAME الافتراضي.
     *
     * قيمةُ لارافل الافتراضية «Laravel»، ومكتبٌ لم يُضبط له APP_NAME
     * كان يرسل إلى موكّليه باسمها. فما لم يكن اسماً حقيقياً فاسمُ
     * النظام أصدق منه.
     */
    public function test_the_default_framework_name_never_reaches_a_client(): void
    {
        Setting::set('office_name', '', 'general');
        config(['app.name' => 'Laravel']);

        $this->assertSame(MailIdentity::SYSTEM_NAME, MailIdentity::fromName());
        $this->assertStringNotContainsStringIgnoringCase('laravel', MailIdentity::fromName());
    }

    /** ورسائل النظام تُوقَّع باسم النظام مهما كان اسم المكتب. */
    public function test_system_mail_is_never_signed_with_the_office_name(): void
    {
        Setting::set('office_name', 'شركة حمد الريامي للمحاماة', 'general');

        $this->assertSame(MailIdentity::SYSTEM_NAME, MailIdentity::fromName(system: true));
    }

    // ─────────────────────────────────────────────── وجهة الردّ

    public function test_a_valid_office_email_becomes_the_reply_destination(): void
    {
        Setting::set('office_email', 'office@example.om', 'general');

        $this->assertSame('office@example.om', MailIdentity::replyTo());
    }

    public function test_a_malformed_office_email_is_refused_not_sent(): void
    {
        Setting::set('office_email', 'ليس بريدًا', 'general');

        $this->assertNull(MailIdentity::replyTo());
    }

    // ─────────────────────────────────────────────── الثغرات

    public function test_a_missing_office_name_is_reported_as_an_issue(): void
    {
        Setting::set('office_name', '', 'general');
        Setting::set('office_email', 'office@example.om', 'general');

        $keys = array_column(MailIdentity::identityIssues(), 'key');

        $this->assertContains('office_name', $keys);
        $this->assertNotContains('office_email', $keys);
    }

    public function test_a_missing_office_email_is_reported_as_an_issue(): void
    {
        Setting::set('office_name', 'مكتب', 'general');
        Setting::set('office_email', '', 'general');

        $issues = MailIdentity::identityIssues();

        $this->assertSame(['office_email'], array_column($issues, 'key'));
        $this->assertStringContainsString('الصندوقَ المركزي', $issues[0]['text']);
    }

    public function test_a_malformed_office_email_is_reported_too(): void
    {
        Setting::set('office_name', 'مكتب', 'general');
        Setting::set('office_email', 'office@@example', 'general');

        $issues = MailIdentity::identityIssues();

        $this->assertSame(['office_email'], array_column($issues, 'key'));
        $this->assertStringContainsString('غير صالح', $issues[0]['text']);
    }

    public function test_a_complete_identity_reports_nothing(): void
    {
        Setting::set('office_name', 'مكتب', 'general');
        Setting::set('office_email', 'office@example.om', 'general');

        $this->assertSame([], MailIdentity::identityIssues());
    }

    // ─────────────────────────────────────────────── الشاشة لا تكذب

    /**
     * ما تعرضه الشاشة هو ما يخرج في الترويسة حرفاً بحرف.
     *
     * وهذا هو الحارس الحقيقي: شاشةٌ تعِد بشيءٍ وبريدٌ يخرج بغيره أسوأ
     * من شاشةٍ لا تعرض شيئاً — الأولى تُطمئن على غير أساس.
     */
    public function test_what_the_screen_shows_is_what_the_envelope_carries(): void
    {
        Setting::set('office_name', 'شركة حمد الريامي للمحاماة', 'general');
        Setting::set('office_email', 'office@example.om', 'general');
        config(['mail.from.address' => 'mudawalah@gmail.com']);

        $shown = MailIdentity::clientSees();
        $envelope = $this->mail()->envelope();

        $this->assertSame($shown['name'], $envelope->from->name);
        $this->assertSame($shown['address'], $envelope->from->address);
        $this->assertSame($shown['replyTo'], $envelope->replyTo[0]->address);
    }

    /** وبلا بريدٍ للمكتب لا Reply-To أصلاً — لا عنوانٌ خاطئ. */
    public function test_without_an_office_email_the_envelope_carries_no_reply_to(): void
    {
        Setting::set('office_email', '', 'general');

        $this->assertNull(MailIdentity::clientSees()['replyTo']);
        $this->assertSame([], $this->mail()->envelope()->replyTo);
    }

    // ─────────────────────────────────────────────── شاشة الإعدادات

    public function test_the_settings_screen_warns_when_replies_would_be_lost(): void
    {
        Setting::set('office_name', 'مكتب', 'general');
        Setting::set('office_email', '', 'general');

        $this->actingAs($this->admin())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('الصندوق المركزي — لا يراه أحدٌ في مكتبك')
            ->assertSee('وصل ردُّه الصندوقَ المركزي');
    }

    public function test_the_settings_screen_shows_the_live_reply_destination(): void
    {
        Setting::set('office_name', 'شركة حمد الريامي للمحاماة', 'general');
        Setting::set('office_email', 'office@example.om', 'general');

        $this->actingAs($this->admin())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('office@example.om')
            ->assertSee('شركة حمد الريامي للمحاماة')
            ->assertDontSee('الصندوق المركزي — لا يراه أحدٌ في مكتبك');
    }

    /** وحفظُ الحقل يغيّر الترويسة فعلاً — لا الشاشة وحدها. */
    public function test_saving_the_office_email_changes_the_next_envelope(): void
    {
        Setting::set('office_email', '', 'general');
        $this->assertSame([], $this->mail()->envelope()->replyTo);

        $this->actingAs($this->admin())
            ->put(route('settings.update'), [
                'office_name' => 'شركة حمد الريامي للمحاماة',
                'office_email' => 'reply@example.om',
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertSame('reply@example.om', $this->mail()->envelope()->replyTo[0]->address);
        $this->assertSame('شركة حمد الريامي للمحاماة', $this->mail()->envelope()->from->name);
    }
}
