<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\ClientPortal\PortalLinks;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * عزلُ المكاتب، وصمتُ الواجهة عمّا لا يخصّ المكتب.
 *
 * ═══ كيف يقع العزل أصلاً ═══
 *
 * لا بعمودٍ في جدول ولا بترشيحٍ في استعلام، بل بالنشر: كلُّ مكتبٍ
 * نسخةُ تطبيقٍ وقاعدةُ بياناتٍ ومفتاحُ تشفيرٍ ونطاقٌ ومستخدمُ نظامٍ
 * مستقلّ. فلا استعلامَ في هذا الكود يستطيع أن يرى مكتباً آخر — لا
 * وجودَ له في قاعدته أصلاً.
 *
 * وهذه الاختباراتُ تحرس ما يمكن أن يُكسر رغم ذلك:
 *  · أن يبقى الكودُ خالياً من office_id يوهم بجدولٍ مشترك.
 *  · أن يكون سرُّ كلّ مكتبٍ مشفَّراً بمفتاحه هو — فصفٌّ يُنسخ لا يُفكّ.
 *  · أن تكون نسخةُ واتساب لكلّ مكتبٍ باسمٍ مشتقٍّ من نطاقه.
 *  · وأن يبقى الموكّل محصوراً في قضاياه.
 */
class OfficeIsolationTest extends TestCase
{
    use RefreshDatabase;

    // ══════════ عزلُ البنية ══════════

    /**
     * لا office_id في الكود — ولا يجوز أن يعود.
     *
     * عمودٌ كهذا يعني جدولاً مشتركاً بين المكاتب، وذاك يعني أنّ خطأً
     * واحداً في شرطِ استعلام يكشف قضايا مكتبٍ لمكتبٍ آخر. والعزلُ هنا
     * بالنشر لا بالشرط، فوجودُ العمود أصلاً غلطٌ في الفهم.
     */
    public function test_no_shared_office_column_ever_appears_in_the_code(): void
    {
        $offenders = [];

        foreach (['app', 'database/migrations'] as $dir) {
            foreach (File::allFiles(base_path($dir)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                if (preg_match("/['\"]office_id['\"]|->office_id\b/", (string) file_get_contents($file->getPathname()))) {
                    $offenders[] = str_replace(base_path() . '/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, "عمود office_id يوهم بجدولٍ مشترك:\n" . implode("\n", $offenders));
    }

    /**
     * سرُّ المكتب مشفَّرٌ بمفتاحه هو.
     *
     * فصفٌّ نُسخ إلى قاعدة مكتبٍ آخر — أو نسخةٌ احتياطيّة وُضعت في
     * المكان الخطأ — لا يُفكّ عنده.
     */
    public function test_a_secret_copied_to_another_office_cannot_be_read(): void
    {
        $plain = 'EAA-secret-of-office-one-0123456789';
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString($plain), 'whatsapp');

        $stored = (string) Setting::get(WhatsAppSettings::KEY_TOKEN);

        $this->assertNotSame($plain, $stored, 'خُزّن الرمز صريحاً');
        $this->assertSame($plain, WhatsAppSettings::accessToken());

        // مكتبٌ آخر = مفتاحُ تطبيقٍ آخر. والواجهةُ تحتفظ بنسخةٍ
        // محلولة، فلا يكفي نسيانُ الرباط وحده.
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->app->forgetInstance('encrypter');
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('encrypter');

        $this->assertNull(WhatsAppSettings::accessToken(), 'فُكَّ سرُّ مكتبٍ بمفتاح مكتبٍ آخر');
    }

    /** ونسخةُ واتساب لكلّ مكتبٍ باسمٍ مشتقٍّ من نطاقه — لا تتصادم. */
    public function test_each_office_gets_its_own_whatsapp_instance_name(): void
    {
        config(['app.url' => 'https://office-one.riyami.om']);
        $first = WhatsAppSettings::evolutionInstance();

        Setting::where('key', WhatsAppSettings::KEY_EVO_INSTANCE)->delete();
        config(['app.url' => 'https://office-two.riyami.om']);

        $this->assertNotSame($first, WhatsAppSettings::evolutionInstance());
        $this->assertSame('office-one-riyami-om', $first);
    }

    /** وسرُّ ويبهوك كلّ مكتبٍ يخصّه: سرُّ غيره يُردّ. */
    public function test_one_offices_webhook_secret_does_not_open_another(): void
    {
        config(['whatsapp.default' => 'evolution']);

        $mine = WhatsAppSettings::evolutionSecret();
        $foreign = str_repeat('z', 40);

        $this->assertNotSame($mine, $foreign);

        $this->postJson('/webhooks/evolution/' . $foreign, ['event' => 'connection.update', 'data' => []])
            ->assertStatus(403);
    }

    // ══════════ عزلُ الموكّلين ══════════

    /** بوابةُ موكّلٍ لا تبلغ قضيّةَ غيره ولا رابطُه جلسةَ غيره. */
    public function test_a_client_portal_is_sealed_to_its_own_client(): void
    {
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $mine = Client::create(['name' => 'أ', 'phone' => '91111111', 'national_id' => '11111111', 'type' => 'individual']);
        $theirs = Client::create(['name' => 'ب', 'phone' => '92222222', 'national_id' => '22222222', 'type' => 'individual']);

        $theirCase = LegalCase::create([
            'case_number' => '2026/99', 'title' => 'قضية ب', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $theirs->id,
        ]);

        $link = PortalLinks::for($mine, 'home');
        preg_match('#/p/([A-Za-z0-9]+)#', $link, $m);
        $this->get('/p/' . $m[1]);

        $this->get(route('client.portal.case', $theirCase->id))->assertNotFound();
    }

    // ══════════ صمتُ الواجهة ══════════

    /**
     * لا تقول الواجهةُ لمدير المكتب ما لا يعنيه عن طريقة الربط.
     *
     * نصٌّ يشرح احتمالَ التقييد أو يشير إلى «الواجهة الرسمية» يُقرأ
     * على غير وجهه ويُنقل عنه، والحدودُ تعمل سواءٌ قُرئ أو لم يُقرأ.
     */
    public function test_the_settings_screen_says_nothing_about_how_the_link_works(): void
    {
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $html = $this->actingAs($admin)->get(route('settings.index'))->assertOk()->getContent();

        foreach ([
            'الواجهة الرسمية',
            'احتمال الحظر',
            'يخالف',
            'غير رسمي',
            'Evolution',
            'Baileys',
        ] as $revealing) {
            $this->assertStringNotContainsString(
                $revealing,
                $html,
                'الشاشة تكشف «' . $revealing . '» لمدير المكتب',
            );
        }
    }

    /** ولا تكشفها شاشةُ المحادثات ولا صفحةُ الموكّل. */
    public function test_no_other_office_screen_reveals_it_either(): void
    {
        Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '1', 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $contact = WhatsAppContact::create(['wa_id' => '96891234567']);
        WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
            'last_inbound_at' => now(),
        ]);

        $html = $this->actingAs($admin)->get(route('whatsapp.index'))->assertOk()->getContent();

        foreach (['Evolution', 'Baileys', 'الواجهة الرسمية', 'احتمال الحظر'] as $revealing) {
            $this->assertStringNotContainsString($revealing, $html);
        }
    }
}
