<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * نقل البيانات (#35) — «انتقل إلى مُداوَلة بدون أن تبدأ من الصفر»
 *
 * أداة داخلية لفريق مُداوَلة تُشغَّل أثناء تأسيس مكتب جديد لاستيراد
 * بياناته من ملفات Excel. ليست ميزة ذاتية للعميل بعد — تُنفَّذ كخدمة.
 *
 *   php artisan mudawala:import clients clients.xlsx --dry-run
 *   php artisan mudawala:import cases cases.xlsx
 *
 * أعمدة clients: الاسم | الهاتف | البريد | العنوان | الرقم المدني | الشركة
 * أعمدة cases:   رقم القضية | العنوان | اسم الموكل | المحكمة | النوع | الحالة
 * (الصف الأول عناوين — يُتجاهل. الأعمدة بعد الأول اختيارية.)
 */
class ImportData extends Command
{
    protected $signature = 'mudawala:import
        {type : نوع البيانات clients|cases}
        {file : مسار ملف xlsx أو csv}
        {--dry-run : معاينة بلا حفظ}';

    protected $description = 'استيراد بيانات مكتب (موكلون/قضايا) من Excel — أداة تأسيس داخلية';

    public function handle(): int
    {
        $type = $this->argument('type');
        $file = $this->argument('file');

        if (!in_array($type, ['clients', 'cases'], true)) {
            $this->error('النوع يجب أن يكون clients أو cases');

            return self::FAILURE;
        }

        if (!is_file($file)) {
            $this->error('الملف غير موجود: ' . $file);

            return self::FAILURE;
        }

        $rows = Excel::toArray([], $file)[0] ?? [];
        array_shift($rows); // صف العناوين
        $rows = array_values(array_filter($rows, fn ($r) => trim((string) ($r[0] ?? '')) !== ''));

        if (!$rows) {
            $this->warn('لا صفوف بيانات في الملف.');

            return self::SUCCESS;
        }

        $this->info(($this->option('dry-run') ? '[معاينة] ' : '') . 'صفوف قابلة للاستيراد: ' . count($rows));

        $result = DB::transaction(function () use ($type, $rows) {
            $stats = $type === 'clients' ? $this->importClients($rows) : $this->importCases($rows);

            if ($this->option('dry-run')) {
                DB::rollBack();
            }

            return $stats;
        });

        $this->table(['أُنشئ', 'حُدّث', 'تخطٍّ (سبب)'], [[
            $result['created'],
            $result['updated'],
            implode('، ', array_slice($result['skipped'], 0, 5)) ?: '—',
        ]]);

        if ($this->option('dry-run')) {
            $this->warn('معاينة فقط — لم يُحفظ شيء. أعد التنفيذ بدون --dry-run للاستيراد الفعلي.');
        }

        return self::SUCCESS;
    }

    /** @param array<int,array<int,mixed>> $rows */
    private function importClients(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row[0] ?? ''));
            if ($name === '') {
                $skipped[] = 'صف ' . ($i + 2) . ': بلا اسم';
                continue;
            }

            $attrs = [
                'phone' => trim((string) ($row[1] ?? '')) ?: null,
                'email' => trim((string) ($row[2] ?? '')) ?: null,
                'address' => trim((string) ($row[3] ?? '')) ?: null,
                'national_id' => trim((string) ($row[4] ?? '')) ?: null,
                'company_name' => trim((string) ($row[5] ?? '')) ?: null,
                'type' => trim((string) ($row[5] ?? '')) !== '' ? 'company' : 'individual',
            ];

            $client = Client::where('name', $name)->first();
            if ($client) {
                $client->update(array_filter($attrs, fn ($v) => $v !== null));
                $updated++;
            } else {
                Client::create(['name' => $name] + $attrs);
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    /** @param array<int,array<int,mixed>> $rows */
    private function importCases(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];

        $validStatuses = ['active', 'pending', 'overdue', 'closed', 'won', 'lost', 'adjudicated', 'fees_pending'];
        $defaultLawyer = User::whereIn('role', ['admin', 'lawyer'])->where('is_active', true)->value('id');
        $nextOffice = (int) (LegalCase::max(DB::raw('office_case_number + 0')) ?? 0);

        foreach ($rows as $i => $row) {
            $caseNumber = trim((string) ($row[0] ?? ''));
            $clientName = trim((string) ($row[2] ?? ''));

            if ($caseNumber === '' || $clientName === '') {
                $skipped[] = 'صف ' . ($i + 2) . ': رقم القضية أو اسم الموكل ناقص';
                continue;
            }

            if (LegalCase::where('case_number', $caseNumber)->exists()) {
                $skipped[] = 'صف ' . ($i + 2) . ': رقم القضية مكرر (' . $caseNumber . ')';
                continue;
            }

            $client = Client::firstOrCreate(['name' => $clientName], ['type' => 'individual']);

            $status = strtolower(trim((string) ($row[5] ?? 'active')));
            $nextOffice++;

            LegalCase::create([
                'case_number' => $caseNumber,
                'title' => trim((string) ($row[1] ?? '')) ?: $caseNumber,
                'client_id' => $client->id,
                'court' => trim((string) ($row[3] ?? '')) ?: 'غير محددة',
                'type' => trim((string) ($row[4] ?? '')) ?: 'مدني',
                'status' => in_array($status, $validStatuses, true) ? $status : 'active',
                'priority' => 'medium',
                'lawyer_id' => $defaultLawyer,
                'description' => 'استيراد من بيانات المكتب السابقة',
                'opponent' => trim((string) ($row[6] ?? '')) ?: '—',
                'office_case_number' => (string) $nextOffice,
                'opened_at' => now()->toDateString(),
            ]);
            $created++;
        }

        return compact('created', 'updated', 'skipped');
    }
}
