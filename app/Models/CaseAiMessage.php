<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAiMessage extends Model
{
    use HasFactory;

    protected $fillable = ['case_id', 'role', 'content'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }
}
