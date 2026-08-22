<?php

namespace Database\Seeders;

use App\Models\{User, Client, LegalCase, Session as CourtSession, Task, Document};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** بيانات ضخمة لقياس الأداء — staging فقط. */
class StagingBulkSeeder extends Seeder
{
    public function run(): void
    {
        $db = (string) config('database.connections.sqlite.database');
        if (!str_contains($db, 'STAGING_mudawala') || !str_contains($db, 'TEST_')) {
            $this->command->error('رُفض: ليست قاعدة staging'); return;
        }

        $n = (int) (getenv('BULK_CLIENTS') ?: 500);
        $cases = (int) (getenv('BULK_CASES') ?: 1000);
        $manager = User::where('role', 'admin')->firstOrFail();
        $lawyer = User::where('role', 'lawyer')->firstOrFail();

        $t0 = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            Client::create([
                'name' => 'TEST_Bulk_Client_' . $i,
                'type' => 'individual',
                'phone' => '96895' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'email' => "test_bulk_{$i}@staging.invalid",
                'national_id' => '99' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'user_id' => $manager->id,
            ]);
        }
        $ids = Client::where('name', 'like', 'TEST_Bulk_%')->pluck('id')->all();

        for ($i = 0; $i < $cases; $i++) {
            LegalCase::create([
                'case_number' => 'TEST-BULK-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'title' => 'TEST_Bulk_Case_' . $i,
                'description' => 'قضية اختبار أداء',
                'court' => 'TEST_Court',
                'opponent' => 'TEST_Opponent',
                'type' => 'مدني',
                'status' => ['active','pending','closed'][$i % 3],
                'priority' => 'medium',
                'opened_at' => now()->subDays($i % 300),
                'client_id' => $ids[$i % count($ids)],
                'lawyer_id' => $lawyer->id,
                'created_by' => $manager->id,
            ]);
        }
        $caseIds = LegalCase::where('title', 'like', 'TEST_Bulk_%')->pluck('id')->all();

        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['case_id' => $caseIds[$i % count($caseIds)], 'date' => now()->addDays($i % 90),
                'location' => 'TEST_Room', 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 250) as $c) { DB::table('court_sessions')->insert($c); }

        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['title' => 'TEST_Bulk_Task_' . $i, 'case_id' => $caseIds[$i % count($caseIds)],
                'assigned_to' => $lawyer->id, 'created_by' => $manager->id, 'status' => 'pending',
                'priority' => 'medium', 'due_date' => now()->addDays($i % 60), 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 250) as $c) { DB::table('tasks')->insert($c); }

        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['case_id' => $caseIds[$i % count($caseIds)], 'uploaded_by' => $manager->id,
                'title' => 'TEST_Bulk_Doc_' . $i, 'file_path' => 'documents/TEST_Doc_ALPHA_01.txt',
                'file_type' => 'txt', 'file_size' => 100, 'access_level' => 'all',
                'client_visible' => 0, 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 250) as $c) { DB::table('documents')->insert($c); }

        $this->command->info(sprintf('بُذر في %.1f ثانية · عملاء=%d قضايا=%d جلسات=%d مهام=%d مستندات=%d',
            microtime(true) - $t0, Client::count(), LegalCase::count(),
            CourtSession::count(), Task::count(), Document::count()));
    }
}
