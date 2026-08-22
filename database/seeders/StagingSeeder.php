<?php

namespace Database\Seeders;

use App\Models\{User, Client, LegalCase, Session as CourtSession, Task, Document, Notification, Setting, AuditLog, Suggestion};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * بذر بيانات اختبار وهمية لبيئة STAGING المعزولة — لا للإنتاج.
 *
 * حارس قاطع: يرفض العمل إن لم تكن القاعدة داخل مجلد STAGING. كل اسم
 * يبدأ بـ TEST_ فيُثبت لاحقاً أنه اختبار لا إنتاج. لا اسم ولا بريد
 * ولا هاتف حقيقي.
 */
class StagingSeeder extends Seeder
{
    public function run(): void
    {
        $db = (string) config('database.connections.sqlite.database');
        if (!str_contains($db, 'STAGING_mudawala') || !str_contains($db, 'TEST_')) {
            $this->command->error("رُفض البذر: القاعدة ليست staging — {$db}");

            return;
        }

        $office  = strtoupper((string) (getenv('STAGE_OFFICE') ?: 'ALPHA'));
        $clients = (int) (getenv('STAGE_CLIENTS') ?: 12);
        $cases   = (int) (getenv('STAGE_CASES')   ?: 22);
        $docs    = (int) (getenv('STAGE_DOCS')    ?: 32);
        $tasks   = (int) (getenv('STAGE_TASKS')   ?: 34);
        $sess    = (int) (getenv('STAGE_SESSIONS')?: 24);

        Setting::updateOrCreate(['key' => 'office_name'], ['value' => "TEST_Office_{$office}", 'group' => 'general']);
        Setting::updateOrCreate(['key' => 'office_email'], ['value' => strtolower("test_{$office}") . '@staging.invalid', 'group' => 'general']);
        Setting::updateOrCreate(['key' => 'client_portal_enabled'], ['value' => '1', 'group' => 'portal']);

        $mk = function (string $role, string $label, int $n = 1) use ($office) {
            $out = [];
            for ($i = 1; $i <= $n; $i++) {
                $out[] = User::create([
                    'name' => "TEST_{$label}_{$office}_" . sprintf('%02d', $i),
                    'email' => strtolower("test_{$label}_{$office}_" . sprintf('%02d', $i)) . '@staging.invalid',
                    'password' => Hash::make('StageP@ss2026!'),
                    'role' => $role,
                    'is_active' => true,
                    'phone' => '9689' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
                    'locale' => $i % 2 ? 'ar' : 'en',
                ]);
            }

            return $out;
        };

        $owner   = $mk('developer', 'Owner')[0];
        $manager = $mk('admin', 'Manager')[0];
        $lawyers = $mk('lawyer', 'Lawyer', 3);
        $staff   = $mk('staff', 'Employee', 2);

        $prefix = ['ALPHA' => '10', 'BETA' => '20', 'GAMMA' => '30', 'DELTA' => '40'][$office] ?? '90';

        $clientRows = [];
        for ($i = 1; $i <= $clients; $i++) {
            $clientRows[] = Client::create([
                'name' => "TEST_Client_{$office}_" . sprintf('%02d', $i),
                'type' => $i % 4 === 0 ? 'company' : 'individual',
                'phone' => '9689' . $prefix . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'email' => strtolower("test_client_{$office}_" . sprintf('%02d', $i)) . '@staging.invalid',
                'national_id' => $prefix . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'company_name' => $i % 4 === 0 ? "TEST_Co_{$office}_{$i}" : null,
                'address' => "TEST_Address_{$office}",
                'user_id' => $manager->id,
            ]);
        }

        $statuses = ['active', 'pending', 'closed', 'adjudicated'];
        $caseRows = [];
        for ($i = 1; $i <= $cases; $i++) {
            $caseRows[] = LegalCase::create([
                'case_number' => "TEST-{$office}-" . sprintf('%04d', $i),
                'title' => "TEST_Case_{$office}_" . sprintf('%02d', $i),
                'description' => "بيانات اختبار وهمية للمكتب {$office} — قضية {$i}",
                'type' => ['مدني', 'تجاري', 'عمالي', 'جزائي'][$i % 4],
                'court' => "TEST_Court_{$office}",
                'opponent' => "TEST_Opponent_{$i}",
                'status' => $statuses[$i % 4],
                'priority' => ['low', 'medium', 'high'][$i % 3],
                'opened_at' => now()->subDays(random_int(5, 300)),
                'next_date' => now()->addDays(random_int(1, 60)),
                'client_id' => $clientRows[$i % count($clientRows)]->id,
                'lawyer_id' => $lawyers[$i % count($lawyers)]->id,
                'created_by' => $manager->id,
            ]);
        }

        for ($i = 1; $i <= $sess; $i++) {
            CourtSession::create([
                'case_id' => $caseRows[$i % count($caseRows)]->id,
                'date' => now()->addDays(random_int(-40, 60)),
                'location' => "TEST_Courtroom_{$office}_" . (($i % 5) + 1),
                'status' => ['scheduled', 'held', 'postponed'][$i % 3],
                'notes' => "ملاحظة اختبار {$office} #{$i}",
            ]);
        }

        for ($i = 1; $i <= $tasks; $i++) {
            Task::create([
                'title' => "TEST_Task_{$office}_" . sprintf('%02d', $i),
                'description' => "مهمة اختبار وهمية {$i}",
                'case_id' => $caseRows[$i % count($caseRows)]->id,
                'assigned_to' => $lawyers[$i % count($lawyers)]->id,
                'created_by' => $manager->id,
                'status' => ['pending', 'in_progress', 'completed'][$i % 3],
                'priority' => ['low', 'medium', 'high'][$i % 3],
                'due_date' => now()->addDays(random_int(1, 45)),
            ]);
        }

        // نكتب عبر قرص private نفسه الذي يستعمله الرفع الحقيقي، فيطابق
        // المسار ما يتوقّعه التنزيل والمعاينة — لا نخمّن بنية المجلدات
        for ($i = 1; $i <= $docs; $i++) {
            $rel = 'documents/TEST_Doc_' . $office . '_' . sprintf('%02d', $i) . '.txt';
            \Illuminate\Support\Facades\Storage::disk('private')->put($rel,
                "مستند اختبار وهمي — مكتب {$office} — رقم {$i}\nسرّ هذا المكتب: SECRET_{$office}_{$i}\n");
            Document::create([
                'case_id' => $caseRows[$i % count($caseRows)]->id,
                'uploaded_by' => $manager->id,
                'title' => "TEST_Document_{$office}_" . sprintf('%02d', $i),
                'file_path' => $rel,
                'file_type' => 'txt',
                'file_size' => 120,
                'access_level' => [ 'all', 'team', 'private' ][$i % 3],
                'client_visible' => $i % 3 === 0,
                'doc_date' => now()->subDays(random_int(1, 200)),
            ]);
        }

        foreach ([$owner, $manager, ...$lawyers, ...$staff] as $u) {
            Notification::create([
                'user_id' => $u->id,
                'title' => "TEST_Notification_{$office}",
                'message' => "إشعار اختبار وهمي لمكتب {$office}",
                'type' => 'info',
                'is_read' => false,
            ]);
        }

        foreach ($staff as $k => $u) {
            Suggestion::create([
                'user_id' => $u->id,
                'title' => "TEST_Suggestion_{$office}_" . ($k + 1),
                'content' => "اقتراح اختبار وهمي من موظف مكتب {$office} رقم " . ($k + 1),
                'status' => 'pending',
                'delivery_state' => 'pending',
                'context' => ['user' => ['name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'code' => "EMP-{$office}-" . ($k + 1)]],
            ]);
        }

        foreach ([[$lawyers[0], 'case.status_changed'], [$manager, 'document.uploaded'], [$staff[0], 'session.rescheduled']] as [$u, $action]) {
            AuditLog::create([
                'user_id' => $u->id,
                'action' => $action,
                'model_type' => LegalCase::class,
                'model_id' => $caseRows[0]->id,
                'new_values' => ['note' => "TEST_{$office}"],
                'ip_address' => '203.0.113.' . random_int(1, 254),
                'user_agent' => 'TEST_Agent',
            ]);
        }

        $this->command->info(sprintf('%-6s users=%d clients=%d cases=%d sessions=%d tasks=%d docs=%d notif=%d sugg=%d audit=%d',
            $office, User::count(), Client::count(), LegalCase::count(), CourtSession::count(),
            Task::count(), Document::count(), Notification::count(), Suggestion::count(), AuditLog::count()));
    }
}
