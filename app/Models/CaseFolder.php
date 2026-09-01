<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** مجلد منطقي لتنظيم مستندات القضية — ويحتضن مجلداتٍ فرعية. */
class CaseFolder extends Model
{
    /**
     * أقصى عمقٍ للتفريع.
     *
     * خمسُ طبقاتٍ تكفي أعتى تنظيم («مذكرات/استئناف/2026/مسوّدات/قديم»)،
     * وما بعدها متاهةٌ يضيع فيها المستندُ لا يُنظَّم — والحدُّ يمنع
     * معها حلقةً لا تنتهي لو فسد عمودُ الأب يوماً.
     */
    public const MAX_DEPTH = 5;

    protected $fillable = ['case_id', 'parent_id', 'name', 'sort'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_folder_id');
    }

    /**
     * السلسلةُ من الجذر إليّ — لشريط «أين أنا».
     *
     * الصعودُ محدودٌ بأقصى العمق: أبوّةٌ دائريةٌ فاسدةٌ في القاعدة
     * تصير قائمةً مبتورةً تُعرض، لا حلقةً تعلّق الصفحة.
     *
     * @return array<int, self>
     */
    public function breadcrumb(): array
    {
        $trail = [$this];
        $node = $this;

        for ($i = 0; $i < self::MAX_DEPTH && $node->parent; $i++) {
            $node = $node->parent;
            array_unshift($trail, $node);
        }

        return $trail;
    }

    public function depth(): int
    {
        return count($this->breadcrumb());
    }
}
