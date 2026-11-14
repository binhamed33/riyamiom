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

        return $this->profile_name ?: \App\Support\Phone::format($this->wa_id);
    }

    /**
     * هل للجهة اسمٌ يُقرأ، أم أنّ ما تعرضه displayName() رقمُها؟
     *
     * الرقمُ المنسَّق نصٌّ يساريّ في سطرٍ عربيّ: يُرسم بلا عزلٍ مقلوبَ
     * المجموعات («1502 9000 968+») ويصير حرفُ الصورة «+». فالواجهةُ
     * تسأل هنا لتعزله وتضع رمزاً بدل الحرف — بالشرط نفسِه الذي يختار
     * به displayName() الاسمَ، فلا يفترقان.
     */
    public function hasName(): bool
    {
        return $this->client !== null || (bool) $this->profile_name;
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
    /**
     * أقصرُ رقمٍ دوليٍّ يُقبَل.
     *
     * ═══ العطل الذي وُضع له ═══
     *
     * موكّلٌ كُتب رقمُه «٩٧٧٤٧٧٤٦٨» — تسعُ خاناتٍ لرقمٍ عُمانيٍّ ثمانيّ،
     * زادت فيه خانةٌ بالخطأ. ولأنّه ليس ثمانياً لم يُكمَّل بـ٩٦٨، فمرَّ
     * كما هو. وواتساب يقرأ أوّلَ ثلاثٍ مفتاحَ دولة: ٩٧٧ — نيبال.
     *
     * فذهبت الرسالةُ إلى بلدٍ آخر ولم يُخطئ شيءٌ في النظام: تُصَفّ
     * وتُرسَل ويُقال «تمّ»، ولا تصل أحداً أبداً.
     *
     * وأقصرُ رقمٍ دوليٍّ حقيقيٍّ عشرُ خانات (مفتاحٌ من خانةٍ وتسعُ
     * خاناتٍ محلّية). فما دونها ليس رقماً دولياً بحال، وردُّه صراحةً
     * خيرٌ من إرساله إلى مجهول.
     */
    public const MIN_INTERNATIONAL = 10;

    /**
     * أيصلح هذا الرقم للإرسال؟
     *
     * تُسأل بعد التطبيع: العُمانيُّ يخرج منه إحدى عشرة خانة، والأجنبيُّ
     * عشراً فصاعداً. وما بينهما خطأُ إدخالٍ لا رقمُ بلدٍ بعيد.
     */
    public static function isSendable(?string $waId): bool
    {
        // ‏E.164 خمسَ عشرةَ خانةً على الأكثر: رقمان ملتصقان (٩١٢٣٤٥٦٧٩٩٨٨٧٧٦٦) ليسا رقماً —
        // كان يُرسَل إليهما فيردّ واتساب «ليس على واتساب» ويُكتب «أخفق» بلا سببٍ مفهوم
        $len = mb_strlen((string) $waId);

        return $len >= self::MIN_INTERNATIONAL && $len <= 15;
    }

    /**
     * كلُّ أرقام واتساب في حقل هاتف — الحقلُ قد يحمل أكثرَ من رقم.
     *
     * يفصل بينها فاصلة (، أو ,) أو فاصلةٌ منقوطة أو / أو | أو سطرٌ جديد أو « - »،
     * أو مسافةٌ بين رقمين كاملين. وكان الحقلُ كلُّه يُحوَّل رقماً واحداً فتلتصق
     * الأرقام وتذهب الرسالةُ إلى رقمٍ لا وجودَ له.
     *
     * @return list<string>
     */
    public static function waIdsOf(?string $phone): array
    {
        $ids = [];

        foreach (preg_split('/[,،;\/|\n]+/u', (string) $phone) ?: [] as $chunk) {
            // « - » فاصلٌ بين رقمين فقط إن كان كلُّ جانبٍ رقماً كاملاً (ثماني خاناتٍ
            // فأكثر): «9123 - 4567» رقمٌ واحدٌ مكتوبٌ بشرطة، وتقسيمُه أفسده
            $sides = preg_split('/\s+[-–]\s+/u', $chunk) ?: [$chunk];
            $apart = count($sides) > 1
                && array_filter($sides, fn ($s) => strlen(self::digitsOf($s)) < 8) === [];

            foreach ($apart ? $sides : [$chunk] as $part) {
                foreach (self::numbersIn($part) as $id) {
                    if ($id !== '' && !in_array($id, $ids, true)) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * مقطعٌ بلا فاصل: رقمٌ واحد، أو أرقامٌ بينها مسافاتٌ فقط.
     *
     * ما لم يتجاوز خمسَ عشرةَ خانةً فهو رقمٌ واحد («+968 9123 4567»). وما زاد
     * تُجمع مقاطعُه بالترتيب: يبدأ رقمٌ جديدٌ عند «+» أو «00»، أو عند مقطعٍ من
     * ثماني خاناتٍ بعد رقمٍ اكتمل، أو بعد كلمةٍ لا أرقامَ فيها («ext»). وكان
     * فحصُ كلِّ مقطعٍ وحده يُسقط «+968 9123 4567» كلَّه (لا مقطعَ فيه بثمانٍ)
     * ويحذف الأرقامَ الهنديّة، فيُعدّ الموكّلُ بلا رقم أو يُراسَل على رقمه الثاني.
     *
     * @return list<string>
     */
    private static function numbersIn(string $part): array
    {
        $whole = self::normalizeWaId(trim($part));

        if (strlen($whole) <= 15) {
            return [$whole];
        }

        $numbers = [];
        $buffer = '';
        $bufferIntl = false;
        $flush = function () use (&$numbers, &$buffer) {
            if ($buffer !== '') {
                $numbers[] = self::normalizeWaId($buffer);
            }
            $buffer = '';
        };

        foreach (preg_split('/\s+/u', trim($part), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $digits = self::digitsOf($token);

            if ($digits === '') {
                // كلمةٌ أو شرطةٌ وسطَ رقمٍ لم يكتمل: «9123 - 4567 9988 - 7766» —
                // وصلُ نصفٍ من هذا بنصفٍ من ذاك يصنع رقماً ثالثاً يُرسَل إليه.
                // الحقلُ ملتبس: يُترك كلُّه رقماً لا يصلح، فيُتخطّى بسببٍ مكتوب
                if ($buffer !== '' && strlen($buffer) < 8) {
                    return [$whole];
                }
                $flush();

                continue;
            }

            $international = (bool) preg_match('/^\+|^(00|٠٠|۰۰)/u', $token);

            // رقمٌ عُمانيٌّ اكتمل: ثماني خاناتٍ محلّيّة («9123 4567 9988 7766»)، أو
            // ‏968 وثمانٍ بعد «+». والمكتوبُ بمفتاح دولةٍ أخرى لا يُقطع هنا
            // («+966 51 234 5678» ثماني خاناتٍ في منتصفه)
            $complete = $bufferIntl
                ? (bool) preg_match('/^(00)?968\d{8}$/', $buffer)
                : strlen($buffer) === 8;

            if ($buffer !== '' && ($international || $complete || (strlen($digits) >= 8 && strlen($buffer) >= 8))) {
                $flush();
            }

            if ($buffer === '') {
                $bufferIntl = $international;
            }

            // «00» في أوّل الرقم تبقى: normalizeWaId يقرؤها بادئةً دوليّة
            $buffer .= $digits;
        }

        $flush();

        return $numbers;
    }

    /** الخاناتُ وحدها — بعد تحويل الأرقام الهنديّة والفارسيّة. */
    private static function digitsOf(?string $raw): string
    {
        return preg_replace('/\D+/', '', strtr((string) $raw, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ])) ?? '';
    }

    /** الرقمُ الذي تُرسَل إليه رسائلُ الموكّل: أوّلُ رقمٍ صالحٍ في حقله — أو أوّلُها إن لم يصلح أحد. */
    public static function primaryWaId(?string $phone): string
    {
        $ids = self::waIdsOf($phone);

        foreach ($ids as $id) {
            if (self::isSendable($id)) {
                return $id;
            }
        }

        return $ids[0] ?? '';
    }

    public static function normalizeWaId(?string $raw): string
    {
        // الأرقامُ الهنديّة (٩١٢…) تُحوَّل قبل حذف غير الأرقام — وإلا حُذفت كلُّها
        // وأُرسلت الرسالةُ إلى رقمٍ فارغ
        $digits = self::digitsOf($raw);

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
