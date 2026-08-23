<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\LimitReached;
use App\Support\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * تصليب الحدود: القفل الذرّي وفحص التخزين بالبايت.
 *
 * الفحص المبكر في المتحكّم وحده لا يكفي: طلبان متزامنان يجتازانه
 * معاً وهما على المقعد الأخير فيصير 5/4. والتخزين كان يُحسب عدّاً
 * (كم مستنداً) لا حجماً — مكتبٌ عنده جيجابايت أخيرة كان يرفع ملف
 * جيجابايتين بلا اعتراض.
 */
class LimitsHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function limit(array $limits): void
    {
        PlanLimits::sync('bidaya', 'مُداوَلة | بداية', $limits);
    }

    // ── القفل يعيد الفحص داخله ───────────────────────────────────

    public function test_the_guard_rechecks_inside_the_lock(): void
    {
        $this->limit(['users' => 4]);

        // المتحكّم فحص مبكراً وكان العدد ٣، ثم أنهى طلبٌ متزامنٌ
        // المقعدَ الرابع قبل أن نصل نحن إلى الإنشاء
        User::factory()->count(4)->create(['role' => 'lawyer']);

        try {
            PlanLimits::guard('users', fn () => User::factory()->create(['role' => 'staff']));
            $this->fail('القفل لم يعد الفحص — سُمح بمستخدم خامس');
        } catch (LimitReached $e) {
            $this->assertSame('users', $e->resource);
        }

        $this->assertSame(4, User::where('role', '!=', 'client')->count());
    }

    public function test_the_guard_creates_when_room_remains(): void
    {
        $this->limit(['users' => 4]);
        User::factory()->count(3)->create(['role' => 'lawyer']);

        $user = PlanLimits::guard('users', fn () => User::factory()->create(['role' => 'staff']));

        $this->assertNotNull($user->id);
        $this->assertSame(4, User::where('role', '!=', 'client')->count());
    }

    public function test_an_office_with_no_limits_passes_the_guard_untouched(): void
    {
        $made = PlanLimits::guard('users', fn () => User::factory()->create(['role' => 'staff']));

        $this->assertNotNull($made->id);
    }

    // ── remaining / canCreate ────────────────────────────────────

    public function test_remaining_counts_down_and_can_create_flips(): void
    {
        $this->limit(['users' => 2]);

        $this->assertSame(2, PlanLimits::remaining('users'));
        $this->assertTrue(PlanLimits::canCreate('users'));

        User::factory()->count(2)->create(['role' => 'lawyer']);

        $this->assertSame(0, PlanLimits::remaining('users'));
        $this->assertFalse(PlanLimits::canCreate('users'));
        $this->assertNull(PlanLimits::remaining('cases'), 'موردٌ بلا حدّ يبقى بلا حدّ');
    }

    // ── التخزين بالبايت لا بالعدّ ────────────────────────────────

    public function test_a_file_larger_than_the_remaining_space_is_refused(): void
    {
        $this->limit(['documents' => 100, 'storage_gb' => 1]);

        // ٩٠٠ ميجابايت محجوزة من أصل جيجابايت واحدة
        Document::create([
            'uploaded_by' => $this->admin()->id,
            'title' => 'أرشيف قديم',
            'file_path' => 'documents/old.pdf',
            'file_type' => 'pdf',
            'file_size' => 900 * 1024 * 1024,
            'access_level' => 'all',
        ]);

        $this->assertFalse(PlanLimits::storageAllows(200 * 1024 * 1024), '٩٠٠م + ٢٠٠م > جيجابايت');
        $this->assertTrue(PlanLimits::storageAllows(100 * 1024 * 1024), 'الملء التامّ مسموح');
    }

    public function test_the_upload_endpoint_refuses_a_file_that_overflows_storage(): void
    {
        $this->limit(['documents' => 100, 'storage_gb' => 1]);
        $admin = $this->admin();

        Document::create([
            'uploaded_by' => $admin->id,
            'title' => 'أرشيف',
            'file_path' => 'documents/old.pdf',
            'file_type' => 'pdf',
            'file_size' => (int) (0.99 * 1073741824),
            'access_level' => 'all',
        ]);

        $response = $this->actingAs($admin)->post('/documents', [
            'title' => 'ملف يفيض',
            'file' => UploadedFile::fake()->create('big.pdf', 15 * 1024), // ١٥ ميجابايت
            'access_level' => 'all',
        ]);

        $response->assertSessionHasErrors('limit');
        $this->assertSame('storage_gb', session('limit_reached'));
        $this->assertSame(1, Document::count(), 'لم يُخزَّن شيء');
    }

    public function test_a_small_file_still_uploads_under_the_storage_limit(): void
    {
        $this->limit(['documents' => 100, 'storage_gb' => 1]);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/documents', [
            'title' => 'مذكرة',
            'file' => UploadedFile::fake()->create('memo.pdf', 200),
            'access_level' => 'all',
        ]);

        $response->assertSessionDoesntHaveErrors('limit');
        $this->assertSame(1, Document::count());
    }

    // ── المستند المرفق مع القضية طريقُ رفعٍ ثانٍ ─────────────────

    public function test_the_case_attached_document_respects_storage_too(): void
    {
        $this->limit(['documents' => 100, 'storage_gb' => 1]);
        $admin = $this->admin();

        Document::create([
            'uploaded_by' => $admin->id,
            'title' => 'أرشيف',
            'file_path' => 'documents/old.pdf',
            'file_type' => 'pdf',
            'file_size' => (int) (0.99 * 1073741824),
            'access_level' => 'all',
        ]);

        $client = \App\Models\Client::create(['name' => 'موكّل الاختبار', 'phone' => '91234567', 'type' => 'individual']);

        $response = $this->actingAs($admin)->post('/cases', [
            'client_id' => $client->id,
            'case_number' => '2026/501',
            'title' => 'قضية مع مرفق يفيض',
            'type' => 'civil',
            'description' => 'وصف',
            'court' => 'الابتدائية',
            'opponent' => 'الخصم',
            'status' => 'active',
            'priority' => 'medium',
            'doc_file' => UploadedFile::fake()->create('attached.pdf', 15 * 1024),
        ]);

        $response->assertSessionHasErrors('limit');
        $this->assertSame(0, \App\Models\LegalCase::count(), 'القضية لم تُنشأ والمرفق لم يُخزَّن');
    }
}
