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
    const STATUS_ADJUDICATED = 'adjudicated';
    const STATUS_FEES_PENDING = 'fees_pending';

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
        'created_by',
        'opened_at',
        'ai_analysis',
    ];

    protected function casts(): array
    {
        return [
            // العمودان من نوع date في قاعدة البيانات، وستّة مواضع في الشيفرة
            // تناديهما بـ ?->format() — أي أنها كانت تنهار على نصّ. ملف
            // القضية PDF وتصدير القضايا وملخّص القضية كلّها كانت تسقط لأن
            // هذا المصفوف فارغ.
            'opened_at' => \App\Casts\TolerantDate::class,
            'next_date' => \App\Casts\TolerantDate::class,
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function aiMessages(): HasMany
    {
        return $this->hasMany(CaseAiMessage::class, 'case_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CaseActivity::class, 'case_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(CaseChecklistItem::class, 'case_id')->orderBy('sort');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(CaseFolder::class, 'case_id')->orderBy('sort');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(CaseReminder::class, 'case_id')->orderBy('remind_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'case_id');
    }
}
