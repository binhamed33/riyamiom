<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * إشعارات الاشتراك داخل النظام — تصل لمدير المكتب فقط.
 *
 * المستلم يُحدَّد هنا في الـ Backend حصراً (role=admin): المحامون والموظفون
 * والعملاء لا يُنشأ لهم أي إشعار اشتراك إطلاقاً.
 */
class SubscriptionNotices extends Command
{
    protected $signature = 'subscription:notices';

    protected $description = 'إنشاء إشعارات الاشتراك (اقتراب الانتهاء/الانتهاء) لمدير المكتب فقط';

    public function handle(SubscriptionService $subscription): int
    {
        $info = $subscription->info();
        $status = $info['key'];
        $days = $info['remaining_days'];
        $endStamp = $info['end_timestamp'] ?? 'none';

        // مفتاح المرحلة الحالية — يمنع تكرار نفس الإشعار كل يوم.
        $stage = match (true) {
            $status === SubscriptionService::STATUS_EXPIRED => 'expired',
            $status === SubscriptionService::STATUS_EXPIRING && $days <= 1 => 'expiring_1',
            $status === SubscriptionService::STATUS_EXPIRING && $days <= 3 => 'expiring_3',
            $status === SubscriptionService::STATUS_EXPIRING => 'expiring_7',
            default => null,
        };

        if ($stage === null) {
            $this->info('الاشتراك سليم — لا إشعارات.');

            return self::SUCCESS;
        }

        $marker = $stage . ':' . $endStamp;
        if (Setting::get('subscription_notice_sent') === $marker) {
            $this->info('إشعار هذه المرحلة أُرسل مسبقاً.');

            return self::SUCCESS;
        }

        // مفاتيح لا نصوص: الإشعار يُقرأ بلغة مديره لا بلغة الخادم
        [$titleKey, $messageKey] = match ($stage) {
            'expired' => ['app.notif_sub_expired_title', 'app.notif_sub_expired_body'],
            'expiring_1' => ['app.notif_sub_tomorrow_title', 'app.notif_sub_tomorrow_body'],
            'expiring_3' => ['app.notif_sub_soon_title', 'app.notif_sub_soon_body'],
            default => ['app.notif_sub_soon_title', 'app.notif_sub_early_body'],
        };

        $params = ['days' => $days];
        $title = __($titleKey, $params, config('app.locale'));

        // المستلمون: مدير المكتب فقط.
        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            \App\Support\Notify::send(
                userId: $admin->id,
                titleKey: $titleKey,
                messageKey: $messageKey,
                params: $params,
                type: 'subscription',
            );
        }

        Setting::set('subscription_notice_sent', $marker, 'subscription');
        $this->info('أُرسل إشعار «' . $title . '» إلى ' . $admins->count() . ' مدير.');

        return self::SUCCESS;
    }
}
