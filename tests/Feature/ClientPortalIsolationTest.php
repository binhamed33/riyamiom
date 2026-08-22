<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Setting;
use App\Models\User;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * العزل بين العملاء داخل المكتب الواحد.
 *
 * ملاحظة معمارية: العزل بين المكاتب أعلى من هذه الطبقة — كل مكتب في
 * مُداوَلة نسخة مستقلة بقاعدة بيانات ومفتاح تشفير وتخزين خاص به، ولا
 * يوجد جدول عملاء مشترك. فقضية مكتب آخر ليست صفّاً محجوباً في هذا
 * الجدول، بل غير موجودة فيه أصلاً. وهذه الاختبارات تغطّي ما يمكن أن
 * يقع فعلاً: عميل يحاول بلوغ بيانات عميل آخر في المكتب نفسه.
 */
class ClientPortalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Client $me;
    private Client $other;
    private LegalCase $myCase;
    private LegalCase $otherCase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('client-portal:lookup:127.0.0.1');

        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        Setting::set(ClientPortal::KEY_SHOW_DOCUMENTS, '1', 'client_portal');

        $lawyer = User::factory()->create(['role' => 'lawyer']);

        $this->me = Client::factory()->create([
            'name' => 'عميل أول', 'national_id' => '1111111111', 'phone' => '+968 90000111',
        ]);
        $this->other = Client::factory()->create([
            'name' => 'عميل ثانٍ', 'national_id' => '2222222222', 'phone' => '+968 90000222',
        ]);

        $this->myCase = LegalCase::factory()->create([
            'client_id' => $this->me->id, 'lawyer_id' => $lawyer->id, 'title' => 'قضيتي أنا',
        ]);
        $this->otherCase = LegalCase::factory()->create([
            'client_id' => $this->other->id, 'lawyer_id' => $lawyer->id, 'title' => 'قضية غيري',
        ]);
    }

    private function signInAsMe(): void
    {
        $this->post(route('client.access.lookup'), ['national_id' => '1111111111']);
        $this->post(route('client.access.verify'), ['digits' => '111']);
        $this->assertSame($this->me->id, session('client_access_id'));
    }

    public function test_a_client_only_sees_their_own_cases(): void
    {
        $this->signInAsMe();

        $this->get(route('client.portal.cases'))
            ->assertOk()
            ->assertSee('قضيتي أنا')
            ->assertDontSee('قضية غيري');
    }

    /** IDOR: تغيير معرّف القضية في الرابط لا يفتح قضية غيره */
    public function test_another_clients_case_is_not_reachable_by_id(): void
    {
        $this->signInAsMe();

        $this->get(route('client.portal.case', $this->myCase->id))->assertOk()->assertSee('قضيتي أنا');
        $this->get(route('client.portal.case', $this->otherCase->id))->assertNotFound();

        // ولا بمعرّف غير موجود أصلاً
        $this->get(route('client.portal.case', 999999))->assertNotFound();
    }

    public function test_another_clients_document_cannot_be_downloaded(): void
    {
        Storage::fake('private');

        $mine = Document::factory()->create([
            'case_id' => $this->myCase->id,
            'client_visible' => true,
            'access_level' => Document::ACCESS_ALL,
            'file_path' => 'mine.pdf',
        ]);
        $theirs = Document::factory()->create([
            'case_id' => $this->otherCase->id,
            'client_visible' => true,
            'access_level' => Document::ACCESS_ALL,
            'file_path' => 'theirs.pdf',
        ]);

        Storage::disk('private')->put('mine.pdf', 'my content');
        Storage::disk('private')->put('theirs.pdf', 'their content');

        $this->signInAsMe();

        $this->get(route('client.portal.document', $mine->id))->assertOk();
        $this->get(route('client.portal.document', $theirs->id))->assertNotFound();
    }

    /** لا يكفي إخفاء الزر: المستند غير المعلَّم للعميل يُرفض في الخادم */
    public function test_a_document_not_marked_for_the_client_is_refused(): void
    {
        Storage::fake('private');

        $hidden = Document::factory()->create([
            'case_id' => $this->myCase->id,
            'client_visible' => false,
            'access_level' => Document::ACCESS_ALL,
            'file_path' => 'hidden.pdf',
            'title' => 'مذكرة داخلية',
        ]);
        Storage::disk('private')->put('hidden.pdf', 'internal');

        $this->signInAsMe();

        $this->get(route('client.portal.document', $hidden->id))->assertNotFound();
        $this->get(route('client.portal.case', $this->myCase->id))
            ->assertOk()
            ->assertDontSee('مذكرة داخلية');
    }

    /** المستند الخاص محجوب مهما كان العلَم — حزام وحمّالة */
    public function test_a_private_document_stays_hidden_even_if_flagged(): void
    {
        Storage::fake('private');

        $private = Document::factory()->create([
            'case_id' => $this->myCase->id,
            'client_visible' => true,
            'access_level' => Document::ACCESS_PRIVATE,
            'file_path' => 'private.pdf',
            'title' => 'رأي المحامي الخاص',
        ]);
        Storage::disk('private')->put('private.pdf', 'secret');

        $this->signInAsMe();

        $this->get(route('client.portal.document', $private->id))->assertNotFound();
        $this->get(route('client.portal.case', $this->myCase->id))
            ->assertOk()
            ->assertDontSee('رأي المحامي الخاص');
    }

    /** إطفاء المستندات من الإعدادات يغلقها كلها فوراً */
    public function test_turning_documents_off_closes_them_all(): void
    {
        Storage::fake('private');

        $doc = Document::factory()->create([
            'case_id' => $this->myCase->id,
            'client_visible' => true,
            'access_level' => Document::ACCESS_ALL,
            'file_path' => 'doc.pdf',
        ]);
        Storage::disk('private')->put('doc.pdf', 'content');

        $this->signInAsMe();
        $this->get(route('client.portal.document', $doc->id))->assertOk();

        Setting::set(ClientPortal::KEY_SHOW_DOCUMENTS, '0', 'client_portal');

        $this->get(route('client.portal.document', $doc->id))->assertNotFound();
    }

    public function test_another_clients_sessions_never_appear(): void
    {
        Session::factory()->create([
            'case_id' => $this->otherCase->id,
            'date' => now()->addDays(3),
            'status' => 'upcoming',
            'location' => 'محكمة الخصم السرية',
        ]);
        Session::factory()->create([
            'case_id' => $this->myCase->id,
            'date' => now()->addDays(5),
            'status' => 'upcoming',
            'location' => 'محكمتي',
        ]);

        $this->signInAsMe();

        $this->get(route('client.portal.home'))
            ->assertOk()
            ->assertSee('محكمتي')
            ->assertDontSee('محكمة الخصم السرية');
    }

    public function test_the_summary_counts_only_the_clients_own_cases(): void
    {
        LegalCase::factory()->count(3)->create(['client_id' => $this->other->id]);

        $this->signInAsMe();

        $gateway = \App\Services\ClientPortal\ClientCaseGateway::for($this->me->fresh());
        $summary = $gateway->summary();

        $this->assertSame(1, $summary['total']);
    }

    public function test_a_guest_reaches_no_portal_page(): void
    {
        foreach ([
            route('client.portal.home'),
            route('client.portal.cases'),
            route('client.portal.case', $this->myCase->id),
            route('client.portal.account'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('client.access'));
        }
    }

    /** العميل قارئ فقط: لا مسار كتابة في البوابة إطلاقاً */
    public function test_the_portal_exposes_no_write_route_to_the_client(): void
    {
        $writable = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'client.portal.'))
            ->filter(fn ($r) => array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))
            ->map(fn ($r) => $r->getName())
            ->values()
            ->all();

        $this->assertSame([], $writable, 'بوابة العميل يجب أن تبقى للقراءة فقط.');
    }
}
