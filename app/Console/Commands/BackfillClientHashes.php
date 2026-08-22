<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

/**
 * حساب بصمة رقم الهوية للعملاء القائمين.
 *
 * لا يُعدّل هذا الأمر أي بيانات قائمة: يقرأ رقم الهوية ويكتب البصمة في
 * العمود الجديد وحده. آمن للتكرار، وتخطّي عميل بلا رقم هوية.
 */
class BackfillClientHashes extends Command
{
    protected $signature = 'portal:backfill-client-hashes {--force : إعادة الحساب حتى لمن له بصمة}';

    protected $description = 'حساب بصمة رقم الهوية للعملاء ليعمل الدخول إلى بوابة العملاء';

    public function handle(): int
    {
        $done = 0;
        $skipped = 0;

        $query = Client::query()->withTrashed();

        if (!$this->option('force')) {
            $query->whereNull('national_id_hash');
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('لا شيء يحتاج حساباً — كل العملاء جاهزون.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);

        $query->chunkById(200, function ($clients) use (&$done, &$skipped, $bar) {
            foreach ($clients as $client) {
                $hash = Client::hashNationalId($client->national_id);

                if ($hash === null) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // كتابة العمود الجديد وحده — لا تمرّ على أحداث الحفظ فلا
                // يُعاد تشفير شيء ولا يتغيّر updated_at
                Client::withTrashed()->whereKey($client->id)->update(['national_id_hash' => $hash]);
                $done++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("حُسبت البصمة لـ {$done} عميلاً.");

        if ($skipped > 0) {
            $this->warn("{$skipped} عميلاً بلا رقم هوية — لا يستطيعون الدخول حتى يُسجَّل لهم رقم.");
        }

        return self::SUCCESS;
    }
}
