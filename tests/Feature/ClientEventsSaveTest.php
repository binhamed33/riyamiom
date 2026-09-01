<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use App\Support\ClientEvents;
use App\Support\ClientPortal;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * تشغيلُ إشعارات الموكّل يُشغّلها فعلاً — لا يمسحها.
 *
 * ═══ العطل الذي وُضع له هذا الملف ═══
 *
 * قال المكتب: «كلُّ شيءٍ مفعَّل ولا تصل الموكّلَ رسالة». وكان صادقاً،
 * وكانت الشاشةُ تكذب:
 *
 * الخاناتُ كانت تؤشَّر بـ‎enabled()، وهي تجيب «لا» عن كلّ نوعٍ ما دام
 * المفتاحُ الرئيسي مطفأً. فتُعرض العشرُ فارغةً على من لم يشغّل الميزة
 * بعد. فيؤشّر المفتاحَ الرئيسي ويحفظ — والخانةُ الفارغة لا تُرسَل في
 * HTML — فيقرأ المتحكّم «لم يختر شيئاً» ويكتب صفراً صريحاً على العشرة.
 *
 * فالمفتاحُ مضيء، والأنواعُ كلُّها مطفأةٌ صراحةً، والصفرُ الصريح يهزم
 * الافتراض. ولا إشعارَ يُقيَّد ولا رسالةَ تُصفّ ولا سطرَ في سجلّ.
 *
 * وهذا الاختبارُ يمثّل الرحلةَ كما يمشيها المديرُ حرفاً بحرف: يفتح
 * الشاشة، ويرسل ما فيها من خاناتٍ مؤشَّرة لا أكثر، ثمّ يفتح قضيةً.
 */
class ClientEventsSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $developer;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->developer = User::factory()->create(['role' => 'developer', 'is_active' => true]);

        Setting::set(WhatsAppSettings::KEY_TOKEN, Crypt::encryptString('EAA-token-for-testing-0123456789'), 'whatsapp');
        Setting::set(WhatsAppSettings::KEY_PHONE_ID, '111222333', 'whatsapp');
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');

        $this->client = Client::create([
            'name' => 'خالد بن ناصر الرواحي',
            'phone' => '91234567',
            'national_id' => '11223344',
            'type' => 'individual',
        ]);
    }

    /**
     * الخاناتُ المؤشَّرة في الصفحة كما يراها المتصفّح.
     *
     * @return array<int, string>
     */
    private function checkedTypes(string $html): array
    {
        $found = [];

        foreach (array_keys(ClientEvents::catalogue()) as $type) {
            // الخانةُ المؤشَّرة تحمل checked بين وسمِ الإدخال وقيمته
            if (preg_match('/<input[^>]*name="cn_evt\[\]"[^>]*value="' . preg_quote($type, '/') . '"[^>]*>/', $html, $m)
                && str_contains($m[0], 'checked')) {
                $found[] = $type;
            }
        }

        return $found;
    }

    // ══════════ الرحلة كما وقعت ══════════

    /** الشاشةُ تعرض الأنواعَ الافتراضية مؤشَّرةً ولو كان المفتاح مطفأً. */
    public function test_the_screen_shows_the_default_types_ticked_before_the_master_is_on(): void
    {
        $this->assertFalse(ClientEvents::masterEnabled(), 'المفتاحُ الرئيسي مطفأٌ افتراضاً');

        $html = $this->actingAs($this->developer)->get(route('settings.index'))->assertOk()->getContent();

        $this->assertContains(ClientEvents::CASE_CREATED, $this->checkedTypes($html),
            'خانةُ «قضيةٌ جديدة» غيرُ مؤشَّرة — وحفظُ الصفحة كما هي يمسحها');
    }

    /**
     * ═══ الاختبارُ الذي وُضع له كلُّ هذا ═══
     *
     * يشغّل المفتاحَ ويحفظ ما تعرضه الشاشةُ لا أكثر، ثمّ يفتح قضيةً:
     * فيصل الموكّلَ إشعار.
     */
    public function test_turning_the_master_on_from_the_screen_keeps_the_types_on(): void
    {
        Queue::fake();

        $html = $this->actingAs($this->developer)->get(route('settings.index'))->getContent();
        $checked = $this->checkedTypes($html);

        // ما يرسله المتصفّح: المؤشَّرُ وحده، والمفتاحُ الرئيسي الذي أشّره
        $this->actingAs($this->developer)->post(route('settings.whatsapp.update'), [
            'cn_section' => '1',
            'cn_enabled' => '1',
            'cn_evt' => $checked,
        ])->assertRedirect();

        $this->assertTrue(ClientEvents::masterEnabled());
        $this->assertTrue(ClientEvents::enabled(ClientEvents::CASE_CREATED),
            'شُغّل المفتاحُ فانطفأ النوع — وهو عينُ العطل');

        LegalCase::create([
            'case_number' => '2026/777', 'title' => 'قضية', 'type' => 'civil',
            'description' => 'وصف', 'court' => 'الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium', 'client_id' => $this->client->id,
        ]);

        $this->assertSame(1, ClientNotification::where('type', ClientEvents::CASE_CREATED)->count(),
            'فُتحت قضيةٌ ولم يُقيَّد إشعار');
    }

    /** ونموذجٌ لا يحمل القسمَ لا يبدّل شيئاً منه. */
    public function test_a_form_without_the_section_changes_no_toggle(): void
    {
        ClientEvents::setMasterEnabled(true);
        ClientEvents::setEnabled(ClientEvents::CASE_CREATED, true);

        $this->actingAs($this->developer)->post(route('settings.whatsapp.update'), [
            'wa_phone_number_id' => '111222333',
        ])->assertRedirect();

        $this->assertTrue(ClientEvents::masterEnabled(), 'حفظٌ لا يعرض القسمَ أطفأ المفتاح');
        $this->assertTrue(ClientEvents::enabled(ClientEvents::CASE_CREATED));
    }

    /** والإطفاءُ الصريح يبقى إطفاءً: المكتبُ صاحبُ القرار. */
    public function test_an_office_can_still_turn_a_single_type_off(): void
    {
        $this->actingAs($this->developer)->post(route('settings.whatsapp.update'), [
            'cn_section' => '1',
            'cn_enabled' => '1',
            'cn_evt' => [ClientEvents::CASE_CREATED],
        ])->assertRedirect();

        $this->assertTrue(ClientEvents::enabled(ClientEvents::CASE_CREATED));
        $this->assertFalse(ClientEvents::enabled(ClientEvents::SESSION_NEW));
    }

    /** وchosen تقرأ الاختيارَ ولو كان المفتاحُ مطفأً — enabled لا. */
    public function test_chosen_reads_the_stored_choice_independently_of_the_master(): void
    {
        ClientEvents::setEnabled(ClientEvents::CASE_CREATED, true);

        $this->assertTrue(ClientEvents::chosen(ClientEvents::CASE_CREATED));
        $this->assertFalse(ClientEvents::enabled(ClientEvents::CASE_CREATED), 'المفتاحُ الرئيسي مطفأ');
    }

    // ══════════ إصلاحُ ما مُسح قبل الإصلاح ══════════

    /** الهجرةُ تعيد الافتراضاتِ حين لا يكون نوعٌ واحدٌ مشغَّلاً. */
    public function test_the_repair_restores_defaults_when_every_type_was_wiped(): void
    {
        foreach (ClientEvents::types() as $type) {
            ClientEvents::setEnabled($type, false);
        }

        ClientEvents::setMasterEnabled(true);
        $this->assertFalse(ClientEvents::enabled(ClientEvents::CASE_CREATED));

        $this->repair();

        $this->assertTrue(ClientEvents::enabled(ClientEvents::CASE_CREATED),
            'لم تُعَد الافتراضاتُ بعد المسح');
        $this->assertFalse(ClientEvents::enabled(ClientEvents::DOCUMENT_NEW),
            'الاختياريُّ يبقى مطفأً — الافتراضُ لا يوسّع المراسلة');
    }

    /** ولا تلمس مكتباً فيه اختيارٌ حقيقي — نوعٌ واحدٌ مشغَّلٌ يكفي. */
    public function test_the_repair_leaves_a_real_choice_alone(): void
    {
        foreach (ClientEvents::types() as $type) {
            ClientEvents::setEnabled($type, false);
        }

        ClientEvents::setEnabled(ClientEvents::SESSION_NEW, true);
        ClientEvents::setMasterEnabled(true);

        $this->repair();

        $this->assertTrue(ClientEvents::enabled(ClientEvents::SESSION_NEW));
        $this->assertFalse(ClientEvents::enabled(ClientEvents::CASE_CREATED),
            'الهجرةُ ألغت إطفاءً اختاره المكتب');
    }


    // ══════════ القفل على المطوّر ══════════

    /**
     * مديرُ المكتب يرى القسمَ ولا يبدّله.
     *
     * ═══ ولماذا الحارسُ عند المتحكّم لا في الشاشة ═══
     *
     * الخانةُ المعطَّلة لا تُرسَل أصلاً، فلو كان الحارسُ في الشاشة
     * وحدها لكان حفظُ المدير يقرأ «لم يختر شيئاً» فيمسح العشرةَ —
     * وهو العطلُ نفسُه بابٍ آخر.
     */
    public function test_an_office_admin_cannot_change_the_section_and_cannot_wipe_it(): void
    {
        $this->actingAs($this->developer)->post(route('settings.whatsapp.update'), [
            'cn_section' => '1',
            'cn_enabled' => '1',
            'cn_evt' => [ClientEvents::CASE_CREATED, ClientEvents::SESSION_NEW],
        ])->assertRedirect();

        // المديرُ يحفظ الصفحةَ وقسمُها معطَّل: لا خانةَ تُرسَل
        $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), [
            'cn_section' => '1',
        ])->assertRedirect();

        $this->assertTrue(ClientEvents::masterEnabled(), 'حفظُ المدير أطفأ المفتاح');
        $this->assertTrue(ClientEvents::enabled(ClientEvents::CASE_CREATED), 'حفظُ المدير مسح الأنواع');
        $this->assertTrue(ClientEvents::enabled(ClientEvents::SESSION_NEW));
    }

    /** ولا يشغّلها بنموذجٍ مصنوعٍ باليد. */
    public function test_an_office_admin_cannot_turn_it_on_by_hand(): void
    {
        $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), [
            'cn_section' => '1',
            'cn_enabled' => '1',
            'cn_evt' => array_keys(ClientEvents::catalogue()),
        ])->assertRedirect();

        $this->assertFalse(ClientEvents::masterEnabled());
    }

    /**
     * والشاشةُ تقول للمدير إنّها ليست له — بلا حقلٍ واحدٍ يُرسله.
     *
     * ولا خانةٌ معطَّلة: المتصفّح يرسمها رماديةً مهما كانت مؤشَّرة،
     * فيبدو المشغَّلُ مطفأً. والقفلُ معنىً غيرُ الإطفاء.
     */
    public function test_the_screen_tells_the_admin_the_section_is_the_developers(): void
    {
        ClientEvents::setMasterEnabled(true);

        $html = $this->actingAs($this->admin)->get(route('settings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('إشعارات الموكّل يضبطها المطوّر', $html);

        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="cn_evt\[\]"/',
            $html,
            'حقلٌ يُرسله المديرُ في قسمٍ ليس له',
        );

        // والحالةُ مقروءةٌ رغم القفل: «قضيةٌ جديدة» مشغَّلة
        $this->assertStringContainsString('aria-label="مشغَّل"', $html);
    }

    /** والمطوّرُ يرى الخاناتِ حقيقيّةً يبدّلها. */
    public function test_the_developer_still_gets_real_checkboxes(): void
    {
        $html = $this->actingAs($this->developer)->get(route('settings.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="cn_evt\[\]"/', $html);
        $this->assertStringNotContainsString('إشعارات الموكّل يضبطها المطوّر', $html);
    }

    private function repair(): void
    {
        $migration = require database_path('migrations/2026_09_01_100005_repair_wiped_client_event_toggles.php');
        $migration->up();
    }
}
