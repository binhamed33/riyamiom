<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رابطُ دخولٍ أُرسل في رسالة — بصمتُه هنا، وهو في هاتف الموكّل.
 */
class ClientPortalLink extends Model
{
    protected $fillable = [
        'client_id', 'notification_id', 'token_hash',
        'target', 'target_id', 'expires_at', 'used_at', 'revoked_at', 'used_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * صالحٌ للاستعمال الآن؟
     *
     * ثلاثةُ شروطٍ لا اثنان: لم يُبطَل، ولم يُستعمل، ولم تنتهِ مدّته.
     * وإسقاطُ أيٍّ منها يجعل رابطاً في هاتفٍ مسروقٍ بعد عامٍ يفتح ملفّ
     * قضيّة.
     */
    public function usable(): bool
    {
        return $this->revoked_at === null
            && $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
