<?php

namespace App\Services\Ai;

use App\Services\Automation\AutomationEngine;
use App\Support\AiSettings;

/**
 * مولّد المسودات (§2 و§11): يحوّل جملة المدير إلى مسودة قالب أو قاعدة.
 *
 * المسودة لا تُحفظ ولا تُفعَّل هنا أبداً — تُعبَّأ بها استمارة المحرِّر
 * نفسها ليراجعها المدير ويحفظها بيده. والذكاء مقيَّد بمفردات المحرك
 * الحقيقية: مشغّل غير معروف أو إجراء مخترَع يُرفضان قبل أن يصلا للشاشة.
 */
class DraftGenerator
{
    public function __construct(private readonly GeminiProvider $provider = new GeminiProvider())
    {
    }

    /** @return array{name:string,trigger:string,conditions:array,actions:array} */
    public function automationDraft(string $wish): array
    {
        $triggers = AutomationEngine::triggers();
        $fields = AutomationEngine::conditionFields();
        $operators = AutomationEngine::operators();
        $actions = AutomationEngine::actions();

        $vocab = "المشغّلات المتاحة (trigger):\n";
        foreach ($triggers as $key => $t) {
            $vocab .= "- {$key}: {$t['label']} — {$t['description']}\n";
        }
        $vocab .= "\nحقول الشروط (field): " . implode(', ', array_keys($fields));
        $vocab .= "\nالمعاملات (operator): " . implode(', ', array_keys($operators));
        $vocab .= "\nالإجراءات (type): " . implode(', ', array_keys($actions));
        $vocab .= "\nقيم assign/target المسموحة: case_lawyer, manager, task_assignee, both";
        $vocab .= "\nحالات القضية: active, pending, overdue, closed, won, lost, adjudicated, fees_pending";
        $vocab .= "\nالأولويات: low, medium, high, urgent";

        $system = <<<PROMPT
أنت مساعد داخل نظام مُداوَلة لإدارة مكاتب المحاماة. حوّل طلب المدير إلى قاعدة أتمتة.
أجب بـ JSON فقط — لا شرح ولا Markdown — بهذه البنية حرفياً:
{"name":"اسم عربي موجز","trigger":"...","conditions":[{"field":"...","operator":"...","value":"..."}],"actions":[{"type":"create_task","title":"...","priority":"high","assign":"case_lawyer","due_in_days":1}]}
لإجراء notify استخدم: {"type":"notify","target":"...","message":"..."}
التزم حرفياً بالمفردات التالية ولا تخترع غيرها:
{$vocab}
المتغيرات المتاحة في النصوص: {case} و{client} و{date}.
إن كان الطلب لا يمكن تمثيله بهذه المفردات أجب: {"error":"سبب موجز بالعربية"}
PROMPT;

        $draft = $this->ask($system, $wish);

        return $this->validateAutomation($draft);
    }

    /** @return array{name:string,description:?string,default_status:?string,items:array,checklist:array,folders:array,reminders:array} */
    public function templateDraft(string $wish): array
    {
        $system = <<<'PROMPT'
أنت مساعد داخل نظام مُداوَلة لإدارة مكاتب المحاماة في سلطنة عُمان. حوّل طلب المدير إلى قالب قضية.
أجب بـ JSON فقط — لا شرح ولا Markdown — بهذه البنية حرفياً:
{"name":"اسم القالب","description":"سطر واحد","default_status":"active","items":[{"title":"مهمة","priority":"high","days_offset":1}],"checklist":["بند تحقق"],"folders":["المستندات"],"reminders":[{"title":"تذكير","days_offset":7,"target":"lawyer"}]}
القيود: items بين 2 و8، checklist بين 2 و10، folders بين 2 و8، reminders حتى 4.
priority من: low, medium, high, urgent. target من: lawyer, manager, both.
default_status من: active, pending — أو اتركها "active".
اجعل المحتوى عملياً لمكتب محاماة عُماني، بالعربية، بلا حشو.
إن كان الطلب خارج نطاق قوالب القضايا أجب: {"error":"سبب موجز بالعربية"}
PROMPT;

        $draft = $this->ask($system, $wish);

        return $this->validateTemplate($draft);
    }

    // ------------------------------------------------------------------

    private function ask(string $system, string $wish): array
    {
        if (!AiSettings::isConfigured()) {
            throw new \RuntimeException(AiSettings::notConfiguredMessage());
        }

        $raw = $this->provider->chat([['role' => 'user', 'content' => mb_substr($wish, 0, 500)]], $system);

        if ($raw === null || trim($raw) === '') {
            throw new \RuntimeException('تعذّر توليد المسودة الآن — جرّب بعد لحظات أو اكتبها يدوياً.');
        }

        // النموذج قد يغلّف بـ ```json رغم التعليمات — نقشّر ثم نلتقط أول كائن
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($raw));
        if (!str_starts_with((string) $clean, '{')) {
            $start = strpos((string) $clean, '{');
            $end = strrpos((string) $clean, '}');
            $clean = ($start !== false && $end !== false) ? substr((string) $clean, $start, $end - $start + 1) : $clean;
        }

        $data = json_decode((string) $clean, true);

        if (!is_array($data)) {
            throw new \RuntimeException('ردّ المولد لم يكن مفهوماً — أعد المحاولة بصياغة أوضح.');
        }

        if (isset($data['error'])) {
            throw new \RuntimeException('لا يمكن تمثيل الطلب: ' . (self::text($data['error'], 200) ?: 'خارج نطاق ما يمكن بناؤه'));
        }

