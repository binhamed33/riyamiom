<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    const ACCESS_ALL = 'all';
    const ACCESS_TEAM = 'team';
    const ACCESS_PRIVATE = 'private';

    protected $fillable = [
        'case_id',
        'case_folder_id',
        'uploaded_by',
        'title',
        'doc_type',
        'doc_date',
        'file_path',
        'file_type',
        'file_size',
        'access_level',
    ];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CaseFolder::class, 'case_folder_id');
    }
}
