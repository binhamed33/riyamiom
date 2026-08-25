<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Suggestion;
use App\Models\Task;
use App\Models\User;
use App\Support\BackupVerifier;
use Illuminate\Console\Command;

/**
 * فحص صحّة المكتب بعد تحديث — أمر واحد يغلق فجوات التحقّق كلها.
 *
 * قراءة فقط: لا يكتب ولا يعدّل ولا يحذف شيئاً. يجيب عن الأسئلة التي
 * لا يجوز ادّعاء «تم التحديث بأمان» قبل الإجابة عنها: هل النسخة
 * الاحتياطية موجودة وسليمة؟ هل البيانات كلها في مكانها؟ هل الوظائف
 * التي كانت معطّلة تعمل على بيانات حقيقية؟ هل في السجلّ أخطاء جديدة؟
 */
class OfficeHealth extends Command
{
    protected $signature = 'office:health {--since= : فحص السجلّ منذ هذا التاريخ (Y-m-d H:i)}';

    protected $description = 'فحص شامل بعد التحديث: النسخ الاحتياطية والبيانات والوظائف والسجلّ (قراءة فقط)';

    private int $failures = 0;

    public function handle(): int
    {
        $this->line('');
        $this->components->info('فحص صحّة المكتب — ' . now()->format('Y-m-d H:i'));

        $this->checkBackups();
        $this->checkData();
        $this->checkFeatures();
        $this->checkMail();
        $this->checkLog();

        $this->line('');

        if ($this->failures === 0) {
            $this->components->info('كل الفحوص سليمة ✓');
        } else {
            $this->components->error($this->failures . ' فحص أخفق — راجع ما فوق');
        }

        return $this->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    // ---------------------------------------------------------------- النسخ

    private function checkBackups(): void
    {
        $this->section('النسخ الاحتياطية');

        $dir = storage_path('app/backups');
        $files = glob($dir . '/*.zip') ?: [];

        if ($files === []) {
            $this->bad('لا توجد أي نسخة احتياطية في ' . $dir);

            return;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $files[0];
        $ageHours = round((time() - filemtime($latest)) / 3600, 1);

        $this->ok(count($files) . ' نسخة موجودة، أحدثها: ' . basename($latest)
            . ' (' . round(filesize($latest) / 1048576, 1) . ' م.ب، منذ ' . $ageHours . ' ساعة)');

        if ($ageHours > 26) {
            $this->bad('أحدث نسخة أقدم من يوم — النسخ المجدولة لا تعمل؟');
        }

        $verify = BackupVerifier::verify($latest);

        if ($verify['ok']) {
            $this->ok('فحص الأحدث: أرشيف سليم، قاعدة البيانات داخله'
                . ($verify['tables'] > 0 ? '، ' . $verify['tables'] . ' جدولاً' : '')
                . '، ' . $verify['files'] . ' ملفاً');
        } else {
            $this->bad('أحدث نسخة لا تجتاز الفحص: ' . $verify['reason']);
        }
    }

    // -------------------------------------------------------------- البيانات

    private function checkData(): void
    {
        $this->section('البيانات (مع المحذوف حذفاً ناعماً)');

        $counts = [
            'العملاء' => Client::withTrashed()->count(),
            'القضايا' => LegalCase::withTrashed()->count(),
            'الجلسات' => Session::count(),
            'المهام' => Task::count(),
            'المستندات' => Document::count(),
            'المستخدمون' => User::count(),
            'الاقتراحات' => Suggestion::count(),
        ];

        foreach ($counts as $label => $count) {
            $this->line('      ' . str_pad($label, 14, ' ', STR_PAD_RIGHT) . ' : ' . $count);
        }

        $storageFiles = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(storage_path('app'), \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $storageFiles++;
            }
        }
        $this->line('      ' . str_pad('ملفات التخزين', 14, ' ', STR_PAD_RIGHT) . ' : ' . $storageFiles);

        // مستند مسجَّل في القاعدة وملفه مفقود من القرص = فقدان بيانات فعلي
        $missing = 0;
        Document::whereNotNull('file_path')->chunk(200, function ($docs) use (&$missing) {
            foreach ($docs as $doc) {
                $path = storage_path('app/private/' . ltrim((string) $doc->file_path, '/'));
                if (!file_exists($path) && !\Illuminate\Support\Facades\Storage::disk('private')->exists((string) $doc->file_path)) {
                    $missing++;
                }
            }
        });

        if ($missing === 0) {
            $this->ok('كل ملفات المستندات المسجَّلة موجودة على القرص');
        } else {
            $this->bad($missing . ' مستنداً مسجَّلاً في القاعدة وملفه غير موجود على القرص');
        }
    }

    // -------------------------------------------------------------- الوظائف

    private function checkFeatures(): void
    {
        $this->section('الوظائف — على أحدث بيانات حقيقية');

        $case = LegalCase::with(['client', 'lawyer', 'sessions', 'tasks', 'documents'])->latest('id')->first();

        if (!$case) {
            $this->line('      لا قضايا — تُتخطى فحوص القضية');

            return;
        }

        // العطلان اللذان كانا يضربان كل قضية: ملف PDF والتصدير
        try {
            $html = view('pdf.case-file', ['case' => $case, 'title' => 'ملف القضية'])->render();
            $this->ok('ملف القضية PDF يُبنى (' . strlen($html) . ' بايت) — قضية ' . $case->case_number);
        } catch (\Throwable $e) {
            $this->bad('ملف القضية PDF ينهار: ' . $e->getMessage());
        }

        try {
            $user = User::whereIn('role', ['developer', 'admin'])->first() ?? User::first();
            (new \App\Exports\CasesExport($user))->map($case);
            $this->ok('تصدير القضايا يبني صفوفه');
        } catch (\Throwable $e) {
            $this->bad('تصدير القضايا ينهار: ' . $e->getMessage());
        }

        try {
            $client = Client::latest('id')->first();
            $client?->name;
            $client?->phone;
            $this->ok('قراءة عميل حقيقي (فكّ التشفير يعمل)');
        } catch (\Throwable $e) {
            $this->bad('قراءة عميل تنهار: ' . $e->getMessage());
        }

        $this->line('      ' . str_pad('حالة الربط باللوحة', 20, ' ', STR_PAD_RIGHT) . ': '
            . (\App\Services\PanelReporter::configured() ? 'مربوط' : 'غير مربوط (الاقتراحات تُحفظ محلياً فقط)'));
    }

    // --------------------------------------------------------------- السجلّ

    /**
     * البريد.
     *
     * الطابور المتراكم أخطرُ من الطابور المتعطّل: النظام يقول «أُرسلت»
     * والموكّل لا يستلم. وسببُه الأشهر أنّ cron لا يشغّل schedule:run،
     * فلا يعمل العامل أصلاً.
     */
    private function checkMail(): void
    {
        $this->section('البريد');

        if (!\App\Support\MailIdentity::isConfigured()) {
            $this->bad('البريد غير مضبوط — لا يصل الموكّلين شيء (السائق: ' . config('mail.default') . ')');

            return;
        }

        $this->ok('SMTP مضبوط — المُرسِل ' . \App\Support\MailIdentity::fromAddress());

        if (config('queue.default') !== 'database') {
            return;
        }

        // كل الطوابير لا طابور البريد وحده: مهمّةٌ عالقة في «default»
        // — توصيلُ اقتراح مثلاً — لا تُصدر خطأً ولا تُرى، تبقى صامتة.
        try {
            $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
            $stuck = \Illuminate\Support\Facades\DB::table('jobs')
                ->where('available_at', '<', now()->subMinutes(15)->timestamp)
                ->count();
            $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return;
        }

        if ($stuck > 0) {
            $this->bad($stuck . ' مهمّة عالقة منذ ربع ساعة — الأرجح أنّ cron لا يشغّل schedule:run');
        } elseif ($pending > 0) {
            $this->ok($pending . ' مهمّة في الطابور تنتظر دورها');
        } else {
            $this->ok('الطوابير فارغة');
        }

        if ($failed > 0) {
            $this->bad($failed . ' مهمّة أخفقت نهائياً — راجع السجلّ');
        }
    }

    private function checkLog(): void
    {
        $this->section('سجلّ الأخطاء');

        $log = storage_path('logs/laravel.log');

        if (!file_exists($log)) {
            $this->ok('لا ملف سجلّ — لا أخطاء');

            return;
        }

        $since = $this->option('since')
            ? \Illuminate\Support\Carbon::parse($this->option('since'))
            : now()->subDay();

        // نقرأ الذيل وحده — السجلّ قد يكون بعشرات الميغابايت
        $handle = fopen($log, 'r');
        $size = filesize($log);
        fseek($handle, max(0, $size - 2_000_000));
        $tail = stream_get_contents($handle);
        fclose($handle);

        $recent = [];

        foreach (explode("\n", $tail) as $line) {
            if (!str_contains($line, '.ERROR:')) {
                continue;
            }

            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)
                && \Illuminate\Support\Carbon::parse($m[1])->gte($since)) {
                $recent[] = mb_substr($line, 0, 160);
            }
        }

        if ($recent === []) {
            $this->ok('لا أخطاء منذ ' . $since->format('Y-m-d H:i'));

            return;
        }

        $this->bad(count($recent) . ' خطأ منذ ' . $since->format('Y-m-d H:i') . ' — آخرها:');

        foreach (array_slice($recent, -5) as $line) {
            $this->line('      ' . $line);
        }
    }

    // -------------------------------------------------------------- العرض

    private function section(string $title): void
    {
        $this->line('');
        $this->line('  <options=bold>' . $title . '</>');
    }

    private function ok(string $msg): void
    {
        $this->line('  <fg=green>✓</> ' . $msg);
    }

    private function bad(string $msg): void
    {
        $this->failures++;
        $this->line('  <fg=red>✗</> ' . $msg);
    }
}
