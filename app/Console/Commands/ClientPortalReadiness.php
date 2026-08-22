<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Document;
use App\Support\ClientPortal;
use Illuminate\Console\Command;

/**
 * جاهزية بوابة العملاء في هذا المكتب.
 *
 * طريقة الدخول تغيّرت من (بريد أو هاتف) إلى (رقم هوية + آخر ٣ أرقام من
 * الهاتف). فمن لم يُسجَّل له رقم هوية أو هاتف لن يستطيع الدخول — وهذا
 * الأمر يقول للمكتب كم عميلاً في هذه الحال قبل أن يكتشفها من شكواهم.
 *
 * قراءة محضة: لا يعدّل شيئاً.
 */
class ClientPortalReadiness extends Command
{
    protected $signature = 'portal:readiness {--list : عرض أسماء من لا يستطيعون الدخول}';

    protected $description = 'فحص جاهزية بوابة العملاء: من يستطيع الدخول ومن لا يستطيع ولماذا';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>بوابة العملاء — الجاهزية</>');
        $this->line(str_repeat('─', 46));

        $this->line('الحالة: ' . (ClientPortal::enabled() ? '<fg=green>مفعّلة</>' : '<fg=yellow>معطَّلة</>'));

        foreach ([
            'الجلسات' => ClientPortal::showsSessions(),
            'مسار القضية' => ClientPortal::showsTimeline(),
            'المستندات' => ClientPortal::showsDocuments(),
        ] as $label => $on) {
            $this->line("  {$label}: " . ($on ? '<fg=green>معروضة</>' : '<fg=gray>مخفية</>'));
        }

        $this->newLine();

        $total = Client::count();
        $noId = 0;
        $noPhone = 0;
        $blocked = [];

        Client::chunkById(200, function ($clients) use (&$noId, &$noPhone, &$blocked) {
            foreach ($clients as $client) {
                $hasId = Client::hashNationalId($client->national_id) !== null;
                $digits = preg_replace('/\D+/', '', (string) $client->phone) ?? '';
                $hasPhone = strlen($digits) >= 3;

                if (!$hasId) {
                    $noId++;
                }
                if (!$hasPhone) {
                    $noPhone++;
                }
                if (!$hasId || !$hasPhone) {
                    $blocked[] = [$client->name, $hasId ? '✓' : '—', $hasPhone ? '✓' : '—'];
                }
            }
        });

        $able = $total - count($blocked);

        $this->line("العملاء: <options=bold>{$total}</>");
        $this->line("  يستطيعون الدخول: <fg=green>{$able}</>");

        if ($blocked) {
            $this->line('  لا يستطيعون: <fg=yellow>' . count($blocked) . '</>');
            $this->line("    بلا رقم هوية: {$noId}");
            $this->line("    بلا رقم هاتف: {$noPhone}");
            $this->newLine();
            $this->warn('هؤلاء لن يدخلوا حتى يُستكمل لهم رقم الهوية والهاتف من صفحة العملاء.');

            if ($this->option('list')) {
                $this->newLine();
                $this->table(['العميل', 'هوية', 'هاتف'], $blocked);
            } else {
                $this->line('  (‏--list لعرض أسمائهم)');
            }
        }

        if (ClientPortal::showsDocuments()) {
            $shared = Document::where('client_visible', true)->count();
            $this->newLine();
            $this->line("المستندات المعلَّمة للعرض: <options=bold>{$shared}</>");

            if ($shared === 0) {
                $this->line('  <fg=gray>لم يُعلَّم أي مستند بعد — لن يرى العملاء مستندات حتى تُعلَّم.</>');
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
