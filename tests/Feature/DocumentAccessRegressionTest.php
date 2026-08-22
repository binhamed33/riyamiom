<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * عطلان حقيقيان ظهرا في بيئة الاختبار المعزولة:
 *
 * ١) مسارا التنزيل والمعاينة كانا داخل مجموعة «إدارة أنواع المستندات»
 *    المحصورة بمدير المكتب، فلم يستطع محامٍ ولا موظف تنزيل مستند واحد
 *    ولا معاينته في أي مكتب.
 *
 * ٢) اسم الملف عند التنزيل يُبنى من عنوان يكتبه المستخدم. وعنوان مثل
 *    «عقد 2024/2025» — شائع في المكاتب — يجعل Symfony يرفض الاسم فيسقط
 *    الطلب برسالة عامة، فلا يُنزَّل ذلك المستند أبداً ولا يُفهم السبب.
 */
class DocumentAccessRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function office(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $lawyer = User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

        return [$admin, $lawyer, $staff, $case];
    }

    private function doc(LegalCase $case, User $uploader, string $level, string $title = 'TEST_Doc'): Document
    {
        Storage::disk('private')->put('documents/t.txt', 'محتوى اختبار');

        return Document::create([
            'case_id' => $case->id,
            'uploaded_by' => $uploader->id,
            'title' => $title,
            'file_path' => 'documents/t.txt',
            'file_type' => 'txt',
            'file_size' => 20,
            'access_level' => $level,
        ]);
    }

    public function test_download_and_preview_are_open_to_the_whole_office_team(): void
    {
        // المسار كان محصوراً بمدير المكتب — نثبّت الدور المقصود في المسار نفسه
        foreach (['documents.download', 'documents.preview'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $role = collect($middleware)->first(fn ($m) => str_starts_with((string) $m, 'role:'));

            $this->assertNotNull($role, "{$name} بلا بوابة دور");
            foreach (['lawyer', 'staff'] as $r) {
                $this->assertStringContainsString($r, $role,
                    "{$name} يمنع {$r} — لا يستطيع تنزيل مستند واحد.");
            }
        }
    }

    public function test_a_lawyer_downloads_a_shared_document(): void
    {
        [$admin, $lawyer, , $case] = $this->office();
        $doc = $this->doc($case, $admin, 'all');

        $this->actingAs($lawyer)->get(route('documents.download', $doc))->assertOk();
        $this->actingAs($lawyer)->get(route('documents.preview', $doc))->assertOk();
    }

    public function test_a_staff_member_downloads_a_team_document(): void
    {
        [$admin, , $staff, $case] = $this->office();
        $doc = $this->doc($case, $admin, 'team');

        $this->actingAs($staff)->get(route('documents.download', $doc))->assertOk();
    }

    public function test_a_private_document_stays_with_whoever_uploaded_it(): void
    {
        [$admin, $lawyer, $staff, $case] = $this->office();
        $doc = $this->doc($case, $admin, 'private');

        $this->actingAs($admin)->get(route('documents.download', $doc))->assertOk();

        // المكتب يحوّل رفض الصلاحية إلى رسالة مفهومة بدل صفحة 403 عارية،
        // فالمهم أن المحتوى لا يصل — لا الرمز بعينه.
        foreach ([$lawyer, $staff] as $intruder) {
            $response = $this->actingAs($intruder)->get(route('documents.download', $doc));

            $this->assertContains($response->status(), [403, 302],
                'مستند خاص وصل إلى من لا يملكه (الرمز ' . $response->status() . ')');
            $this->assertStringNotContainsString('محتوى اختبار', $response->getContent());
        }
    }

    public function test_a_title_with_a_slash_still_downloads(): void
    {
        [$admin, , , $case] = $this->office();
        $doc = $this->doc($case, $admin, 'all', 'عقد 2024/2025');

        $response = $this->actingAs($admin)->get(route('documents.download', $doc));

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringNotContainsString('2024/2025', $disposition);
        $this->assertStringContainsString('.txt', $disposition);
    }

    public function test_a_title_with_a_backslash_still_downloads(): void
    {
        [$admin, , , $case] = $this->office();
        $doc = $this->doc($case, $admin, 'all', 'ملف\\سرّي');

        $this->actingAs($admin)->get(route('documents.download', $doc))->assertOk();
        $this->actingAs($admin)->get(route('documents.preview', $doc))->assertOk();
    }
}
