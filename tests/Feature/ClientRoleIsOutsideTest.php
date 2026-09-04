<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * حسابُ الموكّل جهةٌ من خارج المكتب — لا درجةٌ أدنى فيه.
 *
 * ═══ ما كان ═══
 *
 * ‏RoleMiddleware يقول القاعدةَ صراحةً في تعليقه: «دورُ الموكّل ليس
 * درجةً أدنى في السلّم بل جهةٌ من خارج المكتب». ولم تكن مطبَّقةً على
 * أهمّ صفحتين:
 *
 *   · ‏GET /dashboard  — بلا حارس دورٍ إطلاقاً
 *   · ‏GET /command    — بلا حارس دورٍ إطلاقاً
 *
 * وكلُّ استعلاماتهما مقيَّدةٌ بـ«when($isLawyer)» وحدَها. فالمحامي
 * يُضيَّق عليه، وكلُّ دورٍ آخر — والموكّلُ منهم — يقرأ المكتبَ كلَّه.
 *
 * فحسابُ موكّلٍ يفتح صفحتَه الأولى فيقرأ أسماءَ موكّلين آخرين
 * وأرقامَ هواتفهم، وعناوينَ قضاياهم وأرقامَها، وقاعاتِ جلساتهم
 * ومواعيدَها، ومهامَّ بأسماء محاميها، وعناوينَ مستنداتٍ لم تُتَح له.
 * ثمّ يعيدها كلَّ يومٍ فيبني دفترَ موكّلي المكتب.
 *
 * و‎/command أسوأُ: يردّ JSON مباشرةً — بلا واجهةٍ ولا حيلة.
 *
 * ═══ وما يحرسه هذا ═══
 *
 * أنّ حسابَ الموكّل لا يبلغ سطحاً من أسطح الفريق، وأنّ اسمَ موكّلٍ
 * آخر ورقمَه لا يظهران له في أيّ ردّ.
 */
class ClientRoleIsOutsideTest extends TestCase
{
    use RefreshDatabase;

    private const VICTIM_CLIENT = 'موكّلُ الضحيّةِ الخاصّ';
    private const VICTIM_PHONE = '96899887766';
    private const VICTIM_CASE = 'قضيّةُ الضحيّةِ السرّيّة';
    private const VICTIM_DOC = 'مستندٌ داخليٌّ للضحيّة';

    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $victim = Client::create([
            'name' => self::VICTIM_CLIENT, 'type' => 'individual',
            'national_id' => '7778889', 'phone' => self::VICTIM_PHONE,
        ]);

        $case = LegalCase::create([
            'case_number' => 'ق/99', 'office_case_number' => '99', 'title' => self::VICTIM_CASE,
            'description' => 'و', 'type' => 'مدني', 'court' => 'محكمة', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'urgent',
            'client_id' => $victim->id, 'lawyer_id' => $lawyer->id,
            'created_by' => $admin->id, 'opened_at' => now(),
        ]);

        Task::create([
            'case_id' => $case->id, 'title' => 'مهمّةُ الضحيّة', 'status' => 'pending',
            'priority' => 'high', 'assigned_to' => $lawyer->id, 'created_by' => $admin->id,
            'due_date' => now()->subDays(3),
        ]);

        Document::create([
            'case_id' => $case->id, 'uploaded_by' => $lawyer->id,
            'title' => self::VICTIM_DOC, 'file_path' => 'documents/v.pdf',
            'file_type' => 'pdf', 'file_size' => 100,
            'access_level' => 'all', 'client_visible' => false,
        ]);

        // حسابُ موكّلٍ لا علاقةَ له بشيءٍ ممّا سبق
        $this->clientUser = User::factory()->create(['role' => 'client', 'is_active' => true]);
    }

    /** @return array<string, array{0: string}> */
    public static function teamSurfaces(): array
    {
        return [
            'لوحُ الأوامر' => ['/command?q='],
            'أوامرُ اللغة الطبيعيّة' => ['/nl/actions/parse'],
            'نبضُ التغيير' => ['/sync'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('teamSurfaces')]
    public function test_a_client_account_is_refused_at_every_team_surface(string $path): void
    {
        $response = $this->actingAs($this->clientUser)->get($path);

        $this->assertContains($response->getStatusCode(), [403, 302, 405],
            $path . ' — حسابُ موكّلٍ بلغ سطحاً من أسطح الفريق');

        $body = $response->getContent();

        foreach ([self::VICTIM_CLIENT, self::VICTIM_PHONE, self::VICTIM_CASE] as $secret) {
            $this->assertStringNotContainsString($secret, $body, $path . ' — سُرّبت بيانات موكّلٍ آخر');
        }
    }

    /**
     * والصفحةُ الأولى تصرفه إلى صفحاته — لا تُخدَم له ولا تنكسر.
     *
     * الردُّ ٤٠٣ هنا يكسر الدخول: ‎/ يحوّل إلى /dashboard لكلّ أحد.
     * فالصوابُ صرفٌ لا منع.
     */
    public function test_the_dashboard_sends_a_client_to_their_own_pages(): void
    {
        $this->actingAs($this->clientUser)
            ->get('/dashboard')
            ->assertRedirect(route('client.cases'));
    }

    /** ولا يظهر له اسمُ موكّلٍ آخر ولا رقمُه في أيّ خطوةٍ من الطريق. */
    public function test_no_other_client_data_reaches_a_client_account(): void
    {
        $body = $this->actingAs($this->clientUser)
            ->followingRedirects()
            ->get('/dashboard')
            ->getContent();

        foreach ([
            self::VICTIM_CLIENT,
            self::VICTIM_PHONE,
            self::VICTIM_CASE,
            self::VICTIM_DOC,
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $body,
                'بياناتُ موكّلٍ آخر وصلت حسابَ موكّل');
        }
    }

    /** وفريقُ المكتب يصل إلى ما هو له — الحارسُ لا يمنع أهلَه. */
    public function test_the_office_team_still_reaches_its_own_surfaces(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($staff)->get('/dashboard')->assertOk();
        $this->actingAs($staff)->get('/command?q=')->assertOk();
        $this->actingAs($staff)->get('/sync')->assertOk();
    }
}
