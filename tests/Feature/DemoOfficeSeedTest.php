<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\CaseActivity;
use App\Models\CaseFolder;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\Document;
use App\Models\FinanceInvoice;
use App\Models\FinanceTransaction;
use App\Models\HrAttendance;
use App\Models\HrSalary;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * مكتبُ العرض يُبذر حيث سُمّي — وحيث سُمّي فقط.
 *
 * الحارسُ أهمُّ من البيانات: أمرٌ يبذر قضايا ومستخدمين وفواتير على
 * مكتبٍ حقيقي بالخطأ كارثةٌ لا تُمحى. فالرفضُ يُختبر قبل البذر.
 */
class DemoOfficeSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        config()->set('app.url', 'https://testrer.riyami.om');
    }

    public function test_it_refuses_without_a_named_site(): void
    {
        $this->artisan('office:demo-seed')->assertFailed();

        $this->assertSame(0, LegalCase::count());
    }

    public function test_it_refuses_a_site_that_does_not_match_app_url(): void
    {
        $this->artisan('office:demo-seed', ['--site' => 'abdulrhman.riyami.om'])->assertFailed();

        $this->assertSame(0, LegalCase::count());
        $this->assertSame(0, User::count());
    }

    /** مكتبُ الوالد مرفوضٌ بالاسم — ولو طابق النطاقُ الرابطَ حرفياً. */
    public function test_the_protected_office_is_refused_even_when_it_matches(): void
    {
        config()->set('app.url', 'https://office.riyami.om');

        $this->artisan('office:demo-seed', ['--site' => 'office.riyami.om'])->assertFailed();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Client::count());
    }

    public function test_it_builds_a_linked_office_on_the_named_site(): void
    {
        Http::fake();
        Mail::fake();

        $this->artisan('office:demo-seed', [
            '--site' => 'testrer.riyami.om',
            '--cases' => 3,
            '--password' => 'Demo-Pass-2026',
            '--my-phone' => '96871730036',
        ])
            ->expectsOutputToContain('بوابة الموكّلين للعرض')
            ->assertSuccessful();

        // الطاقم بأدواره
        $this->assertSame(6, User::count());
        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame(2, User::where('role', 'lawyer')->count());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Demo-Pass-2026', User::first()->password));

        // الموكّلون، وهاتفُ العارض على البطل
        $this->assertSame(14, Client::count());
        $this->assertStringContainsString('71730036', (string) Client::first()->phone);

        // القضايا وما يدور حولها
        $this->assertSame(3, LegalCase::count());
        $this->assertGreaterThan(0, CourtSession::count());
        $this->assertGreaterThanOrEqual(1, CourtSession::where('status', 'upcoming')->count(), 'لا جلسةَ قادمة — التقويم فارغ');
        $this->assertGreaterThan(0, Task::count());
        $this->assertGreaterThan(0, CaseActivity::count());
        $this->assertSame(9, CaseFolder::count());

        // المستندات ملفاتٌ حقيقية على القرص لا صفوفٌ يتيمة
        $this->assertGreaterThanOrEqual(6, Document::count());
        Document::all()->each(function (Document $doc) {
            $this->assertTrue(Storage::disk('private')->exists($doc->file_path), 'مستند بلا ملف: ' . $doc->file_path);
            $this->assertGreaterThan(100, $doc->file_size);
        });
        $this->assertSame('pdf', Document::first()->file_type, 'لم يُولَّد PDF — سقط إلى نص');

        // المال
        $this->assertSame(3, FinanceInvoice::count());
        $this->assertGreaterThan(30, FinanceTransaction::count(), 'المصروفات الشهرية لم تُبذر');

        // الموارد البشرية
        $this->assertSame(6, HrSalary::count());
        $this->assertGreaterThan(60, HrAttendance::count());

        // الإعلانات والبوابة
        $this->assertSame(2, Announcement::count());
        $this->assertSame('1', (string) Setting::get(ClientPortal::KEY_ENABLED));

        // ولا إشعارَ خرج إلى موكّلٍ وهمي
        $this->assertSame(0, ClientNotification::count());
        Http::assertNothingSent();
        Mail::assertNothingSent();

        // والأرقامُ الوهمية مقيَّدةٌ «إيقاف المراسلة» — لو رُبط واتساب المكتب
        // غداً لا تصل رسالةٌ إلى غريبٍ صادف رقمُه رقماً مختلَقاً
        $this->assertSame(13, WhatsAppContact::whereNotNull('opted_out_at')->count());
        $heroWaId = WhatsAppContact::normalizeWaId((string) Client::first()->phone);
        $this->assertNull(WhatsAppContact::where('wa_id', $heroWaId)->value('opted_out_at'), 'هاتفُ العارض نفسُه قُيِّد — لن يرى الإشعار الذي جاء ليعرضه');
    }

    /** مكتبٌ بُذر قبل قاعدة التقييد يشملها بإعادة التشغيل — بلا تكرار. */
    public function test_rerunning_restricts_numbers_seeded_before_the_rule(): void
    {
        $args = ['--site' => 'testrer.riyami.om', '--cases' => 1, '--password' => 'x', '--my-phone' => '96871730036'];
        $this->artisan('office:demo-seed', $args)->assertSuccessful();

        // كأنّ التشغيلَ الأوّل جرى بنسخةٍ لا تعرف القاعدة
        WhatsAppContact::query()->delete();

        $this->artisan('office:demo-seed', $args)->assertSuccessful();
        $this->assertSame(13, WhatsAppContact::whereNotNull('opted_out_at')->count());
        $this->assertSame(14, Client::count());
    }

    /** التشغيلُ الثاني لا يكرّر ولا يحذف. */
    public function test_running_twice_adds_nothing(): void
    {
        $args = ['--site' => 'testrer.riyami.om', '--cases' => 2, '--password' => 'Demo-Pass-2026'];

        $this->artisan('office:demo-seed', $args)->assertSuccessful();
        $before = [User::count(), Client::count(), LegalCase::count(), CourtSession::count(), Document::count(), FinanceInvoice::count(), FinanceTransaction::count(), HrAttendance::count()];

        $this->artisan('office:demo-seed', $args)->assertSuccessful();
        $after = [User::count(), Client::count(), LegalCase::count(), CourtSession::count(), Document::count(), FinanceInvoice::count(), FinanceTransaction::count(), HrAttendance::count()];

        $this->assertSame($before, $after);
    }

    /** الاسمُ الوهميّ يُستبدل، واسمٌ اختاره صاحبُ الموقع يُترك. */
    public function test_a_chosen_office_name_is_left_alone(): void
    {
        Setting::set('office_name', 'مكتب الرستاق للمحاماة', 'office');

        $this->artisan('office:demo-seed', ['--site' => 'testrer.riyami.om', '--cases' => 1, '--password' => 'x'])->assertSuccessful();

        $this->assertSame('مكتب الرستاق للمحاماة', Setting::get('office_name'));
    }
}
