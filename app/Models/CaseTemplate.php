<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * قالب قضية (#30): عند إنشاء قضية بقالب، تُنشأ مهامه تلقائياً
 * بمواعيد محسوبة من تاريخ الإنشاء.
 */
class CaseTemplate extends Model
{
    protected $fillable = ['name', 'description', 'items'];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    /** إنشاء مهام القالب لقضية جديدة */
    public function applyTo(LegalCase $case, int $creatorId): int
    {
        $created = 0;

        foreach ($this->items as $item) {
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
            $created++;
        }

        return $created;
    }
}
