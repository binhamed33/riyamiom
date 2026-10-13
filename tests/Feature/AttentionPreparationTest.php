<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use App\Services\AttentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تحديثُ القضية يُحضّر الجلسة — والتنبيهُ يقول «تم التحضير».
 *
 * ═══ ما وقع ═══
 *
 * زرُّ «تحضير الجلسة» يفتح نافذةَ تحديثٍ على القضية، فيكتب المحامي ما
 * حضّره ويحفظ — ويبقى التنبيه «بدون تحضير»: الحكمُ كان بمهمّةٍ مفتوحةٍ
 * عنوانُها «تحضير» وحدَها، وإذا أُنجزت سقط الاحتسابُ أيضاً.
 */
class AttentionPreparationTest extends TestCase
{
    use RefreshDatabase;

    private User $lawyer;

    private LegalCase $case;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $client = Client::create(['name' => 'موكّل', 'type' => 'individual', 'national_id' => '1234567', 'phone' => '96891234567']);
        $this->case = LegalCase::create([
            'case_number' => 'م/2026/9', 'title' => 'قضية', 'description' => 'وصف', 'type' => 'مدني', 'court' => 'المحكمة',
            'opponent' => 'خصم', 'status' => 'active', 'priority' => 'medium', 'client_id' => $client->id,
            'created_by' => $this->lawyer->id, 'lawyer_id' => $this->lawyer->id, 'opened_at' => now(),
        ]);
        $this->session = Session::create(['case_id' => $this->case->id, 'date' => now()->addDay(), 'status' => 'upcoming', 'location' => 'المحكمة']);
    }

    private function sessionItem(): array
    {
        $this->actingAs($this->lawyer);

        // بالأيقونة لا بالعنوان: «مهمة متأخرة — تحضير الجلسة» عنوانٌ فيه «جلسة» أيضاً
        return app(AttentionService::class)->items()->first(fn ($i) => $i['icon'] === 'gavel');
    }

    public function test_a_session_with_nothing_done_asks_for_preparation(): void
    {
        $item = $this->sessionItem();

        $this->assertStringContainsString('بدون تحضير', $item['description']);
        $this->assertSame('warning', $item['severity']);
        $this->assertNotNull($item['action']);
    }

    /** تحديثٌ على القضية — كما يفعله زرُّ «تحضير الجلسة» — يُحضّرها. */
    public function test_a_case_update_counts_as_preparation(): void
    {
        $this->actingAs($this->lawyer)
            ->postJson(route('cases.activities.store', $this->case), ['type' => 'note', 'title' => 'راجعت المذكّرة وجهّزت المستندات'])
            ->assertOk();

        $item = $this->sessionItem();

        $this->assertStringContainsString('تم التحضير', $item['description']);
        $this->assertStringContainsString('راجعت المذكّرة', $item['description']);
        $this->assertSame('info', $item['severity']);
        $this->assertNull($item['action']);
    }

    /** مهمّةُ تحضيرٍ أُنجزت = تحضيرٌ تامّ، لا عودةٌ إلى «بدون تحضير». */
    public function test_a_completed_preparation_task_still_counts(): void
    {
        Task::factory()->create(['case_id' => $this->case->id, 'title' => 'تحضير الجلسة', 'status' => 'completed', 'created_by' => $this->lawyer->id, 'assigned_to' => $this->lawyer->id]);

        $item = $this->sessionItem();

        $this->assertStringContainsString('تم التحضير', $item['description']);
        $this->assertSame('info', $item['severity']);
    }

    public function test_an_open_preparation_task_is_in_progress(): void
    {
        Task::factory()->create(['case_id' => $this->case->id, 'title' => 'تحضير الجلسة', 'status' => 'pending', 'due_date' => now()->addDays(3), 'created_by' => $this->lawyer->id, 'assigned_to' => $this->lawyer->id]);

        $d = $this->sessionItem()['description']; fwrite(STDERR, "
DESC: " . $d . "
"); $this->assertStringContainsString('قيد التنفيذ', $d);
    }

    /** تحديثٌ سبق تحديدَ الجلسة ليس تحضيراً لها. */
    public function test_an_update_older_than_the_session_does_not_count(): void
    {
        $old = CaseActivity::create(['case_id' => $this->case->id, 'user_id' => $this->lawyer->id, 'type' => 'note', 'title' => 'قديم', 'occurred_at' => now()->subDays(10)]);
        $old->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $this->assertStringContainsString('بدون تحضير', $this->sessionItem()['description']);
    }
}
