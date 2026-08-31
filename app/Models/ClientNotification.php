<?php

namespace App\Models;

use App\Support\ClientEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إشعارٌ للموكّل — يعيش في البوابة، وواتساب أثرٌ منه.
 */
class ClientNotification extends Model
{
    public const PENDING = 'pending';
    public const QUEUED = 'queued';
    public const SKIPPED = 'skipped';
    public const FAILED = 'failed';

    protected $fillable = [
        'client_id', 'case_id', 'type', 'title', 'body',
        'target', 'target_id', 'event_key',
        'read_at', 'notified_at', 'channel_state', 'channel_reason',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function typeLabel(): string
    {
        return ClientEvents::label((string) $this->type);
    }
}
