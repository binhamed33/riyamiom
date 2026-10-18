<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * سجلُّ التدقيق يقول أفعالَه بالعربيّة — لا بمفاتيحها.
 *
 * ═══ ما وقع ═══
 *
 * ‏A5  «النشاط الأخير» يقرأ «client portal link opened موكّل «سالم…»» من
 *      «نظام»، وشارةُ سجلّ العمليات «client_portal_link_opened» وتفاصيلُه
 *      «target: home»؛ والمعاينةُ «preview مستند «…»» و«file_path: documents/…».
 * ‏A20 مرشّحُ «النموذج» يبحث بـLIKE في اسم الصنف والعمودُ بالعربيّة: «مستند»
 *      لا يُرجع شيئاً و«Document» وحدها تعمل.
 */
class AuditLogArabicActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        app()->setLocale('ar');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'locale' => 'ar', 'name' => 'هلال']);
    }

    private function client(): Client
    {
        return Client::create(['name' => 'سالم بن حمد الزدجالي', 'type' => 'individual', 'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96891234567']);
    }

    /** كما يكتبه ClientLinkController: JSON نصّاً فوق صبّ «array». */
    private function portalLinkRow(Client $client, string $target): AuditLog
    {
        return AuditLog::create([
            'user_id' => null, 'action' => 'client_portal_link_opened',
            'model_type' => Client::class, 'model_id' => $client->id,
            'new_values' => json_encode(['target' => $target], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function test_a_portal_link_row_names_the_client_and_the_target(): void
    {
        $log = $this->portalLinkRow($this->client(), 'documents');

        $this->assertSame('فُتح رابط البوابة إلى المستندات', $log->describe());
        $this->assertSame('سالم بن حمد الزدجالي', $log->actorName(), 'الموكّلُ فتح الرابط لا «النظام»');
        $this->assertSame('فتح رابط البوابة', $log->actionLabel());
        $this->assertSame([['label' => 'الوجهة', 'value' => 'المستندات']], $log->details());
    }

    public function test_preview_download_export_and_backup_rows_read_in_arabic(): void
    {
        $preview = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'preview', 'model_type' => Document::class, 'model_id' => 9, 'new_values' => ['file_path' => 'documents/xyz.pdf', 'title' => 'عقد الإيجار']]);
        $download = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'download', 'model_type' => Document::class, 'model_id' => 9, 'new_values' => ['title' => 'عقد الإيجار']]);
        $export = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'create', 'model_type' => null, 'new_values' => ['action' => 'export', 'type' => 'cases']]);
        $backup = AuditLog::create(['user_id' => null, 'action' => 'backup_created', 'model_type' => 'System', 'new_values' => json_encode(['filename' => 'latest.zip', 'size_mb' => 3.2])]);
        $appointment = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'created', 'model_type' => \App\Models\Appointment::class, 'model_id' => 1, 'new_values' => ['title' => 'لقاء الموكّل']]);

        $this->assertSame('عُرض مستند «عقد الإيجار»', $preview->describe());
        $this->assertSame('معاينة', $preview->actionLabel());
        $this->assertNotContains('file_path', array_column($preview->details(), 'label'), 'مسارُ التخزين في التفاصيل');
        $this->assertSame('نُزّل مستند «عقد الإيجار»', $download->describe());
        $this->assertSame('صُدِّرت القضايا', $export->describe());
        $this->assertSame('تصدير', $export->actionLabel());
        // كانت التفاصيلُ «النوع: cases» بمفتاحها؛ و«action» قيلت في الشارة
        $this->assertSame([['label' => 'النوع', 'value' => 'القضايا']], $export->details());
        $this->assertSame('أُنشئت نسخة احتياطية', $backup->describe());
        $this->assertSame('أُنشئ موعد «لقاء الموكّل»', $appointment->describe(), 'المواعيدُ تكتب «created» فكانت تُقرأ «created موعد»');

        // وما سواها ممّا له اسمٌ في lang يُقال باسمه لا بمفتاحه
        $synced = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'whatsapp_templates_synced', 'model_type' => null, 'new_values' => json_encode(['count' => 3])]);
        $this->assertSame('مزامنة القوالب', $synced->describe());
    }

    public function test_the_dashboard_and_audit_page_show_no_raw_action_keys(): void
    {
        $this->portalLinkRow($this->client(), 'home');

        $dash = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('فُتح رابط البوابة إلى الصفحة الرئيسة', $dash);
        $this->assertStringNotContainsString('client portal link opened', $dash);

        $page = $this->actingAs($this->admin)->get(route('audit-log.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('client_portal_link_opened', $page);
        $this->assertStringNotContainsString('target:', $page);
        $this->assertStringContainsString('فتح رابط البوابة', $page);
        $this->assertStringContainsString('سالم بن حمد الزدجالي', $page);
    }

    public function test_the_model_filter_is_a_list_of_arabic_names_and_matches(): void
    {
        $client = $this->client();
        AuditLog::create(['user_id' => $this->admin->id, 'action' => 'create', 'model_type' => Document::class, 'model_id' => 1, 'new_values' => ['title' => 'مذكرة الدفاع']]);
        AuditLog::create(['user_id' => $this->admin->id, 'action' => 'create', 'model_type' => Client::class, 'model_id' => $client->id, 'new_values' => ['name' => $client->name]]);

        $page = $this->actingAs($this->admin)->get(route('audit-log.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<select id="audit-model" name="model"', $page);
        $this->assertStringContainsString('value="' . e(Document::class) . '"', $page);

        // بالصنف من القائمة
        $byClass = $this->actingAs($this->admin)->get(route('audit-log.index', ['model' => Document::class]))->getContent();
        $this->assertStringContainsString('مذكرة الدفاع', $byClass);
        $this->assertStringNotContainsString('أُنشئ موكّل', $byClass);

        // وبالاسم العربيّ الذي يراه في العمود — كان لا يُرجع شيئاً
        $byNoun = $this->actingAs($this->admin)->get(route('audit-log.index', ['model' => 'مستند']))->getContent();
        $this->assertStringContainsString('مذكرة الدفاع', $byNoun);
        $this->assertStringNotContainsString('سالم بن حمد الزدجالي', $byNoun);
    }

    /**
     * صفوفُ الاشتراك تقول ما جرى.
     *
     * DeveloperSubscriptionController يكتب «create/update» على Setting وفعلَه في
     * ‎new_values.action‎. كان actionKey() يأخذه لما بلا صنفٍ وحده، و«action»
     * مخفيّةٌ من التفاصيل لكلّ صفّ — فقرأ المديرُ «عُدِّل إعداد — الحالة: suspended»
     * بلا كلمةٍ عن الاشتراك.
     */
    public function test_subscription_rows_name_their_operation(): void
    {
        $activate = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'create', 'model_type' => Setting::class, 'new_values' => ['action' => 'activate_subscription', 'duration' => 12, 'start_at' => '2026-10-18 09:00:00', 'end_at' => '2027-10-18 09:00:00']]);
        $suspend = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'update', 'model_type' => Setting::class, 'new_values' => ['action' => 'suspend_subscription', 'status' => 'suspended']]);
        $reactivate = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'update', 'model_type' => Setting::class, 'new_values' => ['action' => 'reactivate_subscription', 'status' => 'active']]);

        $this->assertSame('فُعِّل اشتراك النظام', $activate->describe());
        $this->assertSame('تفعيل الاشتراك', $activate->actionLabel());
        $this->assertSame(['المدة', 'البداية', 'النهاية'], array_column($activate->details(), 'label'));
        $this->assertSame('أُوقف اشتراك النظام', $suspend->describe());
        $this->assertSame('إيقاف الاشتراك', $suspend->actionLabel());
        $this->assertSame('أُعيد تفعيل اشتراك النظام', $reactivate->describe());

        $page = $this->actingAs($this->admin)->get(route('audit-log.index'))->assertOk()->getContent();
        $this->assertStringContainsString('إيقاف الاشتراك', $page);
        $this->assertStringContainsString('تفعيل الاشتراك', $page);

        // و«action» لا تُخفى إلا حين تأخذها الشارة: فعلٌ داخل القيم لا تعرفه يبقى مقروءاً
        $other = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'update', 'model_type' => Client::class, 'model_id' => 1, 'new_values' => ['action' => 'merge_duplicates', 'name' => 'سالم']]);
        $this->assertSame('update', $other->actionKey());
        $this->assertContains(['label' => 'الإجراء', 'value' => 'merge_duplicates'], $other->details());
    }

    /**
     * مرشّحُ «الإجراء» يتّفق مع الشارة.
     *
     * كان ‎where('action', …)‎ والشارةُ actionKey(): «إنشاء» يُرجع صفوفاً شارتُها
     * «تصدير» و«نسخ احتياطي»، ويُسقط المواعيدَ المكتوبة «created» وشارتُها «إنشاء».
     */
    public function test_the_action_filter_matches_what_the_badge_says(): void
    {
        $doc = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'create', 'model_type' => Document::class, 'model_id' => 1, 'new_values' => ['title' => 'مذكرة الدفاع']]);
        $appointment = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'created', 'model_type' => \App\Models\Appointment::class, 'model_id' => 1, 'new_values' => ['title' => 'لقاء الموكّل']]);
        $export = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'create', 'model_type' => null, 'new_values' => ['action' => 'export', 'type' => 'cases']]);
        $backup = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'create', 'model_type' => null, 'new_values' => ['action' => 'backup_created', 'filename' => 'manual-1.zip']]);
        $suspend = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'update', 'model_type' => Setting::class, 'new_values' => ['action' => 'suspend_subscription', 'status' => 'suspended']]);
        $edit = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'updated', 'model_type' => \App\Models\Appointment::class, 'model_id' => 1, 'new_values' => ['title' => 'لقاء مؤجّل']]);
        $bare = AuditLog::create(['user_id' => $this->admin->id, 'action' => 'update', 'model_type' => Client::class, 'model_id' => 1, 'new_values' => null]);
        // ‏new_values نصُّ JSON فوق صبّ «array» (البوابة): ‏json_extract عليه NULL لا خطأ
        $portal = $this->portalLinkRow($this->client(), 'home');

        $ids = fn (string $key) => AuditLog::query()->actionIs($key)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$doc->id, $appointment->id], $ids('create'));
        $this->assertSame([$edit->id, $bare->id], $ids('update'));
        $this->assertSame([$export->id], $ids('export'));
        $this->assertSame([$backup->id], $ids('backup_created'));
        $this->assertSame([$suspend->id], $ids('suspend_subscription'));
        $this->assertSame([$portal->id], $ids('client_portal_link_opened'));

        // وكلُّ صفٍّ أرجعه المرشّحُ شارتُه ما طُلب
        foreach (['create', 'update', 'export', 'backup_created', 'suspend_subscription', 'client_portal_link_opened'] as $key) {
            foreach (AuditLog::query()->actionIs($key)->get() as $row) {
                $this->assertSame($key, $row->actionKey(), "المرشّحُ «{$key}» أرجع صفّاً شارتُه «{$row->actionKey()}»");
            }
        }

        $page = $this->actingAs($this->admin)->get(route('audit-log.index', ['action' => 'create']))->assertOk()->getContent();
        $this->assertStringContainsString('لقاء الموكّل', $page, 'المواعيدُ المكتوبة «created» سقطت من «إنشاء»');
        $this->assertStringContainsString('مذكرة الدفاع', $page);
        $this->assertStringNotContainsString('manual-1.zip', $page, '«إنشاء» أرجع صفَّ نسخٍ احتياطي');
        $this->assertSame(2, substr_count($page, '<tr class="hover:bg-gray-50 transition">'), '«إنشاء» أرجع صفَّ تصديرٍ أو نسخ');
    }

    /** أسماءُ موكّلي «فتح رابط البوابة» باستعلامٍ واحد لا واحدٍ لكلّ صفّ. */
    public function test_portal_link_actors_are_loaded_in_one_query(): void
    {
        $clients = collect(range(1, 6))->map(fn ($i) => Client::create(['name' => 'موكّل ' . $i, 'type' => 'individual', 'national_id' => (string) (5000000 + $i), 'phone' => '9689000000' . $i]));
        $clients->each(fn (Client $c) => $this->portalLinkRow($c, 'home'));

        $clientQueries = function (callable $run): int {
            \Illuminate\Support\Facades\DB::flushQueryLog();
            \Illuminate\Support\Facades\DB::enableQueryLog();
            $run();
            $log = \Illuminate\Support\Facades\DB::getQueryLog();
            \Illuminate\Support\Facades\DB::disableQueryLog();

            // بحثٌ بالمعرّف وحده — شريطُ «يحتاج انتباهك» في القالب يسأل الموكّلين لشأنٍ آخر
            return collect($log)->filter(fn ($q) => preg_match('/from\s+["`]?clients["`]?\s+where\s+["`]?clients["`]?\.["`]?id["`]?\s*(=|in\b)/i', $q['query']))->count();
        };

        $page = '';
        $count = $clientQueries(function () use (&$page) {
            $page = $this->actingAs($this->admin)->get(route('audit-log.index'))->assertOk()->getContent();
        });
        $this->assertSame(1, $count, 'استعلامٌ عن الموكّلين لكلّ صفّ');
        foreach ($clients as $c) {
            $this->assertStringContainsString($c->name, $page);
        }

        // ومحمَّلةً سلفاً لا يسأل actorName() شيئاً
        $logs = AuditLog::query()->where('action', 'client_portal_link_opened')->get();
        AuditLog::primeActorNames($logs);
        $this->assertSame(0, $clientQueries(fn () => $logs->each(fn (AuditLog $l) => $l->actorName())));
        $this->assertSame($clients->pluck('name')->all(), $logs->map->actorName()->all());
    }
}
