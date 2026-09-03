<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * نتائجُ الدفعة الأولى من المراجعة — كلٌّ منها بمشهد استغلالها.
 */
class AuditBatchOneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $lawyer;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
    }

    private int $caseSeq = 0;

    private function aCase(): LegalCase
    {
        $n = ++$this->caseSeq;

        $client = Client::create([
            'name' => 'موكّل', 'type' => 'individual',
            'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96890000000',
        ]);

        return LegalCase::create([
            'case_number' => 'ق/' . $n, 'office_case_number' => 'ق/' . $n, 'title' => 'قضية', 'description' => 'و',
            'type' => 'مدني', 'court' => 'محكمة', 'opponent' => 'خصم', 'status' => 'active', 'priority' => 'medium',
            'client_id' => $client->id, 'lawyer_id' => $this->lawyer->id,
            'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);
    }

    // ───────────────────────────────── XSS مخزَّن

    /**
     * لا innerHTML لنصٍّ يكتبه مستخدم.
     *
     * عنوانُ القضية كان يُحقن بـx-html في قائمة البحث، ورسالةُ
     * المستخدم في محادثة الذكاء كذلك — وسجلُّها مشتركٌ بين كلّ من
     * يفتح القضية. وAlpine يشغّل x-init داخل ما يُحقن، فيُنفَّذ عند
     * كلّ زميل. (سياسةُ المحتوى لا تمنعه: unsafe-eval مسموح.)
     */
    public function test_no_user_text_reaches_inner_html(): void
    {
        foreach ([
            'resources/views/cases/index.blade.php' => 'r.label',
            'resources/views/cases/show.blade.php' => 'm.content',
            'resources/views/layouts/app.blade.php' => 'm.content',
        ] as $file => $needle) {
            $html = (string) file_get_contents(base_path($file));

            preg_match_all('/x-html="([^"]*)"/', $html, $m);

            foreach ($m[1] as $expr) {
                if (!str_contains($expr, $needle)) {
                    continue;
                }

                $this->assertStringContainsString(
                    'md(',
                    $expr,
                    basename($file) . ": «{$expr}» تُحقن نصّاً خاماً في innerHTML"
                );
            }
        }
    }

    // ───────────────────────────────── المحادثات

    /**
     * الردُّ يكون على رسالةٍ من المحادثة نفسِها.
     *
     * reply_to_id كان يُؤخذ بلا تحقّق ونصُّ الرسالة يُردّ في الجواب:
     * محادثةٌ مع النفس + رقمٌ متزايد = كلُّ رسائل المكتب.
     */
    public function test_a_reply_cannot_quote_a_message_from_another_conversation(): void
    {
        // محادثةٌ خاصّةٌ بين المدير والمحامي
        $private = Conversation::create([]);
        $private->participants()->attach([$this->admin->id, $this->lawyer->id]);
        $secret = Message::create([
            'conversation_id' => $private->id, 'user_id' => $this->admin->id,
            'message' => 'سرُّ الإدارة: راتبُ فلانٍ كذا',
        ]);

        // والموظّفُ يفتح محادثةً له ويحاول اقتباسَها
        $own = Conversation::create([]);
        $own->participants()->attach($this->staff->id);

        $response = $this->actingAs($this->staff)->postJson(
            route('chat.messages.send', $own),
            ['message' => '.', 'reply_to_id' => $secret->id],
        );

        $this->assertContains($response->getStatusCode(), [422, 403], 'قُبل اقتباسُ رسالةٍ من محادثةٍ أخرى');
        $this->assertStringNotContainsString('سرُّ الإدارة', $response->getContent(), 'سُرّبت الرسالة');
    }

    // ───────────────────────────────── المستندات الخاصّة

    /**
     * عنوانُ مستندٍ «خاصّ» لا يُعرض لغير رافعه — لا في صفحة القضية
     * ولا في ملفّها PDF ولا برسالة نقلٍ.
     */
    public function test_a_private_document_title_never_leaves_its_owner(): void
    {
        $case = $this->aCase();

        $private = Document::create([
            'case_id' => $case->id, 'uploaded_by' => $this->lawyer->id,
            'title' => 'تقرير طبّي سرّي', 'file_path' => 'documents/x.pdf',
            'file_type' => 'pdf', 'file_size' => 100, 'access_level' => 'private',
        ]);

        Document::create([
            'case_id' => $case->id, 'uploaded_by' => $this->lawyer->id,
            'title' => 'صحيفة دعوى', 'file_path' => 'documents/y.pdf',
            'file_type' => 'pdf', 'file_size' => 100, 'access_level' => 'all',
        ]);

        // صفحةُ القضية
        $page = $this->actingAs($this->staff)->get(route('cases.show', $case))->assertOk()->getContent();
        $this->assertStringNotContainsString('تقرير طبّي سرّي', $page, 'عنوانُ مستندٍ خاصٍّ في صفحة القضية');
        $this->assertStringContainsString('صحيفة دعوى', $page, 'ضاع المستندُ العامّ مع الخاصّ');

        // ورافعُه يراه
        $owner = $this->actingAs($this->lawyer)->get(route('cases.show', $case))->assertOk()->getContent();
        $this->assertStringContainsString('تقرير طبّي سرّي', $owner, 'حُجب المستندُ عن رافعه');

        // ونقلُه يُردّ — والرفضُ في هذا التطبيق تحويلٌ لا 403 عارية،
        // فالمهمُّ أنّ الورقة لم تتحرّك ولا رُدّ عنوانُها في رسالة
        $other = $this->aCase();

        $response = $this->actingAs($this->staff)
            ->post(route('documents.move', $private), ['case_id' => $other->id]);

        $this->assertContains($response->getStatusCode(), [302, 403]);
        $this->assertSame($case->id, $private->refresh()->case_id, 'نُقل مستندٌ خاصٌّ لغير رافعه');
        $this->assertStringNotContainsString('تقرير طبّي سرّي', (string) session('success'), 'عنوانُ الخاصّ في رسالة النجاح');
    }

    // ───────────────────────────────── /health

    /** فحصُ الصحّة لا يهمس ببنية النظام لزائر. */
    public function test_health_never_leaks_infrastructure_detail(): void
    {
        $body = $this->get('/health')->getContent();

        foreach (['SQLSTATE', 'Access denied', 'password', '@', 'mysql:'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "«{$leak}» في جواب /health");
        }

        $this->assertDoesNotMatchRegularExpression('/\d+(\.\d+)?% (used|full)/', $body, 'نسبةُ القرص منشورة');
    }

    // ───────────────────────────────── CSRF

    /** الرمزُ يُقارَن بجلسة الخادم لا بكوكي يكتبه العميل. */
    public function test_csrf_never_trusts_a_client_cookie(): void
    {
        $middleware = (string) file_get_contents(app_path('Http/Middleware/VerifyCsrfToken.php'));

        $this->assertStringNotContainsString('$request->cookie(', $middleware, 'عاد قبولُ الرمز من كوكي');
        $this->assertStringNotContainsString('function tokensMatch', $middleware, 'تجاوزٌ يدويٌّ لمقارنة الرمز');
    }

    // ───────────────────────────────── حارسُ البوابة

    /**
     * زيارةُ بابِ البوابة لا تُخرج موظّفاً من حسابه.
     *
     * الحارسُ كان ينادي logout() وهو invalidate كامل — يمحو
     * login_web_* أيضاً. ورابطٌ في بريدٍ يكفي (GET وSameSite=lax).
     */
    public function test_visiting_the_portal_does_not_log_a_staff_member_out(): void
    {
        $this->actingAs($this->lawyer)->get('/client-access/home')->assertRedirect();

        $this->assertAuthenticatedAs($this->lawyer);
        $this->actingAs($this->lawyer)->get(route('dashboard'))->assertOk();
    }

    // ───────────────────────────────── شعارُ المكتب

    /** SVG لا يُقبل شعاراً: نصٌّ يحمل سكربتاً ويُقدَّم inline. */
    public function test_an_svg_logo_is_refused(): void
    {
        $this->assertNotContains('svg', \App\Support\OfficeBrand::ALLOWED);
    }
}
