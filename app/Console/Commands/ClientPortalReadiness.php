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
    protected $signature = 'portal:readiness {--list : عرض أسماء من لا يستطيعون الدخول} {--json : مخرَج آلي تقرؤه لوحة مُداوَلة}';

    protected $description = 'فحص جاهزية بوابة العملاء: من يستطيع الدخول ومن لا يستطيع ولماذا';

    public function handle(): int
    {
        $stats = $this->gather();

        if ($this->option('json')) {
            // سطر واحد تقرؤه اللوحة — لا زخرفة ولا أسماء عملاء
            $this->line(json_encode($stats['summary'], JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        return $this->render($stats);
    }

    /**
     * الأرقام وحدها. لا يخرج منها اسم عميل ولا رقم هوية — اللوحة لا
     * تحتاجهما، وما لا يُرسَل لا يُسرَّب.
     */
    private function gather(): array
    {
        // ═══ الدخولُ برمز واتساب: الجاهزُ من له رقمٌ لا يشاركه فيه موكّلٌ آخر ═══
        //
        // كان الفحصُ يسأل عن رقم الهويّة — بابٍ أُلغي. والرمزُ يُرسَل لمن يطابق
        // رقمُه موكّلاً واحداً بالضبط (PortalOtp): بلا هاتفٍ لا رمز، ورقمٌ مشتركٌ
        // بين موكّلَين لا يُراسَل (قد يدخل أحدُهما ملفَّ الآخر).
        $noPhone = 0;
        $shared = 0;
        $blocked = [];

        $sharedHashes = Client::query()->whereNotNull('phone_hash')
            ->select('phone_hash')->groupBy('phone_hash')->havingRaw('COUNT(*) > 1')
            ->pluck('phone_hash')->flip();

        Client::chunkById(200, function ($clients) use (&$noPhone, &$shared, &$blocked, $sharedHashes) {
            foreach ($clients as $client) {
                $hasPhone = $client->phone_hash !== null && mb_strlen(Client::normalizePhone($client->phone)) >= 8;
                $isShared = $hasPhone && $sharedHashes->has($client->phone_hash);

                if (!$hasPhone) {
                    $noPhone++;
                    $blocked[] = [$client->name, 'بلا رقم هاتفٍ صالح'];
                } elseif ($isShared) {
                    $shared++;
                    $blocked[] = [$client->name, 'رقمُه مسجَّلٌ لموكّلٍ آخر أيضاً'];
                }
            }
        });

        $total = Client::count();

        return [
            'blocked' => $blocked,
            'summary' => [
                'enabled' => ClientPortal::enabled(),
                'shows_sessions' => ClientPortal::showsSessions(),
                'shows_timeline' => ClientPortal::showsTimeline(),
                'shows_documents' => ClientPortal::showsDocuments(),
                'clients_total' => $total,
                'clients_ready' => $total - count($blocked),
                'clients_blocked' => count($blocked),
                // المفتاحُ باقٍ للوحةٍ تقرؤه — والهويّةُ لم تعد شرطَ دخول
                'missing_national_id' => 0,
                'missing_phone' => $noPhone,
                'shared_phone' => $shared,
                'documents_shared' => ClientPortal::showsDocuments()
                    ? Document::where('client_visible', true)->count()
                    : 0,
                'checked_at' => now()->toDateTimeString(),
            ],
        ];
    }

    private function render(array $stats): int
    {
        $s = $stats['summary'];
        $blocked = $stats['blocked'];

        $this->newLine();
        $this->line('<options=bold>بوابة العملاء — الجاهزية</>');
        $this->line(str_repeat('─', 46));

        $this->line('الحالة: ' . ($s['enabled'] ? '<fg=green>مفعّلة</>' : '<fg=yellow>معطَّلة</>'));

        foreach ([
            'الجلسات' => $s['shows_sessions'],
            'مسار القضية' => $s['shows_timeline'],
            'المستندات' => $s['shows_documents'],
        ] as $label => $on) {
            $this->line("  {$label}: " . ($on ? '<fg=green>معروضة</>' : '<fg=gray>مخفية</>'));
        }

        $this->newLine();

        $this->line("العملاء: <options=bold>{$s['clients_total']}</>");
        $this->line("  يستطيعون الدخول: <fg=green>{$s['clients_ready']}</>");

        if ($blocked) {
            $this->line('  لا يستطيعون: <fg=yellow>' . $s['clients_blocked'] . '</>');
            $this->line("    بلا رقم هاتف: {$s['missing_phone']}");
            $this->line("    رقمٌ مشترك بين موكّلَين: {$s['shared_phone']}");
            $this->newLine();
            $this->warn('هؤلاء لا يصلهم رمزُ الدخول على واتساب حتى يكون لكلٍّ منهم رقمُ هاتفٍ يخصّه في صفحة العملاء.');

            if ($this->option('list')) {
                $this->newLine();
                $this->table(['العميل', 'السبب'], $blocked);
            } else {
                $this->line('  (‏--list لعرض أسمائهم)');
            }
        }

        if ($s['shows_documents']) {
            $shared = $s['documents_shared'];
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
