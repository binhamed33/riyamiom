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

    /** مفتاحُ عُمان — بلدُ المكاتب وموكّليها. */
    public const OMAN = '968';

    /**
     * الرقم بصيغة واتساب: أرقامٌ بمفتاح الدولة بلا «+».
     *
     * ═══ لماذا يُكمَّل بـ٩٦٨ ═══
     *
     * لأنّ الموظّف يكتب رقم الموكّل كما ينطقه: «٩١٢٣٤٥٦٧» — ثمانيةَ
     * خاناتٍ بلا مفتاح دولة، فذاك ما يُكتب في الوكالات وعلى الأوراق.
     * وواتساب لا يعرف رقماً بلا مفتاح دولة: يرفضه أو يوصله إلى بلدٍ
     * آخر. فيُكمَّل هنا مرّةً واحدة بدل أن يُطلب من كل موظّفٍ في كلّ
     * مكتبٍ أن يتذكّره.
     *
     * ═══ والصور التي تصل من الموظّفين ═══
     *
     * ‏«+968 9123 4567» · «00968 91234567» · «968-9123-4567» ·
     * ‏«091234567» · «9123 4567» — خمسُ صورٍ لرقمٍ واحد، وكلُّها تخرج
     * من هنا «96891234567».
     *
     * ═══ وما لا يُلمس ═══
     *
     * رقمٌ يحمل مفتاحَ دولةٍ أخرى (٩٧١ للإمارات، ٩٦٦ للسعودية…) يمرّ
     * كما هو: موكّلٌ مقيمٌ خارج عُمان يُراسَل على رقمه هو، ولا يُقحَم
     * عليه مفتاحُ بلدٍ ليس بلده.
     */
    public static function normalizeWaId(?string $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        // «٠٠» بادئةُ الاتصال الدولي — يليها مفتاحُ الدولة مباشرة
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // صفرُ التوجيه المحلّي: ٠٩١٢٣٤٥٦٧ ⇐ ٩١٢٣٤٥٦٧
        if (strlen($digits) === 9 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // ثمانيةُ خاناتٍ = رقمٌ عُمانيٌّ محلّي بلا مفتاح
        if (strlen($digits) === 8) {
            return self::OMAN . $digits;
        }

        return $digits;
    }

    /**
     * الرقمُ كما سيراه واتساب — للعرض في الشاشة.
     *
     * يُعرض للموظّف عند إدخال رقم الموكّل كي يرى بعينه إلى أيّ رقمٍ
     * ستذهب الرسالة قبل أن تذهب، لا أن يكتشفه من رسالةٍ وصلت غيرَه.
     */
    public static function displayWaId(?string $raw): string
    {
        $normalized = self::normalizeWaId($raw);

        return $normalized === '' ? '' : '+' . $normalized;
    }
}
