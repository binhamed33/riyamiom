<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session as CourtSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * سجل العمليات يجيب عن أربعة أسئلة لكل تغيير: مَن، وماذا، ومتى، وعلى
 * أيّ سجل. وثلاثة مستخدمين مختلفين يجب أن يُنسب لكلٍّ فعله لا لغيره.
 */
class AuditWhoWhatWhenTest extends TestCase
{
    use RefreshDatabase;

    private function office(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => 'مدير']);
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'name' => 'محامٍ']);
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true, 'name' => 'موظف']);
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

        return [$admin, $lawyer, $staff, $case];
    }

    public function test_three_users_three_actions_each_attributed_to_its_own_doer(): void
    {
        [$admin, $lawyer, $staff, $case] = $this->office();
        Storage::disk('private')->put('documents/a.txt', 'محتوى');

        // المحامي يغيّر حالة القضية
        $this->actingAs($lawyer)->put(route('cases.update', $case), array_merge(
            $case->only(['case_number', 'title', 'type', 'court', 'client_id', 'priority']),
            ['status' => 'closed', 'description' => 'تحديث', 'opponent' => 'خصم']
        ));

        // المدير يرفع مستنداً ثم يحذفه
        $doc = Document::create([
            'case_id' => $case->id, 'uploaded_by' => $admin->id, 'title' => 'مستند',
            'file_path' => 'documents/a.txt', 'file_type' => 'txt', 'file_size' => 10,
            'access_level' => 'all',
        ]);
        $this->actingAs($admin)->delete(route('documents.destroy', $doc));

        // الموظف يغيّر موعد جلسة
        $session = CourtSession::create([
            'case_id' => $case->id, 'date' => now()->addDays(3),
            'location' => 'قاعة ١', 'status' => 'upcoming',
        ]);
        $this->actingAs($staff)->put(route('sessions.update', $session), [
            'case_id' => $case->id, 'date' => now()->addDays(9)->format('Y-m-d'),
            'location' => 'قاعة ٢', 'status' => 'postponed',
        ]);

        $this->assertSame('قاعة ٢', $session->fresh()->location, 'تعديل الجلسة لم يُحفظ — الاختبار لا يقيس السجل');

        $logs = AuditLog::with('user')->get();
        $this->assertGreaterThanOrEqual(3, $logs->count(), 'عمليات لم تُسجَّل في سجل العمليات');

        foreach ($logs as $log) {
            $this->assertNotNull($log->user_id, "سجل بلا فاعل: {$log->action}");   // مَن
            $this->assertNotEmpty($log->action, 'سجل بلا فعل');                     // ماذا
            $this->assertNotNull($log->created_at, 'سجل بلا وقت');                  // متى
        }

        // كل فاعل نُسب إليه فعله هو
        $byUser = $logs->groupBy('user_id');
        $this->assertTrue($byUser->has($lawyer->id), 'فعل المحامي لم يُنسب إليه');
        $this->assertTrue($byUser->has($admin->id), 'فعل المدير لم يُنسب إليه');
        $this->assertTrue($byUser->has($staff->id), 'فعل الموظف لم يُنسب إليه');
    }

    public function test_an_update_keeps_the_old_value_beside_the_new(): void
    {
        [$admin, , , $case] = $this->office();
        $before = $case->status;

        $this->actingAs($admin)->put(route('cases.update', $case), array_merge(
            $case->only(['case_number', 'title', 'type', 'court', 'client_id', 'priority']),
            ['status' => 'closed', 'description' => 'تحديث', 'opponent' => 'خصم']
        ));

        $log = AuditLog::where('model_type', LegalCase::class)->latest('id')->first();
        $this->assertNotNull($log, 'تحديث القضية لم يُسجَّل');
        $this->assertNotNull($log->old_values, 'السجل بلا قيمة سابقة — لا يمكن معرفة ما تغيّر');
        $this->assertNotNull($log->new_values, 'السجل بلا قيمة جديدة');
        $this->assertSame($before, data_get($log->old_values, 'status'));
    }

    public function test_the_audit_log_cannot_be_edited_or_deleted_through_the_app(): void
    {
        $names = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->filter(fn ($n) => str_contains($n, 'audit'));

        foreach ($names as $n) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($n);
            $this->assertEmpty(
                array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']),
                "سجل العمليات قابل للتعديل عبر {$n} — يفقد قيمته كدليل"
            );
        }
    }
}
