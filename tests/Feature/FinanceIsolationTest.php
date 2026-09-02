<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinanceInvoice;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ماليّةُ المكتب: المديرُ يرى الكلَّ، وغيرُه يرى ما كتبه بيده.
 *
 * ═══ الثغرة ═══
 *
 * FinanceController::isAdmin() كانت تعيد true للمحامي والموظّف أيضاً.
 * وكلُّ فحوص الملكية مبنيّةٌ عليها — abort_unless(isAdmin() || صاحبُ
 * القيد) — فتمرّ دائماً: أيُّ موظّفٍ يعرض ويعدّل ويحذف أيَّ فاتورةٍ
 * ومعاملةٍ في المكتب، ويُنزّل مرفقاتِها. والواجهةُ كانت تعرف الصوابَ
 * فتخفي الأزرارَ — والمسارُ مفتوحٌ لمن كتب العنوان.
 */
class FinanceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $lawyer;
    private FinanceInvoice $lawyersInvoice;
    private FinanceTransaction $lawyersTransaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);

        $client = Client::create(['name' => 'موكّل', 'type' => 'individual', 'national_id' => '1234567', 'phone' => '96890000000']);

        $this->lawyersInvoice = FinanceInvoice::create([
            'invoice_number' => 'INV-2026-0001', 'client_id' => $client->id, 'amount' => 500, 'paid_amount' => 0,
            'status' => 'pending', 'issue_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
            'description' => 'أتعاب', 'user_id' => $this->lawyer->id,
            'attachment_path' => 'finance/x.pdf', 'attachment_name' => 'x.pdf',
        ]);

        $this->lawyersTransaction = FinanceTransaction::create([
            'type' => 'income', 'category' => 'fees', 'amount' => 100, 'description' => 'قبض',
            'date' => now()->toDateString(), 'payment_method' => 'cash', 'user_id' => $this->lawyer->id,
            'attachment_path' => 'finance/y.pdf', 'attachment_name' => 'y.pdf',
        ]);
    }

    /**
     * الرفضُ في هذا التطبيق إعادةُ توجيهٍ إلى اللوحة برسالة، لا 403
     * عارية (المعالجُ العامّ). فيُقبل الشكلان ويُفحص الجوهر: لا يصل
     * المحتوى، ولا يُقدَّم مرفق.
     */
    private function assertRefused(\Illuminate\Testing\TestResponse $response, string $what): void
    {
        $this->assertContains($response->getStatusCode(), [302, 403], "{$what}: وصل");
        if ($response->getStatusCode() === 302) {
            $this->assertStringContainsString('dashboard', (string) $response->headers->get('Location'), "{$what}: حُوّل إلى غير اللوحة");
        }
        $this->assertNull($response->headers->get('Content-Disposition'), "{$what}: قُدّم ملفّ");
    }

    /** الموظّفُ لا يفتح فاتورةَ غيره ولا معاملتَه ولا مرفقَهما. */
    public function test_a_staff_member_cannot_reach_another_users_records(): void
    {
        $this->assertRefused($this->actingAs($this->staff)->get(route('finance.invoices.show', $this->lawyersInvoice)), 'فاتورةُ غيره');
        $this->assertRefused($this->actingAs($this->staff)->get(route('finance.transactions.show', $this->lawyersTransaction)), 'معاملةُ غيره');

        // المرفقاتُ كانت بلا فحصٍ أصلاً — لا حتى الفحصُ المعطوب
        $this->assertRefused($this->actingAs($this->staff)->get(route('finance.invoices.attachment', $this->lawyersInvoice)), 'مرفقُ فاتورة');
        $this->assertRefused($this->actingAs($this->staff)->get(route('finance.transactions.attachment', $this->lawyersTransaction)), 'مرفقُ معاملة');
    }

    /** ولا يعدّل ولا يحذف ولا يسدّد ما ليس له. */
    public function test_a_staff_member_cannot_alter_or_delete_another_users_records(): void
    {
        $this->assertRefused($this->actingAs($this->staff)->put(route('finance.invoices.update', $this->lawyersInvoice), [
            'amount' => 1, 'status' => 'paid',
        ]), 'تعديلُ فاتورة');

        $this->assertRefused($this->actingAs($this->staff)->post(route('finance.invoices.pay', $this->lawyersInvoice)), 'تسديدُ فاتورة');
        $this->assertRefused($this->actingAs($this->staff)->delete(route('finance.invoices.destroy', $this->lawyersInvoice)), 'حذفُ فاتورة');
        $this->assertRefused($this->actingAs($this->staff)->delete(route('finance.transactions.destroy', $this->lawyersTransaction)), 'حذفُ معاملة');

        $this->assertSame(500.0, (float) $this->lawyersInvoice->refresh()->amount, 'عُدّلت فاتورةُ غيره');
        $this->assertSame('pending', $this->lawyersInvoice->status, 'سُدّدت فاتورةُ غيره');
        $this->assertNotNull(FinanceTransaction::find($this->lawyersTransaction->id), 'حُذفت معاملةُ غيره');
    }

    /** صاحبُ القيد يراه، والمديرُ يرى الكلَّ — كما كان مقصوداً. */
    public function test_the_owner_and_the_manager_still_reach_them(): void
    {
        $this->actingAs($this->lawyer)->get(route('finance.invoices.show', $this->lawyersInvoice))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.invoices.show', $this->lawyersInvoice))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.transactions.show', $this->lawyersTransaction))->assertOk();
    }

    /** والقائمةُ نفسُها: الموظّفُ لا يرى فاتورةَ المحامي فيها. */
    public function test_the_index_hides_other_users_rows_from_staff(): void
    {
        $html = $this->actingAs($this->staff)->get(route('finance.index', ['tab' => 'invoices']))->assertOk()->getContent();
        $this->assertStringNotContainsString('INV-2026-0001', $html, 'فاتورةُ غيره في قائمته');

        $adminHtml = $this->actingAs($this->admin)->get(route('finance.index', ['tab' => 'invoices']))->assertOk()->getContent();
        $this->assertStringContainsString('INV-2026-0001', $adminHtml);
    }
}
