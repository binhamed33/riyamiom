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

        [$title, $message] = match ($stage) {
            'expired' => [
                'انتهى اشتراك المكتب',
                'انتهى اشتراك مُداوَلة وتم إيقاف النظام مؤقتاً. بياناتكم محفوظة بالكامل — جدّدوا الاشتراك ليعود المكتب للعمل فوراً.',
            ],
            'expiring_1' => [
                'اشتراك المكتب ينتهي غداً',
                'يتبقى يوم واحد على انتهاء اشتراك مُداوَلة. جدّدوا اليوم لتفادي توقف النظام.',
            ],
            'expiring_3' => [
                'اشتراك المكتب ينتهي خلال ' . $days . ' أيام',
                'اقترب انتهاء اشتراك مُداوَلة. يُنصح بالتجديد الآن لضمان استمرار العمل دون انقطاع.',
            ],
            default => [
                'اشتراك المكتب ينتهي خلال ' . $days . ' أيام',
                'هذا تذكير مبكر بموعد تجديد اشتراك مُداوَلة.',
            ],
        };

        // المستلمون: مدير المكتب فقط.
        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => $title,
                'message' => $message,
                'type' => 'subscription',
            ]);
        }

        Setting::set('subscription_notice_sent', $marker, 'subscription');
        $this->info('أُرسل إشعار «' . $title . '» إلى ' . $admins->count() . ' مدير.');

        return self::SUCCESS;
    }
}
