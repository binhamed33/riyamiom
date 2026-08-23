<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientPortal\ClientCaseGateway;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ما يفعله الموظّف يصل الموكّل.
 *
 * الشكوى كانت: «أضيف مستندًا أو جلسة أو إجراءً ولا تتحدّث بيانات
 * الموكّل». والسبب الجذري أن المسار الزمني في البوابة يقرأ
 * CaseActivity، ولم يكن أحد يكتب فيه عند إضافة جلسة أو رفع مستند أو
 * تغيير حالة القضية — يكتب فيه الإدخال اليدوي للإجراءات والأتمتة
 * والقوالب فقط.
 *
 * والتسجيل صار في مراقبي النماذج لا في المتحكّمات: هذه الاختبارات
 * تكتب عبر النموذج مباشرة، وهو أضعف الطرق. فإن نجحت هنا نجحت من
 * المتحكّم ومن الأتمتة ومن القالب ومن الاستيراد.
 */
class ClientTimelineSyncTest extends TestCase
{
    use RefreshDatabase;

    private LegalCase $case;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ClientPortal::KEY_ENABLED,
            ClientPortal::KEY_SHOW_TIMELINE,
            ClientPortal::KEY_SHOW_DOCUMENTS,
            ClientPortal::KEY_SHOW_SESSIONS,
        ] as $key) {
            Setting::set($key, '1', 'client_portal');
        }

        $this->client = Client::factory()->create();
        // الحالة مثبّتة عمداً: مصنع القضايا يختار حالةً عشوائية، فلو
        // وقعت على «won» صار تحديثُها إليها في الاختبار لا تغييراً
        // أصلاً — ويسقط الاختبار مرّةً كل ستّ مرّات بلا سبب ظاهر.
        $this->case = LegalCase::factory()->create([
            'client_id' => $this->client->id,
            'status' => LegalCase::STATUS_ACTIVE,
        ]);
    }

    private function timeline(): \Illuminate\Support\Collection
    {
        return (new ClientCaseGateway($this->client))->timelineFor($this->case);
    }

    public function test_a_new_session_appears_in_the_client_timeline(): void
    {
        $this->assertCount(0, $this->timeline());

        Session::create([
            'case_id' => $this->case->id,
            'date' => now()->addDays(10),
            'location' => 'محكمة مسقط الابتدائية',
            'status' => 'scheduled',
        ]);

        $timeline = $this->timeline();

        $this->assertCount(1, $timeline, 'إضافة جلسة لم تصل مسار الموكّل');
        $this->assertSame(CaseActivity::TYPE_SESSION, $timeline->first()->type);
        $this->assertStringContainsString('جلسة جديدة', $timeline->first()->title);
    }

    public function test_moving_a_session_tells_the_client_the_new_date(): void
    {
        $session = Session::create([
            'case_id' => $this->case->id,
            'date' => now()->addDays(10),
            'status' => 'scheduled',
        ]);

        $session->update(['date' => now()->addDays(20)]);

        $titles = $this->timeline()->pluck('title')->all();

        $this->assertContains('تغيّر موعد الجلسة', $titles);
        $this->assertStringContainsString(
            now()->addDays(20)->format('Y-m-d'),
            $this->timeline()->firstWhere('title', 'تغيّر موعد الجلسة')->content
        );
    }

    public function test_an_internal_note_on_a_session_stays_internal(): void
    {
        $session = Session::create([
            'case_id' => $this->case->id,
            'date' => now()->addDays(10),
            'status' => 'scheduled',
        ]);

        $before = $this->timeline()->count();
        $session->update(['notes' => 'الموكّل متردّد — لا تُبلغه بعد.']);

        $this->assertCount($before, $this->timeline(), 'ملاحظة داخلية سرّبت سطراً إلى الموكّل');
    }

    public function test_a_client_visible_document_appears_but_an_internal_one_does_not(): void
    {
        Document::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'صحيفة الدعوى',
            'client_visible' => true,
            'access_level' => Document::ACCESS_TEAM,
        ]);

        Document::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'مذكرة استراتيجية داخلية',
            'client_visible' => false,
        ]);

        $titles = $this->timeline()->pluck('content')->all();

        $this->assertContains('صحيفة الدعوى', $titles);
        $this->assertNotContains('مذكرة استراتيجية داخلية', $titles,
            'وجود المستند الداخلي أُعلن للموكّل');
    }

    public function test_sharing_an_internal_document_later_tells_the_client(): void
    {
        $doc = Document::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'الحكم الابتدائي',
            'client_visible' => false,
        ]);

        $this->assertCount(0, $this->timeline());

        $doc->update(['client_visible' => true, 'access_level' => Document::ACCESS_TEAM]);

        $timeline = $this->timeline();
        $this->assertCount(1, $timeline);
        $this->assertSame('أُتيح لك مستند', $timeline->first()->title);
    }

    public function test_a_status_change_reaches_the_client(): void
    {
        $this->assertSame(LegalCase::STATUS_ACTIVE, $this->case->status);

        $this->case->update(['status' => LegalCase::STATUS_WON]);

        $entry = $this->timeline()->firstWhere('type', CaseActivity::TYPE_STATUS);

        $this->assertNotNull($entry, 'تغيير حالة القضية لم يصل الموكّل');
        $this->assertStringContainsString('←', $entry->content);
    }

    public function test_internal_activity_types_never_reach_the_client(): void
    {
        foreach ([CaseActivity::TYPE_NOTE, CaseActivity::TYPE_CALL, CaseActivity::TYPE_TASK, CaseActivity::TYPE_PAYMENT] as $type) {
            CaseActivity::create([
                'case_id' => $this->case->id,
                'type' => $type,
                'title' => 'داخلي',
                'occurred_at' => now(),
            ]);
        }

        $this->assertCount(0, $this->timeline(), 'نوع داخلي ظهر في مسار الموكّل');
    }

    public function test_the_timeline_stays_empty_when_the_office_turns_it_off(): void
    {
        Session::create(['case_id' => $this->case->id, 'date' => now()->addDay(), 'status' => 'scheduled']);
        $this->assertCount(1, $this->timeline());

        Setting::set(ClientPortal::KEY_SHOW_TIMELINE, '0', 'client_portal');

        $this->assertCount(0, $this->timeline());
    }
}
