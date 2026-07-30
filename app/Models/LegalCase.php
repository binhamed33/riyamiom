<?php

namespace App\Models;

use App\Traits\Encryptable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalCase extends Model
{
    use HasFactory, SoftDeletes, Encryptable;

    protected $table = 'cases';

    protected $encryptable = [
        'description',
        'opponent',
        'opponent_address',
        'opponent_lawyer',
        'opponent_civil_number',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pending';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CLOSED = 'closed';
    const STATUS_WON = 'won';
    const STATUS_LOST = 'lost';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'case_number',
        'office_case_number',
        'case_type',
        'title',
        'description',
        'type',
        'court',
        'opponent',
        'opponent_phone',
        'opponent_address',
        'opponent_lawyer',
        'opponent_civil_number',
        'status',
        'priority',
        'client_id',
        'lawyer_id',
    ];

    protected function casts(): array
    {
        return [
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lawyer_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'case_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id');
    }
}
