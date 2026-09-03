<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * المكتب الجديد.
 *
 * التجهيز على السيرفر يشغّل الهجرات فقط ولا يزرع بيانات، فقاعدة المكتب
 * الجديد فارغة. وهذه الاختبارات تثبت أن البوابة تعمل عنده من اللحظة
 * الأولى بلا خطوة يدوية، وأن افتراضاتها آمنة، وأنها تبدأ خالية تماماً.
 */
class ClientPortalNewOfficeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('client-portal:lookup:127.0.0.1');
    }

    /** بلا أي صفّ إعدادات: البوابة تعمل، والمستندات مغلقة */
    public function test_a_brand_new_office_gets_a_working_portal_with_safe_defaults(): void
    {
        // المكتب الجديد لا يحمل أي إعداد للبوابة — الافتراضات وحدها تحكم
        $this->assertSame(
            0,
            Setting::where('key', 'like', 'client_portal%')->count(),
            'المكتب الجديد يبدأ بلا إعدادات بوابة.'
        );

        $this->assertTrue(ClientPortal::enabled(), 'البوابة يجب أن تعمل تلقائياً.');
        $this->assertTrue(ClientPortal::showsSessions());
        $this->assertTrue(ClientPortal::showsTimeline());

        // الخصوصية تبدأ مغلقة
        $this->assertFalse(ClientPortal::showsDocuments(), 'المستندات يجب أن تبدأ مغلقة.');
        $this->assertFalse(ClientPortal::showsOpponent(), 'بيانات الخصم يجب أن تبدأ مغلقة.');

        $this->get(route('client.access'))->assertOk();
    }

    public function test_a_new_office_portal_starts_completely_empty(): void
    {
        $this->assertSame(0, Client::count());
        $this->assertSame(0, LegalCase::count());
        $this->assertSame(0, Document::count());

        // ولا يدخل أحد لأنه لا يوجد أحد. والخطوةُ الأولى لا تقول ذلك:
        // ردُّها واحدٌ عرف المكتبُ الهويّةَ أم لم يعرفها — السقوطُ عند
        // التحقّق (انظر ClientPortalAuthTest).
        $this->post(route('client.access.lookup'), ['national_id' => '1234567890']);
        $this->post(route('client.access.verify'), ['digits' => '123'])
            ->assertSessionHas('portal_error', __('portal.login.failed'));

        $this->assertNull(session('client_access_id'));
    }

    /** أول عميل يسجّله المكتب يستطيع الدخول فوراً — البصمة تُحسب عند الحفظ */
    public function test_the_first_client_the_office_creates_can_sign_in_at_once(): void
    {
        $client = Client::create([
            'name' => 'أول عميل',
            'type' => 'individual',
            'national_id' => '7788990011',
            'phone' => '+968 95556677',
        ]);

        $this->assertNotNull($client->fresh()->national_id_hash, 'البصمة تُحسب لحظة الحفظ.');

        $this->post(route('client.access.lookup'), ['national_id' => '7788990011']);
        $this->post(route('client.access.verify'), ['digits' => '677'])
            ->assertRedirect(route('client.portal.home'));

        $this->get(route('client.portal.home'))->assertOk()->assertSee('أول عميل');
    }

    /** تعديل رقم الهوية يُحدّث البصمة فلا يبقى الدخول على الرقم القديم */
    public function test_changing_a_national_id_moves_the_login_with_it(): void
    {
        $client = Client::create([
            'name' => 'عميل',
            'type' => 'individual',
            'national_id' => '1111000011',
            'phone' => '+968 91110000',
        ]);
        $oldHash = $client->fresh()->national_id_hash;

        $client->update(['national_id' => '2222000022']);
        $newHash = $client->fresh()->national_id_hash;

        $this->assertNotSame($oldHash, $newHash);
        $this->assertSame(Client::hashNationalId('2222000022'), $newHash);

        // الرقم القديم لم يعد يفتح شيئاً — يمضي كأيّ مجهولٍ ويسقط
        $this->post(route('client.access.lookup'), ['national_id' => '1111000011']);
        $this->post(route('client.access.verify'), ['digits' => '567'])
            ->assertSessionHas('portal_error', __('portal.login.failed'));

        $this->assertNull(session('client_access_id'));
    }

    /** حفظ العميل مراراً لا يُفسد البصمة رغم إعادة التشفير في كل حفظ */
    public function test_the_hash_survives_repeated_saves(): void
    {
        $client = Client::create([
            'name' => 'عميل',
            'type' => 'individual',
            'national_id' => '3333000033',
            'phone' => '+968 93330000',
        ]);
        $expected = Client::hashNationalId('3333000033');

        foreach (range(1, 3) as $i) {
            $client->update(['name' => 'عميل ' . $i]);
        }

        $this->assertSame($expected, $client->fresh()->national_id_hash);

        $this->post(route('client.access.lookup'), ['national_id' => '3333000033']);
        $this->post(route('client.access.verify'), ['digits' => '000'])
            ->assertRedirect(route('client.portal.home'));
    }
}
