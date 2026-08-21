<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * قاعدة أتمتة يبنيها المدير: متى (Trigger) + إذا (Conditions) + نفّذ (Actions).
 * تعريف المشغّلات والشروط والإجراءات المتاحة في AutomationEngine.
 */
class Automation extends Model
{
    protected $fillable = [
        'name', 'trigger', 'conditions', 'actions',
        'is_active', 'created_by', 'last_run_at', 'runs_count',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }
}