        return $data;
    }

    /**
     * نصٌّ من قيمةٍ لا نثق بشكلها.
     *
     * النموذج يخرج أحياناً كائناً حيث انتظرنا نصّاً ({"title":{"ar":"..."}})،
     * و(string) على مصفوفة ترمي ErrorException لا RuntimeException — فتفلت
     * من المعالج وتصير 500 ورسالةً عامة بدل «أعد الصياغة». هنا تُطرح
     * المصفوفة صراحةً بدل أن تُكسر.
     */
    private static function text(mixed $value, int $max): string
    {
        if (is_string($value)) {
            return mb_substr(trim($value), 0, $max);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    private function validateAutomation(array $d): array
    {
        $trigger = self::text($d['trigger'] ?? '', 60);
        if (!array_key_exists($trigger, AutomationEngine::triggers())) {
            throw new \RuntimeException('المولد اقترح مشغّلاً غير موجود في النظام — أعد الصياغة.');
        }

        $fields = array_keys(AutomationEngine::conditionFields());
        $ops = array_keys(AutomationEngine::operators());
        $conditions = [];
        foreach (array_slice((array) ($d['conditions'] ?? []), 0, 5) as $c) {
            if (!is_array($c) || !in_array($c['field'] ?? '', $fields, true) || !in_array($c['operator'] ?? '', $ops, true)) {
                continue;
            }
            $conditions[] = ['field' => $c['field'], 'operator' => $c['operator'], 'value' => self::text($c['value'] ?? '', 190)];
        }

        $known = array_keys(AutomationEngine::actions());
        $targets = ['case_lawyer', 'manager', 'task_assignee', 'both'];
        $actions = [];
        foreach (array_slice((array) ($d['actions'] ?? []), 0, 3) as $a) {
            if (!is_array($a) || !in_array($a['type'] ?? '', $known, true)) {
                continue;
            }
            $actions[] = [
                'type' => $a['type'],
                'title' => self::text($a['title'] ?? '', 190),
                'message' => self::text($a['message'] ?? '', 300),
                'priority' => in_array($a['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $a['priority'] : 'high',
                'assign' => in_array($a['assign'] ?? '', $targets, true) ? $a['assign'] : 'case_lawyer',
                'target' => in_array($a['target'] ?? '', $targets, true) ? $a['target'] : 'manager',
                // الحالة قائمةٌ مغلقة كبقية الحقول — لا تُمرَّر كما جاءت
                'status' => in_array($a['status'] ?? '', ['active', 'pending', 'overdue', 'closed', 'won', 'lost', 'adjudicated', 'fees_pending'], true)
                    ? $a['status'] : 'active',
                'due_in_days' => max(0, min(90, is_numeric($a['due_in_days'] ?? null) ? (int) $a['due_in_days'] : 1)),
            ];
        }

        if ($actions === []) {
            throw new \RuntimeException('المولد لم يقترح إجراءً صالحاً — أعد الصياغة.');
        }

        return [
            'name' => self::text($d['name'] ?? '', 100) ?: 'قاعدة مقترحة',
            'trigger' => $trigger,
            'conditions' => $conditions,
            'actions' => $actions,
        ];
    }

    private function validateTemplate(array $d): array
    {
        $items = [];
        foreach (array_slice((array) ($d['items'] ?? []), 0, 8) as $i) {
            $title = self::text(is_array($i) ? ($i['title'] ?? '') : $i, 190);
            if ($title === '') {
                continue;
            }
            $items[] = [
                'title' => $title,
                'priority' => in_array(is_array($i) ? ($i['priority'] ?? '') : '', ['low', 'medium', 'high', 'urgent'], true) ? $i['priority'] : 'medium',
                'days_offset' => max(0, min(90, is_numeric($raw = (is_array($i) ? ($i['days_offset'] ?? 1) : 1)) ? (int) $raw : 1)),
            ];
        }

        $strings = fn ($list, $max) => collect((array) $list)->take($max)
            ->map(fn ($x) => self::text(is_array($x) ? ($x['title'] ?? $x['name'] ?? '') : $x, 120))
            ->filter()->values()->all();

        $reminders = [];
        foreach (array_slice((array) ($d['reminders'] ?? []), 0, 4) as $r) {
            $title = self::text(is_array($r) ? ($r['title'] ?? '') : '', 190);
            if ($title === '') {
                continue;
            }
            $reminders[] = [
                'title' => $title,
                'days_offset' => max(0, min(180, is_numeric($r['days_offset'] ?? null) ? (int) $r['days_offset'] : 7)),
                'target' => in_array($r['target'] ?? '', ['lawyer', 'manager', 'both'], true) ? $r['target'] : 'lawyer',
            ];
        }

        if ($items === [] && $strings($d['checklist'] ?? [], 10) === []) {
            throw new \RuntimeException('المولد لم يقترح محتوى صالحاً — أعد الصياغة.');
        }

        return [
            'name' => self::text($d['name'] ?? '', 100) ?: 'قالب مقترح',
            'description' => self::text($d['description'] ?? '', 300) ?: null,
            'default_status' => in_array($d['default_status'] ?? '', ['active', 'pending'], true) ? $d['default_status'] : 'active',
            'items' => $items,
            'checklist' => $strings($d['checklist'] ?? [], 10),
            'folders' => $strings($d['folders'] ?? [], 8) ?: ['المستندات', 'المراسلات', 'الجلسات'],
            'reminders' => $reminders,
        ];
    }
}
