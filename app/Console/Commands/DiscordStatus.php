<?php

namespace App\Console\Commands;

use App\Services\DiscordWebhook;
use App\Services\ServerMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DiscordStatus extends Command
{
    protected $signature = 'discord:status';
    protected $description = 'Send/update server monitoring embed on Discord (edits same message)';

    public function handle(ServerMonitor $monitor, DiscordWebhook $webhook): int
    {
        $hookUrl = config('services.discord.monitor_webhook');

        // غياب إعداد اختياري ليس فشلاً.
        //
        // هذا الأمر مجدول كل خمس دقائق على كل مكتب، وDiscord مراقبةٌ
        // داخلية عند مُداوَلة لا عند العميل. فمكتبٌ بلا رابط كان يسجّل
        // خطأً كل خمس دقائق — ٢٨٨ في اليوم — حتى انتفخ السجل وانطمرت
        // الأخطاء الحقيقية تحته. فحص الصحة رأى ٣٧٣ خطأً في يوم واحد،
        // كلّها هذا، فلم يعد يميّز الجدّ من الضجيج.
        //
        // فيخمد الأمر صامتاً كما تخمد نبضة اللوحة بلا ربط.
        if (!$hookUrl) {
            $this->info('Discord monitoring not configured — skipping.');

            return Command::SUCCESS;
        }

        // المراقبة لا يجوز أن تُسقط المُجدوِل.
        //
        // الرابط مضبوط على هذه المكاتب فعلاً، والفشل بعده: gather()
        // تعتمد على shell_exec و/proc، وأيّهما قد يكون محجوباً على
        // الخادم، فيُرمى الاستثناء ويخرج الأمر بـ1 كل خمس دقائق.
        // ٤٧٤ خطأً في يوم واحد لكل مكتب طمرت تحتها أخطاء حقيقية —
        // ومنها خطأ Gemini الذي لم يُلحظ إلا اليوم.
        //
        // فحصٌ داخليٌّ عند مُداوَلة لا يستحق أن يُصنَّف ERROR في سجلّ
        // مكتب العميل: يُسجَّل تحذيراً مرّة، ويُقال سببه لمن يشغّله
        // بيده، ويخرج بنجاح.
        try {
            $data = $monitor->gather();
            $webhook->sendOrUpdate($hookUrl, $this->buildEmbed($data));

            $this->info('Server status embed updated on Discord.');
        } catch (\Throwable $e) {
            Log::warning('discord:status skipped — ' . $e->getMessage());

            $this->warn('تعذّر إرسال حالة الخادم: ' . $e->getMessage());
            $this->line('هذا فحص داخلي؛ لا يؤثّر على عمل المكتب.');
        }

        return Command::SUCCESS;
    }

    protected function buildEmbed(array $d): array
    {
        $allGreen = $d['database']['connected'];
        $color = $allGreen ? 0x00FF00 : 0xFF0000;
        $mp = $d['memory'];

        $fields = [];

        $fields[] = [
            'name' => '🖥️ Server',
            'value' => sprintf(
                "**Hostname:** `%s`\n**OS:** %s\n**Boot:** %s\n**Uptime:** %s",
                $d['system']['hostname'],
                $d['system']['os'],
                $d['system']['boot_time'],
                $d['system']['uptime']
            ),
            'inline' => false,
        ];

        $cpuVal = $d['cpu']['usage'] ?? 'N/A';
        if (isset($d['cpu']['load_1'])) {
            $cpuVal = sprintf('%s (load: %s / %s / %s)', $d['cpu']['usage'], $d['cpu']['load_1'], $d['cpu']['load_5'], $d['cpu']['load_15']);
        }
        $memPct = $mp['pct'] ?? 0;
        $memEmoji = $memPct > 85 ? '🔴' : ($memPct > 70 ? '🟡' : '🟢');
        $cpuEmoji = $memPct > 85 ? '🔴' : ($memPct > 70 ? '🟡' : '🟢');
        $fields[] = [
            'name' => '⚙️ Resources',
            'value' => sprintf(
                "%s **CPU:** %s (%s cores)\n%s **RAM:** %s MB / %s MB (**%s%%**)\n💾 **Free RAM:** %s MB",
                $cpuEmoji, $cpuVal, $d['cpu']['cores'] ?? 'N/A',
                $memEmoji, $mp['used'] ?? 'N/A', $mp['total'] ?? 'N/A', $memPct,
                $mp['free'] ?? 'N/A'
            ),
            'inline' => false,
        ];

        $diskParts = [];
        foreach ($d['disk'] as $disk) {
            $pctEmoji = $disk['pct'] > 85 ? '🔴' : ($disk['pct'] > 70 ? '🟡' : '🟢');
            $diskParts[] = sprintf("%s `%s` %s GB / %s GB (%s%%)", $pctEmoji, $disk['mount'], $disk['used'], $disk['total'], $disk['pct']);
        }
        $fields[] = [
            'name' => '💾 Disk',
            'value' => $diskParts ? implode("\n", $diskParts) : 'N/A',
            'inline' => false,
        ];

        $dbEmoji = $d['database']['connected'] ? '🟢' : '🔴';
        $fields[] = [
            'name' => '🗄️ Database',
            'value' => sprintf(
                "%s **%s** v%s\n**Host:** `%s` **DB:** `%s`\n**Size:** %s | **Connections:** %s",
                $dbEmoji,
                strtoupper($d['database']['driver']),
                $d['database']['version'] ?? 'N/A',
                $d['database']['host'] ?? 'N/A',
                $d['database']['dbName'] ?? 'N/A',
                $d['database']['size'],
                $d['database']['connections'] ?? 'N/A'
            ),
            'inline' => false,
        ];

        $app = $d['application'];
        $lar = $d['laravel'];
        $appEmoji = $d['application']['debug'] ? '⚠️' : '🔒';
        $fields[] = [
            'name' => '🚀 Application',
            'value' => sprintf(
                "%s **Laravel v%s** | `%s`\n**URL:** %s\n**Queue:** %s | **Storage:** %s\n**Cache:** cfg=%s rte=%s | **Driver:** %s",
                $appEmoji, $lar['version'], $app['env'],
                $app['url'],
                $app['queue_worker'], $app['storage_link'] ? '✅' : '❌',
                $app['config_cached'] ? '✅' : '❌', $app['route_cached'] ? '✅' : '❌',
                $lar['cache_driver']
            ),
            'inline' => false,
        ];

        $fields[] = [
            'name' => '📊 Stats',
            'value' => sprintf(
                "👥 **Users:** %s (%s active)\n⚖️ **Cases:** %s (%s active)\n📦 **Backups:** %s | **Last:** %s\n🕐 **Last Migration:** %s",
                $app['total_users'], $app['active_users'],
                $app['total_cases'], $app['active_cases'],
                $app['backup_count'], $app['last_backup'],
                $lar['last_migration']
            ),
            'inline' => false,
        ];

        $fields[] = [
            'name' => '🔧 PHP',
            'value' => sprintf(
                "**v%s** | Timezone: %s | Locale: %s\n**Extensions:** %s",
                $d['system']['php'],
                $lar['timezone'],
                $lar['locale'],
                $d['system']['php_exts']
            ),
            'inline' => false,
        ];

        return [
            'title' => '📊 Server Monitor — ' . $d['system']['hostname'],
            'description' => sprintf(
                '%s · `%s`\n🕐 **آخر تحديث:** %s',
                strtoupper($app['env']),
                $d['system']['hostname'],
                now()->format('Y-m-d H:i:s')
            ),
            'color' => $color,
            'timestamp' => $d['timestamp'],
            'footer' => ['text' => 'يتجدد كل 5 دقائق · نفس الرسالة تتعدل'],
            'fields' => $fields,
        ];
    }
}
