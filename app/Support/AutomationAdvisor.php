<?php

namespace App\Support;

use App\Models\Automation;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Setting;
use App\Models\Task;

/**
 * اقتراحات الأتمتة من نمط استخدام المكتب (§12).
 *
 * قراءةٌ صادقة لا ادّعاء ذكاء: كل اقتراح يقوم على عدّةٍ من بيانات
 * المكتب نفسه (جلسات انقضت بلا متابعة، مهام متأخرة، قضايا راكدة)،
 * ويُعرض بسببه المذكور بالأرقام. لا يُفعَّل شيء تلقائياً أبداً —
 * زر التفعيل بيد المدير، والتجاهل يُحفظ فلا يعود الاقتراح يلحّ.
 */
class AutomationAdvisor
{
    public const DISMISSED_KEY = 'automation_dismissed_suggestions';

    /** @return array<int,array{key:string,title:string,reason:string,rule:string}> */
    public static function suggestions(): array
    {
        $dismissed = self::dismissed();
        $enabledNames = Automation::pluck('name')->all();
        $out = [];

        $offer = function (string $key, string $ruleName, string $title, string $reason) use (&$out, $dismissed, $enabledNames) {
            // قاعدة موجودة (ولو معطّلة) لا تُقترح — المدير قرّر فيها قراره
            if (in_array($key, $dismissed, true) || in_array($ruleName, $enabledNames, true)) {
                return;
            }
            $out[] = ['key' => $key, 'title' => $title, 'reason' => $reason, 'rule' => $ruleName];
        };

        // جلسات انقضت الشهر الماضي بلا مهمة متابعة بعدها بيوم
        $completed = Session::whereDate('date', '<', today())
            ->whereDate('date', '>=', today()->subDays(30))
            ->where('status', '!=', 'cancelled')->count();
        if ($completed >= 3) {
            $offer(
                'post_session_followup',
                'متابعة ما بعد الجلسة',
                'مهمة متابعة تلقائية بعد كل جلسة',
                "انعقدت {$completed} جلسات خلال الشهر الماضي — قاعدةٌ واحدة تنشئ مهمة «تحديث القضية» بعد كل جلسة تلقائياً."
            );
        }

        // مهام متأخرة الآن
        $overdue = Task::where('status', '!=', 'completed')
            ->whereNotNull('due_date')->whereDate('due_date', '<', today())->count();
        if ($overdue >= 2) {
            $offer(
                'overdue_task_nudge',
                'تنبيه المهام المتأخرة',
                'تنبيه أصحاب المهام المتأخرة يومياً',
                "لديكم {$overdue} مهام متجاوزة موعدها الآن — تنبيهٌ تلقائي يصل صاحب كل مهمة بدل انتظار أن يلاحظ أحد."
            );
        }

        // قضايا نشطة راكدة 14 يوماً
        $stale = LegalCase::whereIn('status', ['active', 'pending'])
            ->where('updated_at', '<', now()->subDays(14))->count();
        if ($stale >= 2) {
            $offer(
                'stale_case_alert',
                'تنبيه القضايا الراكدة',
                'تنبيه الإدارة عن القضايا الراكدة',
                "{$stale} قضايا نشطة لم تُحدَّث منذ أسبوعين — تنبيهٌ أسبوعي للإدارة يمنع أن تُنسى قضية."
            );
        }

        // جلسات قادمة خلال أسبوع بلا قاعدة تحضير
        $upcoming = Session::whereDate('date', '>=', today())
            ->whereDate('date', '<=', today()->addDays(7))
            ->where('status', 'upcoming')->count();
        if ($upcoming >= 2) {
            $offer(
                'session_prep',
                'تحضير الجلسات القادمة',
                'مهمة تحضير قبل كل جلسة بثلاثة أيام',
                "أمامكم {$upcoming} جلسات خلال الأسبوع — قاعدةٌ تنشئ مهمة تحضير للمحامي المسؤول قبل كل جلسة بثلاثة أيام."
            );
        }

        return $out;
    }

    /** تفعيل اقتراح: تُنشأ قاعدته الجاهزة مفعَّلة — بقرار المدير الصريح. */
    public static function accept(string $key, ?int $userId = null): ?string
    {
        $suggestion = collect(self::suggestions())->firstWhere('key', $key);
        if (!$suggestion) {
            return null;
        }

        // القاعدة تأتي من نفس تعريفات seedDefaults — مصدر واحد للحقيقة
        $created = \App\Services\Automation\AutomationEngine::seedByName($suggestion['rule'], $userId);

        return $created ? $suggestion['rule'] : null;
    }

    public static function dismiss(string $key): void
    {
        $dismissed = self::dismissed();
        if (!in_array($key, $dismissed, true)) {
            $dismissed[] = $key;
            Setting::set(self::DISMISSED_KEY, json_encode($dismissed), 'automation');
        }
    }

    /** @return string[] */
    private static function dismissed(): array
    {
        $raw = Setting::get(self::DISMISSED_KEY);
        $arr = $raw ? json_decode((string) $raw, true) : [];

        return is_array($arr) ? $arr : [];
    }
}
