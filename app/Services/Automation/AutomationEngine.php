<?php

namespace App\Services\Automation;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\CaseActivity;
use App\Models\CaseReminder;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * محرك الأتمتة — مُداوَلة
 *
 * قواعد يبنيها المدير من الواجهة: متى (Trigger) + إذا (Conditions) + نفّذ (Actions).
 * التعريفات كلها في سجلات ثابتة قابلة للتوسعة (triggers / conditionFields / actions)
 * فلا حاجة لإعادة كتابة المحرك عند إضافة شرط أو إجراء جديد.
 *
 * ضمانات السلامة:
 *  - dedupe_key فريد: نفس القاعدة لا تنفَّذ مرتين على نفس الموضوع (Idempotency).
 *  - لا يوجد Trigger يستمع لنتائج الإجراءات (لا حلقات لا نهائية).
 *  - حد أقصى للمواضيع لكل قاعدة ولكل دورة تشغيل.
 *  - حد إعادة المحاولة: بعد 3 إخفاقات على نفس الموضوع يُتخطى نهائياً.
 *  - كل تشغيل (نجاح/فشل/تخطٍّ) يُسجَّل في automation_runs — لا فشل صامت.
 */
class AutomationEngine
{
    /** أقصى عدد مواضيع تعالجها قاعدة واحدة في الدورة */
    public const MAX_SUBJECTS_PER_RULE = 200;

    /** أقصى عدد إجراءات داخل القاعدة الواحدة */
    public const MAX_ACTIONS_PER_RULE = 10;

    /** حد إعادة المحاولة بعد الفشل */
    public const MAX_FAILURES = 3;

    public static function enabled(): bool
    {
        return Setting::get('automation_enabled', '0') === '1';
    }

    // ------------------------------------------------------------------
    // السجلات (Registry) — التوسعة تكون هنا فقط
    // ------------------------------------------------------------------

    /** @return array<string,array{label:string,subject:string,scheduled:bool,description:string}> */
    public static function triggers(): array
    {
        return [
            'session_approaching' => [
                'label' => 'جلسة تقترب',
                'subject' => 'session',
                'scheduled' => true,
                'description' => 'جلسة قادمة خلال الأيام المحددة في الشروط (الافتراضي: 3 أيام)',
            ],
            'session_completed' => [
                'label' => 'انتهت جلسة',
                'subject' => 'session',
                'scheduled' => true,
                'description' => 'جلسة انعقد موعدها أمس (غير ملغاة)',
            ],
            'case_stale' => [
                'label' => 'قضية بلا تحديث',
                'subject' => 'case',
                'scheduled' => true,
                'description' => 'قضية نشطة لم تُحدَّث منذ المدة المحددة في الشروط (الافتراضي: 14 يوماً)',
            ],
            'task_due_soon' => [
                'label' => 'مهمة يقترب استحقاقها',
                'subject' => 'task',
                'scheduled' => true,
                'description' => 'مهمة غير منجزة يحل موعدها خلال المدة المحددة (الافتراضي: يومان)',
            ],
            'task_overdue' => [
                'label' => 'مهمة متأخرة',
                'subject' => 'task',
                'scheduled' => true,
                'description' => 'مهمة غير منجزة تجاوزت موعد استحقاقها',
            ],
            'case_status_changed' => [
                'label' => 'تغيّرت حالة القضية',
                'subject' => 'case',
                'scheduled' => false,
                'description' => 'تنفَّذ لحظة تغيير حالة القضية — قيّدها بشرط «حالة القضية» لتستهدف حالة بعينها (مثل الإغلاق)',
            ],
            'case_created' => [
                'label' => 'أُنشئت قضية جديدة',
                'subject' => 'case',
                'scheduled' => false,
                'description' => 'تنفَّذ لحظة إنشاء قضية جديدة',
            ],
            'client_created' => [
                'label' => 'أُضيف عميل جديد',
                'subject' => 'client',
                'scheduled' => false,
                'description' => 'تنفَّذ لحظة إضافة عميل جديد',
            ],
        ];
    }

