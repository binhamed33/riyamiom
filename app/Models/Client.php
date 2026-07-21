<?php

namespace App\Models;

use App\Traits\Encryptable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes, Encryptable;

    protected $fillable = [
        'name',
        'type',
        'phone',
        'email',
        'address',
        'national_id',
        'company_name',
        'user_id',
    ];

    protected $encryptable = [
        'phone',
        'email',
        'address',
        'national_id',
        'company_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
    }
}
