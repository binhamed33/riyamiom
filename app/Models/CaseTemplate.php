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
