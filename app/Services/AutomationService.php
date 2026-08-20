<?php

namespace App\Services;

use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * محرك الأتمتة — مُداوَلة (#29)
 *
 * قواعد تشغيلية تنفَّذ بالجدولة، كل قاعدة آمنة للتكرار (لا تُنشئ
 * نسخاً مكررة). المحرك معطَّل افتراضياً ويُفعَّل من لوحة المطور
 * (Setting: automation_enabled) حتى لا يتغير سلوك المكاتب القائمة
 * إلا بقرار صريح.
 */
class AutomationService
{
    public const SESSION_PREP_DAYS = 3;
    public const STALE_CASE_DAYS = 14;

    public static function enabled(): bool
    {
        return Setting::get('automation_enabled', '0') === '1';
    }

    /** @return array<string,int> عدد ما أُنشئ لكل قاعدة */
    public function run(): array
    {
        return [
            'prep_tasks' => $this->createSessionPrepTasks(),
            'followup_tasks' => $this->createSessionFollowupTasks(),
            'stale_notices' => $this->notifyStaleCases(),
        ];
    }

    /**
     * قاعدة ١: جلسة خلال ٣ أيام بلا مهمة تحضير → أنشئ مهمة تحضير
     * للمحامي المسؤول عن القضية.
     */
    private function createSessionPrepTasks(): int
    {
        $created = 0;

        Session::with('case')
            ->whereBetween('date', [now(), now()->addDays(self::SESSION_PREP_DAYS)->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->each(function (Session $session) use (&$created) {
                $case = $session->case;
                if (!$case) {
                    return;
                }

                $title = 'تحضير جلسة ' . $session->date->format('Y-m-d') . ' — ' . $case->title;
                if (Task::where('case_id', $case->id)->where('title', $title)->exists()) {
                    return; // أُنشئت في جولة سابقة
                }

                $assignee = $case->lawyer_id ?? $this->fallbackAssignee();
                if (!$assignee) {
                    return;
                }

                Task::create([
                    'title' => $title,
                    'description' => 'مهمة تلقائية: جلسة قادمة بتاريخ ' . $session->date->format('Y-m-d H:i')
                        . ($session->location ? ' في ' . $session->location : '') . '. جهّز المذكرات والمستندات.',
                    'case_id' => $case->id,
                    'assigned_to' => $assignee,
                    'created_by' => $assignee,
                    'status' => 'pending',
                    'priority' => $session->date->isToday() || $session->date->isTomorrow() ? 'urgent' : 'high',
                    'due_date' => $session->date->copy()->subDay()->max(now()),
                ]);

                Notification::create([
                    'user_id' => $assignee,
                    'title' => 'مهمة تحضير جلسة (تلقائي)',
                    'message' => $title,
                    'type' => 'task',
                ]);

                $created++;
            });

        return $created;
    }

    /**
     * قاعدة ٢: انتهت الجلسة أمس → مهمة متابعة (تحديث الموكل، رصد
     * القرار، الخطوة التالية).
     */
    private function createSessionFollowupTasks(): int
    {
        $created = 0;

        Session::with('case')
            ->whereBetween('date', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->each(function (Session $session) use (&$created) {
                $case = $session->case;
                if (!$case) {
                    return;
                }

                $title = 'متابعة ما بعد جلسة ' . $session->date->format('Y-m-d') . ' — ' . $case->title;
                if (Task::where('case_id', $case->id)->where('title', $title)->exists()) {
                    return;
                }

                $assignee = $case->lawyer_id ?? $this->fallbackAssignee();
                if (!$assignee) {
                    return;
                }

                Task::create([
                    'title' => $title,
                    'description' => 'مهمة تلقائية: سجّل نتيجة الجلسة، حدّث ملف القضية، وأبلغ الموكل بالمستجدات.',
                    'case_id' => $case->id,
                    'assigned_to' => $assignee,
                    'created_by' => $assignee,
                    'status' => 'pending',
                    'priority' => 'high',
                    'due_date' => now()->addDay(),
                ]);

                $created++;
            });

        return $created;
    }

    /**
     * قاعدة ٣: قضية نشطة لم تُحدَّث منذ ١٤ يوماً → إشعار للإدارة
     * (مرة واحدة لكل دورة ركود — لا إزعاج يومي).
     */
    private function notifyStaleCases(): int
    {
        $notified = 0;
        $admins = $this->admins();
        if ($admins->isEmpty()) {
            return 0;
        }

        LegalCase::whereIn('status', ['active', 'pending', 'overdue'])
            ->where('updated_at', '<=', now()->subDays(self::STALE_CASE_DAYS))
            ->get()
            ->each(function (LegalCase $case) use (&$notified, $admins) {
                $marker = 'قضية راكدة #' . $case->id;

                $alreadyNotified = Notification::where('title', 'like', $marker . '%')
                    ->where('created_at', '>=', now()->subDays(self::STALE_CASE_DAYS))
                    ->exists();
                if ($alreadyNotified) {
                    return;
                }

                foreach ($admins as $admin) {
                    Notification::create([
                        'user_id' => $admin->id,
                        'title' => $marker . ' — ' . $case->title,
                        'message' => 'لم تُحدَّث هذه القضية منذ ' . self::STALE_CASE_DAYS . ' يوماً. راجع وضعها أو أعد توزيعها.',
                        'type' => 'case',
                    ]);
                }

                $notified++;
            });

        return $notified;
    }

    private function admins(): Collection
    {
        return User::whereIn('role', ['admin', 'developer'])->where('is_active', true)->get();
    }

    private function fallbackAssignee(): ?int
    {
        return User::where('role', 'admin')->where('is_active', true)->value('id');
    }
}
