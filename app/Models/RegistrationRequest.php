<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
    protected $fillable = [
        'office_name',
        'contact_name',
        'phone',
        'email',
        'lawyers_count',
        'city',
        'notes',
        'status',
    ];

    protected $casts = [
        'lawyers_count' => 'integer',
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_NEW => 'جديد',
        self::STATUS_CONTACTED => 'تم التواصل',
        self::STATUS_APPROVED => 'موافق عليه',
        self::STATUS_REJECTED => 'مرفوض',
    ];

    public const STATUS_COLORS = [
        self::STATUS_NEW => 'bg-blue-100 text-blue-700 border-blue-200',
        self::STATUS_CONTACTED => 'bg-amber-100 text-amber-700 border-amber-200',
        self::STATUS_APPROVED => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        self::STATUS_REJECTED => 'bg-red-100 text-red-700 border-red-200',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getLawyersCountLabelAttribute(): string
    {
        return match ($this->lawyers_count) {
            1 => 'محامٍ واحد',
            2 => '٢ – ١٠ محامين',
            3 => '١١ – ٥٠ محاميًا',
            4 => 'أكثر من ٥٠',
            default => '—',
        };
    }
}
