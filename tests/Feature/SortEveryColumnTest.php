<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * كلُّ عمودٍ يُرتَّب بالنقر على ترويسته — لا التاريخُ وحدَه.
 *
 * ═══ ما كان ═══
 *
 * الجلساتُ تُرتَّب بالتاريخ والحالة فقط؛ و«الموكّل» و«الخصم»
 * و«المحكمة» ترويساتٌ ميّتة. والسببُ أنّ هذه الأعمدة تعيش في جدول
 * القضايا لا الجلسات، فلا يبلغها ORDER BY.
 *
 * ═══ ولماذا استعلامٌ مرتبطٌ لا ضمُّ جداول ═══
 *
 * الضمُّ (JOIN) يجعل «الحالة» و«المعرّف» و«تاريخ الإنشاء» أسماءً
 * ملتبسةً بين الجدولين، فتسقط كلُّ فلاتر الصفحة القائمة على
 * ambiguous column — إصلاحٌ يكسر ما كان يعمل. والاستعلامُ المرتبط
 * داخل ORDER BY لا يمسّ الاستعلامَ الأصليَّ بحرف.
 *
 * ═══ وما تحرسه هذه الاختبارات ═══
 *
 * ١) الترويسةُ تُنقر: لكلّ عمودٍ ذي معنًى رابطُ ترتيب.
 * ٢) والنقرةُ تفعل: الصفوفُ تُعاد فعلاً لا الرابطُ وحدَه يتغيّر.
 * ٣) والاتجاهُ ينقلب: صاعدٌ ثم نازلٌ يعطيان ترتيبين متعاكسين.
 * ٤) ولا يسقط شيءٌ مما كان: الفلاترُ تعمل مع الترتيب الجديد.
 */
class SortEveryColumnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function client(string $name, string $phone = '96891000000', string $email = null): Client
    {
        return Client::create([
            'name' => $name,
            'type' => 'individual',
            'national_id' => (string) random_int(1000000, 9999999),
            'phone' => $phone,
            'email' => $email,
        ]);
    }

    private function legalCase(Client $client, string $court, string $opponent, string $number): LegalCase
    {
        return LegalCase::create([
            'case_number' => $number, 'office_case_number' => $number,
            'title' => 'قضية ' . $number, 'description' => 'وصف',
            'type' => 'مدني', 'court' => $court, 'opponent' => $opponent,
            'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);
    }

    /**
     * ترتيبُ الأسماء كما ظهرت في صفوف الجدول — الأوّلُ أوّلاً.
     *
     * البحثُ يبدأ من <tbody> لا من رأس الصفحة: قوائمُ التصفية فوق
     * الجدول تحمل أسماءَ المحاكم مرتّبةً أبجدياً دائماً، فبحثٌ في
     * الصفحة كلّها يقرأ ترتيبَ القائمة لا ترتيبَ الجدول — واختبارٌ
     * يقرأ الشيءَ الخطأ ينجح على كودٍ معطوب.
     */
    private function orderOf(string $html, array $needles): array
    {
        $body = strpos($html, '<tbody');
        if ($body !== false) {
            $html = substr($html, $body);
        }

        $seen = [];
        foreach ($needles as $needle) {
            $at = strpos($html, $needle);
            if ($at !== false) {
                $seen[$at] = $needle;
            }
        }
        ksort($seen);

        return array_values($seen);
    }

    // ─────────────────────────────────────────────── الجلسات

    /** «الموكّل» و«المحكمة»: ترويساتٌ صارت تُرتِّب فعلاً. */
    public function test_sessions_sort_by_client_opponent_and_court(): void
    {
        $bClient = $this->client('باء الموكّل');
        $aClient = $this->client('ألف الموكّل');

        $bCase = $this->legalCase($bClient, 'محكمة ياء', 'خصمٌ ياء', 'س/2');
        $aCase = $this->legalCase($aClient, 'محكمة ألف', 'خصمٌ ألف', 'س/1');

        // التاريخُ معكوسٌ عمداً: لو بقي الترتيبُ بالتاريخ لظهر الخطأ
        CourtSession::create(['case_id' => $aCase->id, 'date' => now()->addDays(9), 'location' => 'ق1', 'status' => 'upcoming']);
        CourtSession::create(['case_id' => $bCase->id, 'date' => now()->addDays(2), 'location' => 'ق2', 'status' => 'upcoming']);

        foreach ([
            'client' => ['ألف الموكّل', 'باء الموكّل'],
            'court' => ['محكمة ألف', 'محكمة ياء'],
        ] as $key => $pair) {
            $asc = $this->actingAs($this->admin)
                ->get(route('sessions.index', ['sort' => $key, 'dir' => 'asc']))
                ->assertOk()->getContent();

            $this->assertSame($pair, $this->orderOf($asc, $pair), "الترتيبُ الصاعد بـ{$key} لم يقع");

            $desc = $this->actingAs($this->admin)
                ->get(route('sessions.index', ['sort' => $key, 'dir' => 'desc']))
                ->assertOk()->getContent();

            $this->assertSame(array_reverse($pair), $this->orderOf($desc, $pair), "النازلُ بـ{$key} لم ينقلب");
        }
    }

    /** والفلاترُ تبقى تعمل مع الترتيب الجديد — لا ambiguous column. */
    public function test_sessions_filters_survive_the_new_sorts(): void
    {
        $case = $this->legalCase($this->client('موكّل'), 'محكمة', 'خصم', 'س/9');
        CourtSession::create(['case_id' => $case->id, 'date' => now()->addDay(), 'location' => 'ق', 'status' => 'upcoming']);

        foreach (['client', 'court', 'date', 'status'] as $key) {
            $this->actingAs($this->admin)->get(route('sessions.index', [
                'sort' => $key,
                'dir' => 'asc',
                'status' => 'upcoming',
                'court' => 'محكمة',
                'mine' => 0,
                'range' => 'month',
            ]))->assertOk();
        }
    }

    // ─────────────────────────────────────────────── المهام

    /** المهام: «القضية» و«المسند إليه» عمودان في جدولين آخرين. */
    public function test_tasks_sort_by_case_and_assignee(): void
    {
        $zayd = User::factory()->create(['name' => 'ياسر المحامي', 'role' => 'lawyer', 'is_active' => true]);
        $amr = User::factory()->create(['name' => 'أحمد المحامي', 'role' => 'lawyer', 'is_active' => true]);

        $client = $this->client('موكّل المهام');
        $zCase = $this->legalCase($client, 'محكمة', 'خصم', 'م/2');
        $aCase = $this->legalCase($client, 'محكمة', 'خصم', 'م/1');
        $zCase->update(['title' => 'ياء القضية']);
        $aCase->update(['title' => 'ألف القضية']);

        Task::factory()->create(['title' => 'مهمة أولى', 'case_id' => $zCase->id, 'assigned_to' => $zayd->id, 'status' => 'pending']);
        Task::factory()->create(['title' => 'مهمة ثانية', 'case_id' => $aCase->id, 'assigned_to' => $amr->id, 'status' => 'pending']);

        $byCase = $this->actingAs($this->admin)
            ->get(route('tasks.index', ['sort' => 'case', 'dir' => 'asc']))->assertOk()->getContent();
        $this->assertSame(['ألف القضية', 'ياء القضية'], $this->orderOf($byCase, ['ألف القضية', 'ياء القضية']));

        $byAssignee = $this->actingAs($this->admin)
            ->get(route('tasks.index', ['sort' => 'assignee', 'dir' => 'asc']))->assertOk()->getContent();
        $this->assertSame(['أحمد المحامي', 'ياسر المحامي'], $this->orderOf($byAssignee, ['أحمد المحامي', 'ياسر المحامي']));
    }

    // ─────────────────────────────────────────────── الموكّلون

    /** الموكّلون: الاسمُ والنوعُ يُرتَّبان. */
    public function test_clients_sort_by_name_and_type(): void
    {
        $this->client('ياسر الأخير', '96899000000', 'zayn@example.om');
        $this->client('أحمد الأوّل', '96891000000', 'ahmad@example.om');

        $asc = $this->actingAs($this->admin)
            ->get(route('clients.index', ['sort' => 'name', 'dir' => 'asc']))->assertOk()->getContent();
        $this->assertSame(['أحمد الأوّل', 'ياسر الأخير'], $this->orderOf($asc, ['أحمد الأوّل', 'ياسر الأخير']));

        $desc = $this->actingAs($this->admin)
            ->get(route('clients.index', ['sort' => 'name', 'dir' => 'desc']))->assertOk()->getContent();
        $this->assertSame(['ياسر الأخير', 'أحمد الأوّل'], $this->orderOf($desc, ['أحمد الأوّل', 'ياسر الأخير']));

        $this->actingAs($this->admin)->get(route('clients.index', ['sort' => 'type', 'dir' => 'asc']))->assertOk();
    }

    /**
     * والهاتفُ والبريدُ لا يُعرضان كترويسةٍ تُرتِّب.
     *
     * كلاهما مشفَّرٌ في القاعدة، وORDER BY عليه يرتّب النصَّ المشفَّر:
     * الصفوفُ تتحرّك، والترتيبُ عشوائيٌّ تماماً. ورأى المستخدمُ حركةً
     * فصدَّق أنّها ترتيب. فالحارسُ هنا يمنع «إتماماً» يعيدهما.
     */
    public function test_encrypted_columns_are_never_offered_as_sorts(): void
    {
        $first = $this->client('ألف', '96891111111', 'a@example.om');
        $second = $this->client('باء', '96892222222', 'b@example.om');

        $html = $this->actingAs($this->admin)->get(route('clients.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('sort=phone', $html, 'الهاتفُ المشفَّر عُرض كعمودٍ يُرتَّب');
        $this->assertStringNotContainsString('sort=email', $html, 'البريدُ المشفَّر عُرض كعمودٍ يُرتَّب');

        // ومن كتبه في الرابط يدوياً يعود إلى الافتراضي لا إلى فوضى
        $forced = $this->actingAs($this->admin)
            ->get(route('clients.index', ['sort' => 'phone', 'dir' => 'asc']))->assertOk()->getContent();
        $natural = $this->actingAs($this->admin)->get(route('clients.index'))->assertOk()->getContent();

        $this->assertSame(
            $this->orderOf($natural, [$first->name, $second->name]),
            $this->orderOf($forced, [$first->name, $second->name]),
            'مفتاحٌ مرفوضٌ غيَّر الترتيب'
        );
    }

    // ─────────────────────────────────────────────── المستندات

    /** المستندات: «القضية» و«الرافع» و«الوصول». */
    public function test_documents_sort_by_case_and_uploader(): void
    {
        $client = $this->client('موكّل المستندات');
        $zCase = $this->legalCase($client, 'محكمة', 'خصم', 'د/2');
        $aCase = $this->legalCase($client, 'محكمة', 'خصم', 'د/1');
        $zCase->update(['title' => 'ياء ملف']);
        $aCase->update(['title' => 'ألف ملف']);

        $late = User::factory()->create(['name' => 'ياسمين الرافعة', 'role' => 'staff', 'is_active' => true]);
        $early = User::factory()->create(['name' => 'أنس الرافع', 'role' => 'staff', 'is_active' => true]);

        // «للجميع» صراحةً: المصنعُ يختار الصلاحيةَ عشوائياً، و«خاص»
        // رفعه غيرُك لا يظهر لك — فيسقط صفٌّ ويبدو الترتيبُ معطوباً
        Document::factory()->create(['case_id' => $zCase->id, 'uploaded_by' => $late->id, 'title' => 'ورقة أ', 'access_level' => 'all']);
        Document::factory()->create(['case_id' => $aCase->id, 'uploaded_by' => $early->id, 'title' => 'ورقة ب', 'access_level' => 'all']);

        // «كل المستندات» لا الجذرَ: الجذرُ يعرض مجلداتِ الأشخاص لا جدولاً
        $byCase = $this->actingAs($this->admin)
            ->get(route('documents.index', ['all' => 1, 'sort' => 'case', 'dir' => 'asc']))->assertOk()->getContent();
        $this->assertSame(['ألف ملف', 'ياء ملف'], $this->orderOf($byCase, ['ألف ملف', 'ياء ملف']));

        $byUploader = $this->actingAs($this->admin)
            ->get(route('documents.index', ['all' => 1, 'sort' => 'uploader', 'dir' => 'asc']))->assertOk()->getContent();
        $this->assertSame(['أنس الرافع', 'ياسمين الرافعة'], $this->orderOf($byUploader, ['أنس الرافع', 'ياسمين الرافعة']));
    }

    // ─────────────────────────────────────────────── الترويسات

    /**
     * لكلّ عمودٍ ذي معنًى رابطُ ترتيبٍ في ترويسته.
     *
     * ترويسةٌ لا تُنقر تُنقر مرّةً ثم تُهجَر: المستخدم يظنّ الجدولَ
     * معطّلاً لا العمودَ غيرَ مرتَّب.
     */
    public function test_every_meaningful_header_carries_a_sort_link(): void
    {
        $client = $this->client('موكّل الترويسات', '96890000000', 'h@example.om');
        $case = $this->legalCase($client, 'محكمة', 'خصم', 'ت/1');
        CourtSession::create(['case_id' => $case->id, 'date' => now()->addDay(), 'location' => 'ق', 'status' => 'upcoming']);
        Task::factory()->create(['case_id' => $case->id, 'status' => 'pending']);
        Document::factory()->create(['case_id' => $case->id]);

        $expected = [
            'sessions.index' => ['court', 'client', 'date', 'status'],
            'tasks.index' => ['title', 'case', 'assignee', 'status', 'priority', 'due'],
            'clients.index' => ['name', 'type', 'cases'],
            'cases.index' => ['number', 'court', 'client', 'type', 'lawyer', 'status', 'priority', 'created'],
        ];

        foreach ($expected as $route => $keys) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

            foreach ($keys as $key) {
                $this->assertStringContainsString(
                    'sort=' . $key,
                    $html,
                    "لا رابطَ ترتيبٍ للعمود «{$key}» في {$route}"
                );
            }
        }

        $docs = $this->actingAs($this->admin)
            ->get(route('documents.index', ['all' => 1]))->assertOk()->getContent();
        foreach (['name', 'case', 'uploader', 'type', 'size', 'access', 'created'] as $key) {
            $this->assertStringContainsString('sort=' . $key, $docs, "لا رابطَ ترتيبٍ للعمود «{$key}» في المستندات");
        }
    }

    /**
     * ولا يُعرض عمودٌ مشفَّرٌ كعمودٍ يُرتَّب — في أيّ صفحة.
     *
     * ═══ العطل الذي وُلد هذا الحارسُ منه ═══
     *
     * «الخصم» مشفَّرٌ في LegalCase، وكان معروضاً في القضايا كعمودٍ
     * يُرتَّب من قبلِ هذا العمل كلِّه. ORDER BY يقع على النصّ المشفَّر،
     * والتشفيرُ بمتّجهٍ عشوائيٍّ لكلّ صفّ: الترتيبُ يختلف عن نفسه من
     * حفظٍ إلى حفظ. فالمستخدمُ يضغط، والصفوفُ تتحرّك، فيصدّق أنّها
     * رُتّبت — والحقيقةُ قرعةٌ. وهذا أسوأُ من عمودٍ لا يُرتَّب: العمودُ
     * الصامتُ يُعرف، والكاذبُ يُصدَّق.
     *
     * والقائمةُ تُقرأ من النماذج نفسِها لا من يدي: إن شُفِّر عمودٌ
     * جديدٌ غداً سقط الاختبارُ من تلقائه.
     */
    public function test_no_page_offers_a_sort_on_an_encrypted_column(): void
    {
        $encrypted = [];

        // نموذجٌ بلا أعمدةٍ مشفَّرة لا يُعلن الخاصيّة أصلاً — وذلك
        // ليس عطلاً، فيُتخطّى بدل أن يُسقط الحارسَ كلَّه
        foreach ([LegalCase::class, Client::class, User::class, Document::class, Task::class] as $model) {
            if (!property_exists($model, 'encryptable')) {
                continue;
            }

            $property = new \ReflectionProperty($model, 'encryptable');
            $encrypted = array_merge($encrypted, (array) $property->getValue(new $model()));
        }

        $encrypted = array_unique($encrypted);
        $this->assertNotEmpty($encrypted, 'لم يُقرأ أيُّ عمودٍ مشفَّر — الحارسُ فارغ');

        $client = $this->client('موكّل الحارس', '96890000000', 'g@example.om');
        $case = $this->legalCase($client, 'محكمة', 'خصم', 'ح/1');
        CourtSession::create(['case_id' => $case->id, 'date' => now()->addDay(), 'location' => 'ق', 'status' => 'upcoming']);
        Task::factory()->create(['case_id' => $case->id, 'status' => 'pending']);
        Document::factory()->create(['case_id' => $case->id, 'access_level' => 'all']);

        foreach (['cases.index', 'sessions.index', 'tasks.index', 'clients.index'] as $route) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

            foreach ($encrypted as $column) {
                $this->assertStringNotContainsString(
                    'sort=' . $column . '&',
                    $html,
                    "الصفحة {$route} تعرض «{$column}» عموداً يُرتَّب — وهو مشفَّرٌ فالترتيبُ عشوائيّ"
                );
            }
        }
    }

    /**
     * والترتيبُ المعروضُ يعطي الجوابَ نفسَه في كلّ مرّة.
     *
     * ترتيبٌ على نصٍّ مشفَّرٍ ينجح نصفَ المرّات ويسقط نصفَها: التشفيرُ
     * يختلف من صفٍّ إلى صفّ ومن حفظٍ إلى حفظ. فالتكرارُ هو ما يكشفه —
     * تشغيلةٌ واحدةٌ ناجحةٌ لا تعني شيئاً.
     */
    public function test_the_offered_sorts_are_deterministic(): void
    {
        for ($round = 0; $round < 8; $round++) {
            $late = $this->client('ياء الموكّل ' . $round);
            $early = $this->client('ألف الموكّل ' . $round);

            $lateCase = $this->legalCase($late, 'ياء المحكمة ' . $round, 'خصم', 'ك/' . $round . '/2');
            $earlyCase = $this->legalCase($early, 'ألف المحكمة ' . $round, 'خصم', 'ك/' . $round . '/1');

            CourtSession::create(['case_id' => $lateCase->id, 'date' => now()->addDays(2), 'location' => 'ق', 'status' => 'upcoming']);
            CourtSession::create(['case_id' => $earlyCase->id, 'date' => now()->addDays(9), 'location' => 'ق', 'status' => 'upcoming']);

            foreach (['client' => 'الموكّل ', 'court' => 'المحكمة '] as $key => $suffix) {
                $pair = ['ألف ' . $suffix . $round, 'ياء ' . $suffix . $round];

                $html = $this->actingAs($this->admin)
                    ->get(route('sessions.index', ['sort' => $key, 'dir' => 'asc']))
                    ->assertOk()->getContent();

                $this->assertSame($pair, $this->orderOf($html, $pair), "الجولة {$round}: الترتيبُ بـ{$key} تقلّب");
            }

            CourtSession::query()->forceDelete();
            LegalCase::query()->forceDelete();
            Client::query()->forceDelete();
        }
    }

    /** ومفتاحٌ مجهولٌ في الرابط يعود إلى الافتراضي بلا خطأ. */
    public function test_an_unknown_sort_key_falls_back(): void
    {
        $case = $this->legalCase($this->client('موكّل'), 'محكمة', 'خصم', 'ف/1');
        CourtSession::create(['case_id' => $case->id, 'date' => now()->addDay(), 'location' => 'ق', 'status' => 'upcoming']);

        foreach (['sessions.index', 'tasks.index', 'clients.index', 'documents.index'] as $route) {
            $this->actingAs($this->admin)
                ->get(route($route, ['sort' => 'id); DROP TABLE cases;--', 'dir' => 'sideways']))
                ->assertOk();
        }
    }
}
