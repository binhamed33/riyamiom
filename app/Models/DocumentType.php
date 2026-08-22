<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = ['name', 'is_active', 'sort', 'is_builtin'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_builtin' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    /** كم مستنداً يحمل هذا النوع؟ يمنع حذف نوع مستعمل. */
    public function usageCount(): int
    {
        return Document::where('doc_type', $this->name)->count();
    }

    /**
     * الأنواع الأولى التي يبدأ بها كل مكتب. المكتب يزيد عليها ويعطّل
     * ما لا يخصّه — ولا تُحذف المدمجة حتى لا تختفي من تحت مستندات
     * تحملها بالفعل.
     */
    public static function defaults(): array
    {
        return [
            'وكالة', 'توكيل', 'صحيفة دعوى', 'مذكرة', 'مذكرة دفاع',
            'لائحة اعتراضية', 'حكم', 'حكم ابتدائي', 'حكم استئناف',
            'محضر جلسة', 'قرار', 'عقد', 'عقد إيجار', 'سند', 'إقرار',
            'مراسلة', 'إشعار', 'طلب', 'تقرير', 'مستند رسمي',
            'هوية', 'جواز سفر', 'شهادة', 'إيصال', 'فاتورة', 'مرفقات', 'أخرى',
        ];
    }
}
