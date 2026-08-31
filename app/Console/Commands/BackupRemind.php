<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\BackupStatus;
use App\Support\Notify;
use Illuminate\Console\Command;

/**
 * التذكير الشهري بالنسخة الاحتياطية — لمدير المكتب.
 *
 * النسخة التلقائية تحمي من عطل الخادم، لكن أعمى الثقة بها من لا
 * يتحقق: مديرٌ لا يعرف أين نسخته ولا متى أُخذت ولا كيف يستعيدها
 * يكتشف ذلك كلَّه يومَ الكارثة. التذكير الشهري يضع أمامه الحقيقة —
 * تاريخ آخر نسخة ناجحة وحجمها — ويدعوه لتنزيل نسخته الخاصة على
 * جهازه: مكانُ حفظٍ ثالث لا يملكه الخادم ولا يفنى بفنائه.
 *
 * وإن كانت النسخة متأخرة أكثر من يومين انقلب التذكير تحذيراً — فهذا
 * عطلٌ قائم لا معلومة.
 */
class BackupRemind extends Command
{
    protected $signature = 'backup:remind';
    protected $description = 'تذكير مدير المكتب شهرياً بحالة النسخة الاحتياطية وطريقة الاسترجاع';

    /** بعد كم ساعة تُعدّ النسخة متأخرة والتذكير تحذيراً */
    public const STALE_HOURS = 48;

    public function handle(): int
    {
        $lastOk = \App\Models\Setting::get(BackupStatus::KEY_LAST_OK_AT);

        $latest = storage_path('app/backups/' . DailyBackup::LATEST);
        $sizeMb = is_file($latest) ? round(filesize($latest) / 1024 / 1024, 1) : 0;

        // الاتجاه من الماضي إلى الآن — diffInHours في Carbon 3 مُوقَّع،
        // وقلبُه يجعل التأخير سالباً فلا يُكتشف أبداً
        $staleness = $lastOk ? \Carbon\Carbon::parse($lastOk)->diffInHours(now()) : PHP_INT_MAX;
        $healthy = $staleness <= self::STALE_HOURS;

        // مفاتيح لا نصوص: الإشعار يُقرأ بلغة مديره لا بلغة الخادم
        [$titleKey, $messageKey, $type] = $healthy
            ? ['app.notif_backup_remind_title', 'app.notif_backup_remind_body', 'info']
            : ['app.notif_backup_stale_title', 'app.notif_backup_stale_body', 'warning'];

        $params = [
            'date' => $lastOk ? \Carbon\Carbon::parse($lastOk)->timezone('Asia/Muscat')->format('Y-m-d H:i') : '—',
            'size' => $sizeMb,
        ];

        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            Notify::send(
                userId: $admin->id,
                titleKey: $titleKey,
                messageKey: $messageKey,
                params: $params,
                type: $type,
            );
        }

        $this->info(($healthy ? 'تذكيرُ' : 'تحذيرُ') . ' النسخة الاحتياطية أُرسل إلى ' . $admins->count() . ' مدير.');

        return self::SUCCESS;
    }
}
