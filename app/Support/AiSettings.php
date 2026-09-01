<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * إعدادات الذكاء الاصطناعي الخاصة بهذا المكتب وحده.
 *
 * التخزين في جدول settings الخاص بقاعدة بيانات المكتب، والمفتاح مشفَّر
 * بمفتاح تطبيق هذا المكتب (APP_KEY) — فلا يقرأه مكتب آخر ولو وصل للصف.
 * لا يُعاد المفتاح الخام إلى الواجهة أبداً؛ يخرج منه قناع فقط.
 */
class AiSettings
{
    public const KEY_PROVIDER = 'ai_provider';
    public const KEY_API_KEY = 'ai_api_key';
    public const KEY_MODEL = 'ai_model';
    public const KEY_HINT = 'ai_key_hint';
    public const KEY_UPDATED = 'ai_key_updated_at';
    public const GROUP = 'ai';

    /** المزوّد المختار لهذا المكتب، مع الرجوع للافتراضي عند غياب إعداد صالح. */
    public static function provider(): string
    {
        $stored = Setting::get(self::KEY_PROVIDER);

        return self::isImplemented($stored) ? $stored : (string) config('ai.default', 'gemini');
    }

    public static function isImplemented(mixed $provider): bool
    {
        return is_string($provider)
            && $provider !== ''
            && (bool) config("ai.providers.$provider.implemented", false);
    }

    /** المزوّدون المكتوبون فعلاً — الواجهة لا تعرض غيرهم. */
    public static function availableProviders(): array
    {
        return array_filter(
            config('ai.providers', []),
            static fn ($cfg) => (bool) ($cfg['implemented'] ?? false)
        );
    }

    public static function model(): string
    {
        $model = Setting::get(self::KEY_MODEL);
        if (is_string($model) && $model !== '') {
            return $model;
        }

        return (string) config('ai.providers.' . self::provider() . '.default_model', 'gemini-flash-latest');
    }

    /**
     * المفتاح الخام — للاستخدام داخل الخادم فقط، ولا يُمرَّر إلى قالب أو استجابة.
     * يرجع إلى قيمة .env حين لم يُعِدّ المكتب مفتاحه بعد، حفاظاً على عمل
     * المكاتب القائمة كما هي دون أي تغيير في بياناتها.
     */
    public static function apiKey(): ?string
    {
        $stored = Setting::get(self::KEY_API_KEY);

        if (is_string($stored) && $stored !== '') {
            try {
                $plain = Crypt::decryptString($stored);
                if ($plain !== '') {
                    return $plain;
                }
            } catch (DecryptException) {
                // مفتاح تطبيق مختلف أو صف تالف — نتجاهله ولا نُسقط الخدمة
            }
        }

        return self::envFallbackKey();
    }

