<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** مجلد منطقي لتنظيم مستندات القضية (يُنشأ عادة من قالب ذكي). */
class CaseFolder extends Model
{
    protected $fillable = ['case_id', 'name', 'sort'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_folder_id');
    }
}
