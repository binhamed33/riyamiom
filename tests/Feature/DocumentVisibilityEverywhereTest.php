<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * مستوى الإتاحة يُطبَّق في كلّ مسارِ قراءة — لا في بعضها.
 *
 * ═══ ما وقع ═══
 *
 * ‏Document::scopeVisibleTo موجودةٌ وصحيحة، ومطبَّقةٌ في صفحة القضية
 * وفي ملفّها. وكانت غائبةً عن ثلاثة مساراتٍ أخرى تحمّل المستنداتِ
 * نفسَها:
 *
 *   · ‏GET /cases/{case}/timeline   — الخطّ الزمنيّ
 *   · ‏GET /command?q=…             — لوحُ الأوامر
 *   · ‏GET /dashboard               — نافذةُ «أحدث المستندات»
 *
 * والمُسرَّبُ ليس الملفَّ — بل عنوانَه. و«تقرير طبّي» أو «طلب طلاق»
 * في قضيةِ زميلٍ يكفي وحدَه: يُعرَف الموضوعُ من الاسم بلا فتح.
 *
 * ولا حارسَ آخر يمسك: الوصولُ إلى القضايا مفتوحٌ لكلّ الفريق بالقصد
 * ‏(authorizeCaseAccess لا تفعل شيئاً عمداً)، فمستوى الإتاحة هو
 * الحاجزُ الوحيد بين موظّفي المكتب. فإن سقط في مسار، سقط كلُّه.
 *
 * ═══ ولماذا اختبارٌ يمرّ على كلّ المسارات ═══
 *
 * العطبُ لم يكن في المنطق — كان «مسارٌ نُسي». واختبارٌ يفحص مساراً
 * أو اثنين يترك الثالثَ يُكتب غداً بلا حارس. فهذا يمرّ على كلّ سطحٍ
 * يعرض عناوينَ مستندات، ويشترط ألّا يظهر العنوانُ الخاصُّ في أيٍّ
 * منها — ومن أضاف سطحاً جديداً أضاف سطراً هنا.
 */
class DocumentVisibilityEverywhereTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'تقريرٌ طبّيٌّ سرّيٌّ جدّاً';
    private const OPEN = 'صحيفةُ دعوى';

    private User $lawyer;
    private User $staff;
    private User $admin;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $client = Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96890000000',
        ]);

        $this->case = LegalCase::create([
            'case_number' => 'ق/1', 'office_case_number' => 'ق/1', 'title' => 'قضية',
            'description' => 'و', 'type' => 'مدني', 'court' => 'محكمة', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'lawyer_id' => $this->lawyer->id,
            'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);

        // خاصٌّ برافعه — المحامي — ولا يراه غيرُه
        Document::create([
            'case_id' => $this->case->id, 'uploaded_by' => $this->lawyer->id,
            'title' => self::SECRET, 'file_path' => 'documents/secret.pdf',
            'file_type' => 'pdf', 'file_size' => 100, 'access_level' => 'private',
        ]);

        // ومفتوحٌ للمكتب — يجب أن يبقى ظاهراً، فالحارسُ لا يحجب ما لا يُحجَب
        Document::create([
            'case_id' => $this->case->id, 'uploaded_by' => $this->lawyer->id,
            'title' => self::OPEN, 'file_path' => 'documents/open.pdf',
            'file_type' => 'pdf', 'file_size' => 100, 'access_level' => 'all',
        ]);
    }

    /**
     * كلُّ سطحٍ يعرض عناوينَ مستندات.
     *
     * @return array<string, array{0: string, 1: bool}>  المسار ⇐ [العنوان، أيُظهر المفتوح؟]
     */
    public static function surfaces(): array
    {
        return [
            'صفحة القضية' => ['cases.show', true],
            'الخطّ الزمنيّ' => ['cases.timeline', true],
            'لوحُ الأوامر' => ['command', false],
            'لوحةُ التحكّم' => ['dashboard', false],
            'قائمةُ المستندات' => ['documents.index', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('surfaces')]
    public function test_a_private_title_never_appears_on_any_surface(string $route, bool $showsOpen): void
    {
        $url = match ($route) {
            'cases.show', 'cases.timeline' => route($route, $this->case),
            'command' => route('command', ['q' => 'تقرير']),
            default => route($route),
        };

        $body = $this->actingAs($this->staff)->get($url)->assertOk()->getContent();

        $this->assertStringNotContainsString(self::SECRET, $body,
            $route . ' — عنوانُ مستندٍ خاصٍّ ظهر لموظّفٍ لا يملكه');
    }

    /** ورافعُه يراه — الحارسُ يحجب عن الغير لا عن صاحبه. */
    public function test_the_owner_still_sees_their_own_private_document(): void
    {
        $body = $this->actingAs($this->lawyer)
            ->get(route('cases.show', $this->case))->assertOk()->getContent();

        $this->assertStringContainsString(self::SECRET, $body,
            'حُجب المستندُ عن رافعه — الحارسُ يمنع ما يجب أن يُرى');
    }

    /** والمفتوحُ للمكتب يبقى ظاهراً لكلّ الفريق. */
    public function test_an_office_wide_document_stays_visible(): void
    {
        $body = $this->actingAs($this->staff)
            ->get(route('cases.show', $this->case))->assertOk()->getContent();

        $this->assertStringContainsString(self::OPEN, $body,
            'حُجب مستندٌ مفتوحٌ للمكتب — الحارسُ أوسعُ ممّا يجب');
    }

    /**
     * وكلُّ مسارٍ يحمّل مستنداتٍ يمرّ على visibleTo.
     *
     * حارسُ نصٍّ لا حارسُ سلوك: يمسك المسارَ الذي يُكتب غداً بلا
     * ترشيح، قبل أن يصل إلى الإنتاج — والاختبارُ السلوكيُّ أعلاه لا
     * يعرف بوجوده أصلاً.
     */
    public function test_no_controller_loads_documents_without_the_scope(): void
    {
        $offenders = [];

        foreach (glob(app_path('Http/Controllers/*.php')) as $file) {
            $code = (string) file_get_contents($file);

            // ‏'documents' داخل load()/with() بلا دالّةِ ترشيح بعده
            if (preg_match_all("/['\"]documents['\"]\s*(?![=,]>)\s*[,\]]/", $code, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [$hit, $at]) {
                    $before = substr($code, max(0, $at - 120), 120);

                    if (preg_match('/->(load|with|loadMissing)\(\s*\[?[^)]*$/', $before)) {
                        $offenders[] = basename($file) . ' — ' . trim($hit);
                    }
                }
            }
        }

        $this->assertSame([], $offenders,
            "مسارٌ يحمّل المستنداتِ بلا visibleTo:\n" . implode("\n", $offenders));
    }
}
