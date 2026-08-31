<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * جهةُ اتصال واتساب — رقمٌ راسل هذا المكتب أو راسله المكتب.
 *
 * قد يكون موكّلاً معروفاً (client_id) وقد يكون مستفسراً لم يُقيَّد بعد.
 * والربطُ بالموكّل ليس شرطاً للمحادثة: من يمنع الردَّ على من لم يُسجَّل
 * بعدُ يمنع أوّل اتصالٍ بكل موكّلٍ جديد.
 */
class WhatsAppContact extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'wa_id',
        'profile_name',
        'client_id',
        'opted_in_at',
        'opted_out_at',
    ];

    protected function casts(): array
    {
        return [
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(WhatsAppConversation::class, 'contact_id');
    }

    /** الاسم المعروض: اسمُ الموكّل إن رُبط، وإلا اسمُ ملفّه في واتساب. */
    public function displayName(): string
    {
        if ($this->relationLoaded('client') ? $this->client : $this->client()->first()) {
            return (string) $this->client->name;
        }

        return $this->profile_name ?: \App\Support\GulfPhone::format($this->wa_id);
    }

    /**
     * هل يجوز أن يصله إشعارُ نظام؟
     *
     * الرفضُ الصريح يُحترم دائماً — لا إعدادَ مكتبٍ يتجاوزه. ومن لم
     * يقل شيئاً فمراسلتُه مقبولة: الموكّل الذي أعطى مكتبه رقمه توقّع
     * أن يُراسَل فيه، وهذا هو مفهوم Meta للموافقة الضمنيّة.
     */
    public function acceptsNotifications(): bool
    {
        return $this->opted_out_at === null;
    }

    /**
     * الرقم بصيغة واتساب: أرقامٌ بمفتاح الدولة بلا «+».
     * الرقمُ المحلّي يُكمَّل بمفتاح عُمان — بلد المنصّة وموكّليها.
     */
    public static function normalizeWaId(?string $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 8) {
            return '968' . $digits;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '0')) {
            return '968' . substr($digits, 1);
        }

        return $digits;
    }
}