    /** مفتاح .env القديم — يبقى احتياطاً حتى يضبط المكتب مفتاحه من الإعدادات. */
    public static function envFallbackKey(): ?string
    {
        $key = config('services.gemini.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * سلسلةُ المفاتيح بالترتيب: مفتاحُ المكتب ثم المركزيُّ من .env.
     *
     * ═══ لماذا سلسلةٌ لا مفتاحٌ واحد ═══
     *
     * مكتبٌ وضع مفتاحَه المجانيَّ فنفدت حصّتُه ظهراً — والمساعدُ
     * مسوَّقٌ به فلا يجوز أن يقول «اصبر». والمفتاحُ المركزيُّ المدفوع
     * قاعدٌ في .env لا يُمسّ لأنّ مفتاحَ المكتب «يتقدّم».
     *
     * فالتقدّمُ يبقى — مفتاحُ المكتب أوّلاً احتراماً لاختياره — لكنّ
     * نفادَ حصّته لم يعد نهايةَ الطريق: يجرَّب المركزيُّ بعده في
     * الطلب نفسِه، ولا يعرف السائلُ أنّ شيئاً حدث.
     *
     * @return array<int, string>
     */
    public static function keyChain(): array
    {
        return array_values(array_unique(array_filter([
            self::apiKey(),
            self::envFallbackKey(),
        ])));
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== null;
    }

    /** هل المفتاح المستعمل مخزَّن لهذا المكتب، أم موروث من .env؟ */
    public static function usingEnvFallback(): bool
    {
        $stored = Setting::get(self::KEY_API_KEY);

        return (!is_string($stored) || $stored === '') && self::envFallbackKey() !== null;
    }

    /** قناع للعرض — لا يكشف المفتاح ولا طوله الحقيقي. */
    public static function maskedKey(): ?string
    {
        if (!self::isConfigured()) {
            return null;
        }

        $hint = Setting::get(self::KEY_HINT);
        if (is_string($hint) && $hint !== '') {
            return str_repeat('•', 20) . $hint;
        }

        $key = self::apiKey();

        return str_repeat('•', 20) . substr((string) $key, -4);
    }

    public static function updatedAt(): ?string
    {
        $value = Setting::get(self::KEY_UPDATED);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * تثبيت النموذج الذي ردّ فعلاً — شفاءٌ ذاتي.
     *
     * النماذج تتقاعد عند المزوّد بلا إذن منّا: «gemini-2.5-flash لم يعد
     * متاحاً للمفاتيح الجديدة» جاءت من Google نفسها. والاحتياطي ينقذ
     * الطلب الواحد، لكنّ المكتب يبقى يبدأ من الميّت في كل طلب — محاولة
     * فاشلة وسطرُ خطأ في السجل، إلى الأبد.
     *
     * فحين يردّ نموذجٌ غير المضبوط، يُثبَّت. لا يُمسّ المفتاح ولا
     * المزوّد — النموذج وحده.
     */
    public static function rememberWorkingModel(string $model): void
    {
        $model = trim($model);

        if ($model === '' || $model === self::model()) {
            return;
        }

        Setting::set(self::KEY_MODEL, $model, self::GROUP);

        \Illuminate\Support\Facades\Log::info(
            'النموذج المضبوط لم يعد يعمل؛ ثُبّت البديل الذي ردّ: ' . $model
        );
    }

    public static function store(string $provider, ?string $apiKey, ?string $model): void
    {
        if (self::isImplemented($provider)) {
            Setting::set(self::KEY_PROVIDER, $provider, self::GROUP);
        }

        if ($model !== null && $model !== '') {
            Setting::set(self::KEY_MODEL, $model, self::GROUP);
        }

        // مفتاح فارغ = «أبقِ المفتاح الحالي» وليس «امسحه»؛ المسح له مسار صريح
        if ($apiKey !== null && $apiKey !== '') {
            Setting::set(self::KEY_API_KEY, Crypt::encryptString($apiKey), self::GROUP);
            Setting::set(self::KEY_HINT, substr($apiKey, -4), self::GROUP);
            Setting::set(self::KEY_UPDATED, now()->toDateTimeString(), self::GROUP);
        }
    }

    /**
     * رسالة «غير مُعدّ» موجّهة حسب صلاحية المستخدم:
     * مدير المكتب يُرشد إلى الإعدادات، وغيره يُرشد إلى مدير مكتبه —
     * ولا تذكر أي ملف خادم ولا تطلب التواصل مع المطوّر.
     */
    public static function notConfiguredMessage(): string
    {
        $user = auth()->user();

        if ($user && ($user->isAdmin() || $user->isDeveloper())) {
            return 'لم يُضبط مفتاح الذكاء الاصطناعي لهذا المكتب بعد. اضبطه من: الإعدادات ← الذكاء الاصطناعي.';
        }

        return 'خدمة الذكاء الاصطناعي غير مفعّلة في هذا المكتب بعد. تواصل مع مدير المكتب لتفعيلها.';
    }

    /** يحذف مفتاح هذا المكتب فقط — لا يمسّ أي إعداد آخر. */
    public static function forgetKey(): void
    {
        Setting::where('key', self::KEY_API_KEY)->delete();
        Setting::where('key', self::KEY_HINT)->delete();
        Setting::where('key', self::KEY_UPDATED)->delete();
    }

    /**
     * يضبط مهلات هذا الطلب على العجلة التفاعليّة.
     *
     * محامٍ أمام نافذة محادثة لا ينتظر ميزانيّة المئة ثانية التي تليق
     * بمهمّةٍ خلفيّة: كانت رسالة «سؤالك محفوظ» تتأخّر نصف دقيقةٍ وأكثر
     * قبل أن تظهر — فبدا التأجيلُ نفسُه عطلاً. التغيير في config
     * المحفوظ في الذاكرة، فيخصّ هذا الطلب وحده ولا يمسّ المهامّ.
     */
    public static function interactive(): void
    {
        config([
            'ai.retry.budget_ms' => (int) config('ai.retry.interactive_budget_ms', 20000),
            'ai.http_timeout_s' => 45,
        ]);
    }
}
