<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPortalAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = ['identifier_hash', 'ip', 'step', 'succeeded', 'client_id', 'created_at'];

    protected function casts(): array
    {
        return ['succeeded' => 'boolean', 'created_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
