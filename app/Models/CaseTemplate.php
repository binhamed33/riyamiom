<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قالب قضية ذكي: عند إنشاء قضية به تُجهَّز تلقائياً —
 * مهام + قائمة تحقق + مجلدات مستندات + تذكيرات + حدث في الخط الزمني،
 * مع حالة افتراضية اختيارية. القوالب خاصة بقاعدة بيانات المكتب وحده
 * (كل مكتب في مُداوَلة له قاعدة مستقلة — لا مشاركة بين المكاتب).
 */
class CaseTemplate extends Model
{
    protected $fillable = [
        'name', 'description', 'items', 'checklist', 'folders', 'reminders',
        'default_status', 'is_active', 'usage_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'checklist' => 'array',
            'folders' => 'array',
            'reminders' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** ملخص محتوى القالب للعرض والمعاينة */
    public function summary(): array
    {
        return [
            'tasks' => count($this->items ?? []),
            'checklist' => count($this->checklist ?? []),
            'folders' => count($this->folders ?? []),
            'reminders' => count($this->reminders ?? []),
        ];
    }

    /**
     * تجهيز قضية جديدة من القالب: يعيد عدد ما أُنشئ من كل نوع.
     * @return array{tasks:int,checklist:int,folders:int,reminders:int}
     */
    /**
     * مكتبة مُداوَلة: قوالب جاهزة لأشيع أنواع القضايا في عُمان.
     *
     * تُستورد بطلب المدير لا فرضاً، وكل قالب بعدها ملك المكتب: يعدّله
     * ويعطّله ويحذفه كأي قالب كتبه بيده. الاستيراد آمن للتكرار —
     * قالب باسم موجود لا يُكتب فوقه، فتعديلات المكتب لا تضيع.
     */
    public static function seedDefaults(?int $creatorId = null): int
    {
        $folders = ['المستندات', 'المراسلات', 'الجلسات', 'المذكرات', 'الوكالات', 'المرفقات'];

        $checklist = fn (array $extra = []) => array_merge([
            'مراجعة المستندات الأساسية',
            'التحقق من بيانات الموكّل',
            'إدخال بيانات الخصم',
            'تحديد المحامي المسؤول',
        ], $extra);

        $followUps = [
            ['title' => 'مراجعة القضية بعد الفتح', 'days_offset' => 1, 'target' => 'lawyer'],
            ['title' => 'متابعة دورية للقضية', 'days_offset' => 7, 'target' => 'lawyer'],
        ];

        $defaults = [
            [
                'name' => 'قضية مدنية',
                'description' => 'التجهيز القياسي لقضية مدنية: مستندات، مذكرة، وموعد أول جلسة.',
                'items' => [
                    ['title' => 'دراسة صحيفة الدعوى وتحديد الطلبات', 'priority' => 'high', 'days_offset' => 1],
                    ['title' => 'تجهيز حافظة المستندات', 'priority' => 'medium', 'days_offset' => 3],
                    ['title' => 'إعداد المذكرة الأولى', 'priority' => 'high', 'days_offset' => 5],
                ],
                'checklist' => $checklist(['التحقق من الاختصاص والمواعيد']),
            ],
            [
                'name' => 'قضية تجارية',
                'description' => 'نزاعات العقود والشركات: تدقيق العقد وتقدير المطالبة.',
                'items' => [
                    ['title' => 'مراجعة العقد محلّ النزاع بنودَه وملاحقه', 'priority' => 'high', 'days_offset' => 1],
                    ['title' => 'حصر المطالبات وتقدير قيمتها', 'priority' => 'high', 'days_offset' => 2],
                    ['title' => 'مخاطبة الطرف الآخر أو تجهيز الدعوى', 'priority' => 'medium', 'days_offset' => 5],
                ],
                'checklist' => $checklist(['نسخة العقد وملاحقه كاملة', 'مستندات السجل التجاري للطرفين']),
            ],
            [
                'name' => 'قضية عمالية',
                'description' => 'مطالبات العمل: مستحقات، فصل تعسفي، وإجراءات دائرة العمل.',
                'items' => [
                    ['title' => 'حصر مستحقات العامل (أجور، بدلات، مكافأة نهاية خدمة)', 'priority' => 'high', 'days_offset' => 1],
                    ['title' => 'التحقق من شكوى دائرة العمل قبل المحكمة', 'priority' => 'high', 'days_offset' => 2],
                    ['title' => 'تجهيز عقد العمل وكشوف الرواتب', 'priority' => 'medium', 'days_offset' => 3],
                ],
                'checklist' => $checklist(['عقد العمل', 'إثبات مدة الخدمة وآخر أجر']),
            ],
            [
                'name' => 'قضية أحوال شخصية',
                'description' => 'مسائل الأسرة: تجهيز حسّاس يراعي السرية وأطراف العائلة.',
                'items' => [
                    ['title' => 'جلسة استماع خاصة مع الموكّل وتوثيق الوقائع', 'priority' => 'high', 'days_offset' => 1],
                    ['title' => 'تجهيز مستندات الحالة (عقود، شهادات، إثباتات)', 'priority' => 'medium', 'days_offset' => 3],
                ],
                'checklist' => $checklist(['التحقق من محاولات الصلح إن لزمت']),
            ],
            [
                'name' => 'قضية تنفيذ',
                'description' => 'تنفيذ حكم أو سند: ملف التنفيذ ومتابعة إجراءات الحجز.',
                'items' => [
                    ['title' => 'التحقق من صيغة السند التنفيذية وقابليته للتنفيذ', 'priority' => 'high', 'days_offset' => 1],
                    ['title' => 'فتح ملف التنفيذ وإيداع المستندات', 'priority' => 'high', 'days_offset' => 2],
                    ['title' => 'متابعة إجراءات الحجز والإعلان', 'priority' => 'medium', 'days_offset' => 7],
                ],
                'checklist' => $checklist(['نسخة الحكم أو السند مذيّلة بالصيغة التنفيذية']),
            ],
            [
                'name' => 'قضية مطالبة مالية',
                'description' => 'تحصيل دين أو مطالبة مالية: إثبات المديونية ثم التدرج في المطالبة.',
                'items' => [
                    ['title' => 'حصر المديونية ومستنداتها (فواتير، شيكات، إقرارات)', 'priority' => 'high', 'days_offset' => 1],
                    ['title' => 'إنذار المدين كتابياً قبل الدعوى', 'priority' => 'medium', 'days_offset' => 3],
                    ['title' => 'تجهيز صحيفة الدعوى إن لم يسدّد', 'priority' => 'medium', 'days_offset' => 10],
                ],
                'checklist' => $checklist(['إثبات المديونية موقَّعاً', 'حساب الفوائد أو الغرامات إن وُجدت']),
            ],
        ];

        $created = 0;
        foreach ($defaults as $def) {
            if (static::where('name', $def['name'])->exists()) {
                continue;
            }

            static::create($def + [
                'folders' => $folders,
                'reminders' => $followUps,
                'default_status' => 'active',
                'is_active' => true,
                'created_by' => $creatorId,
            ]);
            $created++;
        }

        return $created;
    }

    public function applyTo(LegalCase $case, int $creatorId): array
    {
        $created = ['tasks' => 0, 'checklist' => 0, 'folders' => 0, 'reminders' => 0];

        foreach ($this->items ?? [] as $item) {
            $title = trim($item['title'] ?? '');
            if ($title === '') {
                continue;
            }

            Task::create([
                'title' => $title,
                'description' => 'من قالب: ' . $this->name,
                'case_id' => $case->id,
                'assigned_to' => $case->lawyer_id ?? $creatorId,
                'created_by' => $creatorId,
                'status' => 'pending',
                'priority' => in_array($item['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true)
                    ? $item['priority'] : 'medium',
                'due_date' => now()->addDays(max(0, (int) ($item['days_offset'] ?? 0))),
            ]);
            $created['tasks']++;
        }

        foreach ($this->checklist ?? [] as $i => $item) {
            $title = trim(is_array($item) ? ($item['title'] ?? '') : (string) $item);
            if ($title === '') {
                continue;
            }
            CaseChecklistItem::create(['case_id' => $case->id, 'title' => $title, 'sort' => $i]);
            $created['checklist']++;
        }

        foreach ($this->folders ?? [] as $i => $folder) {
            $name = trim(is_array($folder) ? ($folder['name'] ?? '') : (string) $folder);
            if ($name === '') {
                continue;
            }
            CaseFolder::firstOrCreate(['case_id' => $case->id, 'name' => $name], ['sort' => $i]);
            $created['folders']++;
        }

        foreach ($this->reminders ?? [] as $reminder) {
            $title = trim($reminder['title'] ?? '');
            if ($title === '') {
                continue;
            }
            CaseReminder::create([
                'case_id' => $case->id,
                'title' => $title,
                'remind_at' => now()->addDays(max(0, (int) ($reminder['days_offset'] ?? 1)))->setTime(8, 0),
                'target' => in_array($reminder['target'] ?? '', ['lawyer', 'manager', 'both'], true)
                    ? $reminder['target'] : 'lawyer',
            ]);
            $created['reminders']++;
        }

        if ($this->default_status
            && in_array($this->default_status, ['active', 'pending', 'overdue', 'closed', 'won', 'lost', 'adjudicated', 'fees_pending'], true)) {
            $case->update(['status' => $this->default_status]);
        }

        CaseActivity::create([
            'case_id' => $case->id,
            'user_id' => $creatorId,
            'type' => CaseActivity::TYPE_OTHER,
            'title' => '📋 جُهّزت القضية من قالب «' . $this->name . '»',
            'content' => sprintf(
                'أُنشئ تلقائياً: %d مهام، %d بنود تحقق، %d مجلدات، %d تذكيرات.',
                $created['tasks'], $created['checklist'], $created['folders'], $created['reminders']
            ),
            'occurred_at' => now(),
        ]);

        $this->increment('usage_count');

        return $created;
    }
}