    /**
     * حقول الشروط المتاحة لكل نوع موضوع.
     * @return array<string,array{label:string,type:string,subjects:array,options?:array}>
     */
    public static function conditionFields(): array
    {
        $caseStatuses = [
            'active' => 'نشطة', 'pending' => 'قيد المتابعة', 'overdue' => 'متأخرة',
            'closed' => 'مغلقة', 'won' => 'مكسوبة', 'lost' => 'مخسورة',
            'adjudicated' => 'مفصولة', 'fees_pending' => 'أتعاب معلقة',
        ];

        return [
            'case_status' => ['label' => 'حالة القضية', 'type' => 'select', 'subjects' => ['case', 'session', 'task'], 'options' => $caseStatuses],
            'case_priority' => ['label' => 'أولوية القضية', 'type' => 'select', 'subjects' => ['case', 'session', 'task'], 'options' => ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة']],
            'case_type' => ['label' => 'نوع القضية', 'type' => 'text', 'subjects' => ['case', 'session', 'task']],
            'court' => ['label' => 'المحكمة', 'type' => 'text', 'subjects' => ['case', 'session']],
            'lawyer_id' => ['label' => 'المحامي المسؤول', 'type' => 'user', 'subjects' => ['case', 'session', 'task']],
            'client_type' => ['label' => 'نوع العميل', 'type' => 'select', 'subjects' => ['case', 'session', 'client'], 'options' => ['individual' => 'فرد', 'company' => 'شركة']],
            'days_until_session' => ['label' => 'أيام حتى الجلسة', 'type' => 'number', 'subjects' => ['session']],
            'days_since_update' => ['label' => 'أيام منذ آخر تحديث', 'type' => 'number', 'subjects' => ['case']],
            'days_until_due' => ['label' => 'أيام حتى الاستحقاق', 'type' => 'number', 'subjects' => ['task']],
            'task_priority' => ['label' => 'أولوية المهمة', 'type' => 'select', 'subjects' => ['task'], 'options' => ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة']],
        ];
    }

    public static function operators(): array
    {
        return [
            'equals' => 'يساوي',
            'not_equals' => 'لا يساوي',
            'gte' => 'أكبر أو يساوي',
            'lte' => 'أصغر أو يساوي',
        ];
    }

    /** @return array<string,array{label:string,params:array}> */
    public static function actions(): array
    {
        return [
            'create_task' => [
                'label' => 'إنشاء مهمة',
                'params' => ['title' => 'عنوان المهمة', 'priority' => 'الأولوية', 'assign' => 'إسناد إلى', 'due_in_days' => 'الاستحقاق بعد (أيام)'],
            ],
            'notify' => [
                'label' => 'إرسال إشعار داخلي',
                'params' => ['target' => 'المستهدف', 'message' => 'نص الإشعار'],
            ],
            'add_timeline_event' => [
                'label' => 'إضافة حدث للخط الزمني',
                'params' => ['title' => 'نص الحدث'],
            ],
            'change_case_status' => [
                'label' => 'تغيير حالة القضية',
                'params' => ['status' => 'الحالة الجديدة'],
            ],
            'create_reminder' => [
                'label' => 'إنشاء تذكير',
                'params' => ['title' => 'نص التذكير', 'due_in_days' => 'بعد (أيام)', 'target' => 'المستهدف'],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // التشغيل
    // ------------------------------------------------------------------

    /** الدورة المجدولة (كل ساعة): القواعد الزمنية + تذكيرات القوالب. */
    public function runScheduled(): array
    {
        $stats = ['executed' => 0, 'skipped' => 0, 'failed' => 0, 'reminders' => 0];

        $rules = Automation::where('is_active', true)->get()
            ->filter(fn ($r) => (self::triggers()[$r->trigger]['scheduled'] ?? false));

        foreach ($rules as $rule) {
            $result = $this->runRule($rule);
            $stats['executed'] += $result['executed'];
            $stats['skipped'] += $result['skipped'];
            $stats['failed'] += $result['failed'];
        }

        $stats['reminders'] = $this->processDueReminders();

        return $stats;
    }

    /** تنفيذ قاعدة واحدة على كل مواضيعها المرشحة. */
    public function runRule(Automation $rule): array
    {
        $result = ['executed' => 0, 'skipped' => 0, 'failed' => 0];

        $subjects = $this->candidates($rule)->take(self::MAX_SUBJECTS_PER_RULE);

        foreach ($subjects as $subject) {
            if (!$this->matchesConditions($rule, $subject)) {
                continue;
            }
            $status = $this->executeOn($rule, $subject);
            $result[$status === AutomationRun::STATUS_SUCCESS ? 'executed'
                : ($status === AutomationRun::STATUS_FAILED ? 'failed' : 'skipped')]++;
        }

        $rule->forceFill([
            'last_run_at' => now(),
            'runs_count' => $rule->runs_count + $result['executed'],
        ])->save();

        return $result;
    }

    /** تشغيل لحظي عند حدث (إنشاء قضية/عميل). لا يكسر العملية الأصلية أبداً. */
    public static function fire(string $trigger, Model $subject): void
    {
        try {
            if (!self::enabled()) {
                return;
            }

            $engine = new self();
            Automation::where('is_active', true)->where('trigger', $trigger)->get()
                ->each(function (Automation $rule) use ($engine, $subject) {
                    if ($engine->matchesConditions($rule, $subject)) {
                        $engine->executeOn($rule, $subject);
                    }
                });
        } catch (\Throwable $e) {
            logger()->error('Automation fire failed (' . $trigger . '): ' . $e->getMessage());
        }
    }

    /** وضع الاختبار: كم موضوعاً سيطابق القاعدة الآن؟ (بلا أي تنفيذ) */
    public function dryRun(Automation $rule): int
    {
        return $this->candidates($rule)
            ->filter(fn ($s) => $this->matchesConditions($rule, $s))
            ->count();
    }

    // ------------------------------------------------------------------
    // المواضيع المرشحة لكل Trigger
    // ------------------------------------------------------------------

    private function candidates(Automation $rule): Collection
    {
        return match ($rule->trigger) {
            'session_approaching' => Session::with('case.client')
                ->whereBetween('date', [now(), now()->addDays($this->conditionValue($rule, 'days_until_session', 3))->endOfDay()])
                ->where('status', '!=', 'cancelled')
                ->get(),
            'session_completed' => Session::with('case.client')
                ->whereBetween('date', [now()->subDays(2)->startOfDay(), now()])
                ->where('status', '!=', 'cancelled')
                ->get(),
            'case_stale' => LegalCase::with('client')
                ->whereIn('status', ['active', 'pending', 'overdue'])
                ->where('updated_at', '<=', now()->subDays($this->conditionValue($rule, 'days_since_update', 14)))
                ->get(),
            'task_due_soon' => Task::with('case')
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now(), now()->addDays($this->conditionValue($rule, 'days_until_due', 2))->endOfDay()])
                ->get(),
            'task_overdue' => Task::with('case')
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->startOfDay())
                ->get(),
            default => collect(), // المشغّلات اللحظية لا تُجدول
        };
    }

    /** قيمة شرط رقمي معيّن إن وُجد (تُستخدم لتضييق نافذة البحث) */
    private function conditionValue(Automation $rule, string $field, int $default): int
    {
        foreach ((array) $rule->conditions as $c) {
            if (($c['field'] ?? '') === $field && is_numeric($c['value'] ?? null)) {
                return max(0, (int) $c['value']);
            }
        }

        return $default;
    }

    // ------------------------------------------------------------------
    // تقييم الشروط
    // ------------------------------------------------------------------

    public function matchesConditions(Automation $rule, Model $subject): bool
    {
        foreach ((array) $rule->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $expected = $condition['value'] ?? null;

            if (!$field || !array_key_exists($field, self::conditionFields())) {
                continue;
            }

            $actual = $this->fieldValue($field, $subject);

            $ok = match ($operator) {
                'equals' => (string) $actual === (string) $expected,
                'not_equals' => (string) $actual !== (string) $expected,
                'gte' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
                'lte' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
                default => false,
            };

            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    private function fieldValue(string $field, Model $subject): mixed
    {
        $case = $this->caseOf($subject);

        return match ($field) {
            'case_status' => $case?->status,
            'case_priority' => $case?->priority,
            'case_type' => $case?->case_type,
            'court' => $case?->court,
            'lawyer_id' => $case?->lawyer_id ?? ($subject instanceof Task ? $subject->assigned_to : null),
            'client_type' => $subject instanceof Client ? $subject->type : $case?->client?->type,
            'days_until_session' => $subject instanceof Session ? now()->diffInDays($subject->date, false) : null,
            'days_since_update' => $case ? (int) $case->updated_at->diffInDays(now()) : null,
            'days_until_due' => $subject instanceof Task && $subject->due_date ? now()->diffInDays($subject->due_date, false) : null,
            'task_priority' => $subject instanceof Task ? $subject->priority : null,
            default => null,
        };
    }

    private function caseOf(Model $subject): ?LegalCase
    {
        return match (true) {
            $subject instanceof LegalCase => $subject,
            $subject instanceof Session, $subject instanceof Task => $subject->case,
            default => null,
        };
    }

    // ------------------------------------------------------------------
    // التنفيذ مع ضمانات السلامة
    // ------------------------------------------------------------------

    private function executeOn(Automation $rule, Model $subject): string
    {
        $case = $this->caseOf($subject);
        $dedupe = $this->dedupeKey($rule, $subject);

        // Idempotency: نفس القاعدة على نفس الموضوع تُنفَّذ مرة واحدة.
        if (AutomationRun::where('dedupe_key', $dedupe)->exists()) {
            return AutomationRun::STATUS_SKIPPED;
        }

        // حد إعادة المحاولة بعد الفشل.
        $failures = AutomationRun::where('automation_id', $rule->id)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('status', AutomationRun::STATUS_FAILED)
            ->count();
        if ($failures >= self::MAX_FAILURES) {
            $this->log($rule, $subject, AutomationRun::STATUS_SKIPPED, 'تخطّي نهائي بعد ' . $failures . ' إخفاقات', null, $dedupe);

            return AutomationRun::STATUS_SKIPPED;
        }

        // الساعة تبدأ قبل أول إجراء وتنتهي عند التسجيل، فالمدّة هي
        // زمن العمل نفسه لا زمن الفحوص التي سبقته.
        $startedAt = hrtime(true);
        $attempts = $failures + 1;

        try {
            // كل إجراءات القاعدة وحدة واحدة: تنجح كلها أو لا يبقى أثر جزئي
            $summaries = \Illuminate\Support\Facades\DB::transaction(function () use ($rule, $subject, $case) {
                $out = [];
                foreach (array_slice((array) $rule->actions, 0, self::MAX_ACTIONS_PER_RULE) as $action) {
                    $out[] = $this->executeAction($rule, $action, $subject, $case);
                }

                return $out;
            });

            $this->log($rule, $subject, AutomationRun::STATUS_SUCCESS, implode(' • ', array_filter($summaries)), null, $dedupe, $startedAt, $attempts);

            return AutomationRun::STATUS_SUCCESS;
        } catch (QueryException $e) {
            // سباق dedupe (عاملان في نفس اللحظة) — اعتبره تخطياً
            if (str_contains($e->getMessage(), 'dedupe_key')) {
                return AutomationRun::STATUS_SKIPPED;
            }
            $this->log($rule, $subject, AutomationRun::STATUS_FAILED, null, $e->getMessage(), null, $startedAt, $attempts);

            return AutomationRun::STATUS_FAILED;
        } catch (\Throwable $e) {
            $this->log($rule, $subject, AutomationRun::STATUS_FAILED, null, $e->getMessage(), null, $startedAt, $attempts);

            return AutomationRun::STATUS_FAILED;
        }
    }

    private function executeAction(Automation $rule, array $action, Model $subject, ?LegalCase $case): string
    {
        $type = $action['type'] ?? '';

        return match ($type) {
            'create_task' => $this->actionCreateTask($rule, $action, $subject, $case),
            'notify' => $this->actionNotify($rule, $action, $subject, $case),
            'add_timeline_event' => $this->actionTimelineEvent($rule, $action, $case),
            'change_case_status' => $this->actionChangeStatus($action, $case),
            'create_reminder' => $this->actionCreateReminder($action, $case),
            default => throw new \InvalidArgumentException('إجراء غير معروف: ' . $type),
        };
    }

    private function actionCreateTask(Automation $rule, array $action, Model $subject, ?LegalCase $case): string
    {
        $assignee = $this->resolveUsers($action['assign'] ?? 'case_lawyer', $case, $subject)->first();
        if (!$assignee) {
            throw new \RuntimeException('لا يوجد مستخدم لإسناد المهمة إليه');
        }

        $title = $this->fillPlaceholders($action['title'] ?? 'مهمة تلقائية', $subject, $case);

        $task = Task::create([
            'title' => $title,
            'description' => 'أُنشئت تلقائياً بواسطة قاعدة: ' . $rule->name,
            'case_id' => $case?->id,
            'assigned_to' => $assignee->id,
            'created_by' => $rule->created_by ?? $assignee->id,
            // الفاعل الظاهر هو النظام — وcreated_by للمساءلة وحدها
            'created_via' => 'automation',
            'status' => 'pending',
            'priority' => in_array($action['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $action['priority'] : 'high',
            'due_date' => now()->addDays(max(0, (int) ($action['due_in_days'] ?? 1))),
        ]);

        \App\Support\Notify::send(
            userId: $assignee->id,
            titleKey: 'app.notif_auto_task_title',
            messageKey: 'app.notif_auto_task_body',
            params: ['task' => $title, 'rule' => $rule->name],
            type: Notification::TYPE_INFO,
            notifiableType: Task::class,
            notifiableId: $task->id,
        );

        return 'مهمة: ' . $title;
    }

    private function actionNotify(Automation $rule, array $action, Model $subject, ?LegalCase $case): string
    {
        $users = $this->resolveUsers($action['target'] ?? 'manager', $case, $subject);
        if ($users->isEmpty()) {
            throw new \RuntimeException('لا يوجد مستهدف للإشعار');
        }

        $message = $this->fillPlaceholders($action['message'] ?? 'تنبيه من الأتمتة', $subject, $case);

        foreach ($users as $user) {
            \App\Support\Notify::send(
                userId: $user->id,
                titleKey: 'app.notif_auto_rule_title',
                messageKey: 'app.notif_passthrough',
                params: ['rule' => $rule->name, 'text' => $message],
                type: Notification::TYPE_INFO,
                notifiableType: $subject->getMorphClass(),
                notifiableId: $subject->getKey(),
            );
        }

        return 'إشعار لـ ' . $users->count();
    }

    private function actionTimelineEvent(Automation $rule, array $action, ?LegalCase $case): string
    {
        if (!$case) {
            return '';
        }

        $userId = $rule->created_by
            ?? User::whereIn('role', ['admin', 'developer'])->where('is_active', true)->value('id');
        if (!$userId) {
            throw new \RuntimeException('لا يوجد مستخدم لتسجيل الحدث');
        }

        CaseActivity::create([
            'case_id' => $case->id,
            'user_id' => $userId,
            'created_via' => 'automation',
            'type' => CaseActivity::TYPE_OTHER,
            'title' => '⚙️ ' . ($action['title'] ?? $rule->name),
            'content' => 'حدث تلقائي من قاعدة: ' . $rule->name,
            'occurred_at' => now(),
        ]);

        return 'حدث خط زمني';
    }

    private function actionChangeStatus(array $action, ?LegalCase $case): string
    {
        $status = $action['status'] ?? '';
        $allowed = ['active', 'pending', 'overdue', 'closed', 'won', 'lost', 'adjudicated', 'fees_pending'];
        if (!$case || !in_array($status, $allowed, true)) {
            throw new \RuntimeException('حالة غير صالحة أو لا توجد قضية');
        }

        // يوجد الآن مشغّل يستمع لتغيير الحالة، لكن هذا الإجراء يكتب
        // بـupdate() مباشرة ولا يستدعي fire()، وfire() لا يُستدعى إلا من
        // متحكّم القضية — فلا حلقة. من يضيف نداءً جديداً لـfire() فليراجع هذا.
        $case->update(['status' => $status]);

        return 'الحالة → ' . $status;
    }

    private function actionCreateReminder(array $action, ?LegalCase $case): string
    {
        if (!$case) {
            return '';
        }

        CaseReminder::create([
            'case_id' => $case->id,
            'title' => $action['title'] ?? 'تذكير',
            'remind_at' => now()->addDays(max(0, (int) ($action['due_in_days'] ?? 1))),
            'target' => in_array($action['target'] ?? '', ['lawyer', 'manager', 'both'], true) ? $action['target'] : 'lawyer',
        ]);

        return 'تذكير';
    }

    // ------------------------------------------------------------------
    // تذكيرات القوالب المستحقة (عملية نظام مستقلة عن القواعد)
    // ------------------------------------------------------------------

    public function processDueReminders(): int
    {
        $sent = 0;

        CaseReminder::with('case')
            ->whereNull('notified_at')
            ->where('remind_at', '<=', now())
            ->limit(self::MAX_SUBJECTS_PER_RULE)
            ->get()
            ->each(function (CaseReminder $reminder) use (&$sent) {
                try {
                    $users = $this->resolveUsers($reminder->target === 'manager' ? 'manager' : ($reminder->target === 'both' ? 'both' : 'case_lawyer'), $reminder->case);
                    foreach ($users as $user) {
                        \App\Support\Notify::send(
                            userId: $user->id,
                            titleKey: 'app.notif_reminder_title',
                            messageKey: 'app.notif_reminder_body',
                            params: ['title' => $reminder->title, 'case' => $reminder->case?->title ?? ''],
                            type: Notification::TYPE_INFO,
                            notifiableType: CaseReminder::class,
                            notifiableId: $reminder->id,
                        );
                    }
                    $reminder->update(['notified_at' => now()]);

                    AutomationRun::create([
                        'automation_id' => null,
                        'trigger' => 'reminder',
                        'subject_type' => CaseReminder::class,
                        'subject_id' => $reminder->id,
                        'case_id' => $reminder->case_id,
                        'status' => AutomationRun::STATUS_SUCCESS,
                        'summary' => 'تذكير: ' . $reminder->title,
                        'dedupe_key' => 'reminder|' . $reminder->id,
                    ]);
                    $sent++;
                } catch (\Throwable $e) {
                    AutomationRun::create([
                        'automation_id' => null,
                        'trigger' => 'reminder',
                        'subject_type' => CaseReminder::class,
                        'subject_id' => $reminder->id,
                        'case_id' => $reminder->case_id,
                        'status' => AutomationRun::STATUS_FAILED,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return $sent;
    }

    // ------------------------------------------------------------------
    // أدوات مساعدة
    // ------------------------------------------------------------------

    /** @return Collection<int,User> */
    private function resolveUsers(string $target, ?LegalCase $case, ?Model $subject = null): Collection
    {
        $managers = fn () => User::whereIn('role', ['admin', 'developer'])->where('is_active', true)->get();
        $lawyer = function () use ($case, $managers) {
            if ($case?->lawyer_id) {
                $u = User::where('id', $case->lawyer_id)->where('is_active', true)->first();
                if ($u) {
                    return collect([$u]);
                }
            }

            return $managers()->take(1);
        };

        // المسؤول عن المهمة نفسها — لتنبيه «مهمتك متأخرة» صاحبَها لا مديره
        $assignee = function () use ($subject, $lawyer) {
            $id = $subject instanceof Task ? $subject->assigned_to : null;
            $u = $id ? User::where('id', $id)->where('is_active', true)->first() : null;

            return $u ? collect([$u]) : $lawyer();
        };

        return match ($target) {
            'case_lawyer' => $lawyer(),
            'manager' => $managers(),
            'task_assignee' => $assignee(),
            'both' => $lawyer()->merge($managers())->unique('id')->values(),
            default => $lawyer(),
        };
    }

    /** استبدال المتغيرات في النصوص: {case}, {client}, {date} */
    private function fillPlaceholders(string $text, Model $subject, ?LegalCase $case): string
    {
        $date = $subject instanceof Session ? $subject->date?->format('Y-m-d') : now()->format('Y-m-d');

        return str_replace(
            ['{case}', '{client}', '{date}'],
            [$case?->title ?? '—', $case?->client?->name ?? '—', $date ?? '—'],
            $text
        );
    }

    private function dedupeKey(Automation $rule, Model $subject): string
    {
        $bucket = '';

        // القضية الراكدة تعود للتنبيه بعد أي تحديث جديد ثم ركود جديد
        if ($rule->trigger === 'case_stale' && $subject instanceof LegalCase) {
            $bucket = '|' . $subject->updated_at?->timestamp;
        }

        // تغيّر الحالة حدثٌ متكرّر بطبعه: القضية تُغلق وتُفتح وتُغلق.
        // بلا هذا الدلو كان المفتاح واحداً للقضية أبداً، فتعمل القاعدة
        // مرةً في عمر القضية ثم تصمت — والقاعدة موضوعة لتعمل كل مرة.
        // التاريخ يُميّز كل تغيير، والحالة تُبقي المفتاح مقروءاً؛ وتغييران
        // في الثانية نفسها يبقيان واحداً وهو المطلوب ضد النقر المزدوج.
        if ($rule->trigger === 'case_status_changed' && $subject instanceof LegalCase) {
            $bucket = '|' . $subject->updated_at?->timestamp . '|' . $subject->status;
        }

        return sha1($rule->id . '|' . $rule->trigger . '|' . $subject->getMorphClass() . '|' . $subject->getKey() . $bucket);
    }

    private function log(
        Automation $rule,
        Model $subject,
        string $status,
        ?string $summary,
        ?string $error,
        ?string $dedupe,
        ?float $startedAt = null,
        int $attempts = 1
    ): void {
        // التوقيت يُقاس بساعة أحادية (hrtime) لا بـnow(): تعديل ساعة
        // الخادم أو التوقيت الصيفي لا يجوز أن يُنتج مدّةً سالبة.
        $durationMs = $startedAt !== null
            ? (int) round((hrtime(true) - $startedAt) / 1e6)
            : null;

        AutomationRun::create([
            'automation_id' => $rule->id,
            'trigger' => $rule->trigger,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'case_id' => $this->caseOf($subject)?->id,
            'status' => $status,
            'summary' => $summary ? mb_substr($summary, 0, 250) : null,
            'error' => $error,
            'dedupe_key' => $dedupe,
            'started_at' => $startedAt !== null ? now()->subMilliseconds((int) $durationMs) : null,
            'finished_at' => $startedAt !== null ? now() : null,
            'duration_ms' => $durationMs,
            'attempts' => $attempts,
        ]);
    }

    // ------------------------------------------------------------------
    // القواعد الجاهزة (تحل محل AutomationService القديمة بنفس السلوك)
    // ------------------------------------------------------------------

    /** ينشئ قاعدة جاهزة واحدة باسمها — لتفعيل اقتراحٍ بعينه (§12). */
    /**
     * الزرع «افحص ثم أنشئ» — ونقرتان متزامنتان كانتا تجتازان الفحص معاً
     * فتُنشآن قاعدتين متطابقتين تعملان كلتاهما (مفتاح منع التكرار يحمل
     * رقم القاعدة، فلا يمنع التوأم) — مهمّتان ونداءان لكل حدث. القفل
     * يجعل الزرع عمليةً واحدة لا يدخلها نقران.
     */
    private static function seeding(\Closure $work): mixed
    {
        try {
            return Cache::lock('automation-seed', 20)->block(8, $work);
        } catch (LockTimeoutException) {
            // ازدحام غير مألوف: الأسلم ألّا يُزرع شيء على أن يُزرع مكرّراً
            return 0;
        }
    }

    public static function seedByName(string $name, ?int $creatorId = null): bool
    {
        $def = collect(self::defaultRules())->firstWhere('name', $name);

        if (!$def) {
            return false;
        }

        return (bool) self::seeding(function () use ($def, $name, $creatorId) {
            if (Automation::where('name', $name)->exists()) {
                return false;
            }

            Automation::create($def + ['is_active' => true, 'created_by' => $creatorId]);

            return true;
        });
    }

    public static function seedDefaults(?int $creatorId = null): int
    {
        return (int) self::seeding(function () use ($creatorId) {
            $created = 0;

            foreach (self::defaultRules() as $def) {
                if (!Automation::where('name', $def['name'])->exists()) {
                    Automation::create($def + ['is_active' => true, 'created_by' => $creatorId]);
                    $created++;
                }
            }

            return $created;
        });
    }

    /** @return array<int,array> قواعد مُداوَلة الجاهزة — مصدرها الواحد */
    private static function defaultRules(): array
    {
        return [
            [
                'name' => 'تحضير الجلسات القادمة',
                'trigger' => 'session_approaching',
                'conditions' => [['field' => 'days_until_session', 'operator' => 'lte', 'value' => 3]],
                'actions' => [[
                    'type' => 'create_task', 'title' => 'تحضير جلسة {date} — {case}',
                    'priority' => 'high', 'assign' => 'case_lawyer', 'due_in_days' => 1,
                ]],
            ],
            [
                'name' => 'تذكير جلسة الغد',
                'trigger' => 'session_approaching',
                'conditions' => [['field' => 'days_until_session', 'operator' => 'lte', 'value' => 1]],
                'actions' => [[
                    'type' => 'notify', 'target' => 'case_lawyer',
                    'message' => 'جلسة غداً {date} في قضية «{case}» — تأكد من جاهزية الملف.',
                ]],
            ],
            [
                'name' => 'متابعة ما بعد الجلسة',
                'trigger' => 'session_completed',
                'conditions' => [],
                'actions' => [[
                    'type' => 'create_task', 'title' => 'متابعة ما بعد جلسة {date} — {case}',
                    'priority' => 'high', 'assign' => 'case_lawyer', 'due_in_days' => 1,
                ]],
            ],
            [
                'name' => 'تنبيه القضايا الراكدة',
                'trigger' => 'case_stale',
                'conditions' => [['field' => 'days_since_update', 'operator' => 'gte', 'value' => 14]],
                'actions' => [[
                    'type' => 'notify', 'target' => 'manager',
                    'message' => 'القضية «{case}» لم تُحدَّث منذ فترة طويلة — راجع وضعها أو أعد توزيعها.',
                ]],
            ],
            [
                'name' => 'تنبيه المهام المتأخرة',
                'trigger' => 'task_overdue',
                'conditions' => [],
                'actions' => [[
                    'type' => 'notify', 'target' => 'task_assignee',
                    'message' => 'لديك مهمة متأخرة عن موعدها في قضية «{case}» — أنجزها أو حدّث موعدها.',
                ]],
            ],
            [
                'name' => 'تذكير قرب استحقاق المهام',
                'trigger' => 'task_due_soon',
                'conditions' => [['field' => 'days_until_due', 'operator' => 'lte', 'value' => 2]],
                'actions' => [[
                    'type' => 'notify', 'target' => 'task_assignee',
                    'message' => 'مهمة تستحق خلال يومين في قضية «{case}» — رتّب وقتك لها.',
                ]],
            ],
            [
                'name' => 'استقبال القضية الجديدة',
                'trigger' => 'case_created',
                'conditions' => [],
                'actions' => [[
                    'type' => 'create_task', 'title' => 'مراجعة القضية الجديدة «{case}» وتجهيز ملفها',
                    'priority' => 'high', 'assign' => 'case_lawyer', 'due_in_days' => 1,
                ]],
            ],
            [
                'name' => 'ترحيب بالعميل الجديد',
                'trigger' => 'client_created',
                'conditions' => [],
                'actions' => [[
                    'type' => 'notify', 'target' => 'manager',
                    'message' => 'أُضيف عميل جديد: {client} — راجع بياناته ورحّب به.',
                ]],
            ],
            [
                'name' => 'توثيق تغيّر حالة القضية',
                'trigger' => 'case_status_changed',
                'conditions' => [],
                'actions' => [[
                    'type' => 'add_timeline_event', 'title' => 'تغيّرت حالة القضية — راجع الخط الزمني للتفاصيل',
                ]],
            ],
            [
                'name' => 'خطوات إغلاق القضية',
                'trigger' => 'case_status_changed',
                'conditions' => [['field' => 'case_status', 'operator' => 'equals', 'value' => 'closed']],
                'actions' => [[
                    'type' => 'create_task', 'title' => 'إغلاق ملف «{case}»: تسليم المستندات وتسوية الأتعاب وأرشفة الملف',
                    'priority' => 'medium', 'assign' => 'case_lawyer', 'due_in_days' => 3,
                ]],
            ],
        ];
    }
}
