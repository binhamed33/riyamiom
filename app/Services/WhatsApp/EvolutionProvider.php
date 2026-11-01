<?php

namespace App\Services\WhatsApp;

use App\Support\WhatsAppSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * جسرُ واتساب ويب — يُربط بمسح رمزٍ في ثوانٍ.
 *
 * ═══ ما هو، وما ثمنه ═══
 *
 * Evolution API خادمٌ يتحدّث بروتوكولَ «واتساب ويب» نيابةً عن هاتف.
 * فما يقدر عليه الهاتفُ يقدر عليه: يرسل لأيّ رقمٍ في أيّ وقت، بلا
 * نافذةِ أربعٍ وعشرين ساعة وبلا قوالبَ معتمَدة وبلا تطبيقٍ عند Meta.
 * والربطُ مسحُ رمزٍ من الهاتف، كما يُربط واتساب ويب تماماً.
 *
 * وثمنُه أنّه يخالف شروط Meta: الرقمُ المستعمَل عبره قد يُحظر بلا
 * إنذارٍ ولا استرجاع. وذلك قرارُ صاحب النظام لا قرارُ الكود — وقد
 * اتُّخذ بعد بيانِه.
 *
 * ═══ لماذا لم يتغيّر شيءٌ غيرُ هذا الملف ═══
 *
 * لأنّ الواجهة `WhatsAppProviderInterface` كُتبت من أوّل يوم. فصندوقُ
 * الوارد والطابور والجدولة والصلاحيات والردُّ الآلي لا تعرف Meta ولا
 * Evolution — تعرف العقدَ وحده.
 */
class EvolutionProvider implements WhatsAppProviderInterface
{
    protected ?string $lastError = null;

    protected string $baseUrl;
    protected string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('whatsapp.evolution.base_url', ''), '/');
        $this->apiKey = $apiKey ?? (string) config('whatsapp.evolution.api_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    // ── الاقتران ─────────────────────────────────────────────────

    /** المدّةُ التي يُعدّ فيها المكتبُ «يقترن الآن» فيكفّ الكنسُ والإصلاحُ عن نسخته. */
    public const PAIRING_WINDOW_MIN = 6;

    /**
     * لا تُبدأ خطوةٌ جديدةٌ مع الجسر بعد هذه الثواني من الطلب.
     *
     * ‏nginx يقطع الطلبَ عند ستّين ثانية فيرى المكتبُ «تعذّر الاتصال»، وPHP يمضي
     * (مهلةُ التنفيذ تعدّ وقتَ المعالج لا الانتظار) يحذف وينشئ والمكتبُ يضغط ثانية.
     * فجسرٌ عالقٌ يُقال عنه بعد أربعين ثانية بدل أن يُترك الطلبُ يتجاوز الستّين.
     */
    /**
     * موعدُ الطلب كلِّه: ثلاثون ثانية من الضغطة إلى الردّ.
     *
     * الخادمُ الوسيط (nginx) يقطع ما طال عن دقيقة بصفحة ٥٠٤، وCloudflare بعد مئة
     * ثانية — ولا يصل المكتبَ حينها رمزٌ ولا سبب، بل «جارٍ التحضير» بلا نهاية.
     * فكلُّ نداءٍ للجسر يُمهَل ما بقي من الموعد لا أكثر (within)، وما لم يُنجَز
     * قبله يُقال بخطواته (trace).
     */
    public const PAIR_BUDGET_S = 30;

    protected float $pairDeadline = INF;

    /** لماذا لم يُجب الجسرُ عن الحالة في آخر سؤال — null إن أجاب (٢٠٠). */
    protected ?string $stateFailure = null;

    /** وأجاب عن الحالة بأنّه لا يعرف النسخة (٤٠٤ من حارسه) — جوابٌ صريحٌ لا صمت. */
    protected bool $stateMissing = false;

    /** ولماذا لم يُجب عن صفّ النسخة — null إن أجاب أو أقرّ أنّه لا يعرفها. */
    protected ?string $lookupFailure = null;

    /**
     * رحلةُ آخر طلبٍ خطوةً خطوة («الخطوة: الردّ (المدّة)») — تُعاد إلى الشاشة مع
     * الإخفاق وتُكتب في السجلّ: صورةٌ منها تكفي لمعرفة أيَّ نداءٍ أبطأ أو أخفق.
     *
     * @var list<string>
     */
    protected array $trace = [];

    /** الصفُّ لم يُجب في مهلته (لا ٥٠٠ ولا ٤٠٤): قد يكون هناك — مقترناً — وقاعدةُ الجسر بطيئة. */
    protected bool $lookupUnanswered = false;

    /** ما يُترك للإنشاء بعد كلّ ما قبله: الجسرُ يُمسكه خمسَ ثوانٍ ينتظر أوّلَ رمز. */
    protected const CREATE_RESERVE_S = 8;

    /**
     * رمزُ اقترانٍ جديد — رمزُ مسحٍ، أو رمزُ ربطٍ بالرقم إن مُرِّر رقم.
     *
     * ═══ لماذا جلسةٌ جديدةٌ في كلّ طلب ═══
     *
     * من مصدر Evolution 2.3.7 وBaileys 7.0.0-rc.9 (instance.controller.ts:309-331،
     * whatsapp.baileys.service.ts:336-417):
     *
     * - جلسةٌ تنتظر المسح («connecting») يعيد لها «connect» رمزَها المخزَّن كما
     *   هو ويُهمل الرقم. فكان «اطلب رمزاً» يعود برمز المسح القديم أو بلا شيء،
     *   ولا يُطلب من واتساب رمزُ ربطٍ قطّ. والرقمُ لا يُعتمد إلا في جلسةٍ تُفتح.
     * - عدّادُ الرموز (QRCODE_LIMIT=30) لا يُصفَّر إلا بإنشاء النسخة، فيتراكم
     *   من كلّ محاولةٍ سابقة ومن جلساتٍ فتحها الكنسُ بلا أحدٍ ينظر — فتُرفض
     *   جلسةُ المكتب في منتصف المسح.
     * - طلبُ رمز ربطٍ مرّةً يكتب الرقمَ في اعتماد النسخة، فكلُّ جلسةٍ بعده تحاول
     *   «الدخول» بدل «الربط» فتُرفض.
     * - والرمزُ المخزَّن لا يُمحى عند سقوط جلسة: «connect» قد يعيد رمزاً ميتاً.
     *
     * فالنسخةُ التي لم تقترن تُطوى وتُنشأ من جديد عند كلّ طلبٍ من المكتب: عدّادٌ
     * من الصفر، واعتمادٌ نظيف، والرقمُ — إن وُجد — يُعتمد من أوّل لحظة. والرموزُ
     * تتجدّد كلَّ ٤٥ ثانية فتُقرأ بعد ذلك من currentPairing() لا من هذا الردّ.
     *
     * ═══ وما لا يُطوى أبداً ═══
     *
     * نسخةٌ مقترنة (حالتُها في قاعدة الجسر «open» ولها رقمُ مالك) — سقط اتصالُها
     * أم لم يسقط: يُطرق بابُ وصلها ولا تُحذف. وحذفُها قطعُ اقترانِ مكتبٍ يعمل.
     *
     * @return array{qr: ?string, code: ?string, state: string, message: ?string}
     */
    public function pair(?string $phone = null): array
    {
        $this->lastError = null;

        // رقمُ المكتب — أرقامٌ بلا زائد كما يقبله الجسر. ووجودُه يعني
        // البابَ الثاني: رمزٌ من ثمانية محارف يُكتب في الهاتف بدل المسح.
        $phone = $phone === null ? null : (preg_replace('/\D+/', '', $phone) ?: null);

        if (!$this->isConfigured()) {
            $this->lastError = 'خادم Evolution غير مضبوط — العنوان أو المفتاح ناقص.';

            return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
        }

        $instance = WhatsAppSettings::evolutionInstance();

        // ═══ طلبٌ واحدٌ في كلّ لحظة ═══
        //
        // زرُّ المسح وزرُّ الرقم وإعادةُ المحاولة والإصلاحُ من سطر الأوامر قد
        // تتزامن: أحدُها يحذف النسخةَ والآخرُ أعاد للتوّ رمزَها — فيصل المكتبَ
        // رمزٌ ميتٌ قبل أن يراه. وجلستان معاً عند الجسر تُضيّعان المسحَ الناجح
        // (يُحفظ اعتمادُ الجلسة الأحدث لا التي اقترنت).
        // أطولُ من أسوأ رحلةٍ يسمح بها موعدُ PAIR_BUDGET_S (مع آخر نداءٍ يُقطع عنده)
        // — وإلا انقضى القفلُ والطلبُ ما زال يحذف وينشئ، فدخل طلبٌ ثانٍ عليه
        $lock = Cache::lock('wa:pair:' . $instance, 90);

        if (!$lock->get()) {
            $since = self::pairingRunningFor();

            return ['qr' => null, 'code' => null, 'state' => 'connecting',
                'message' => 'طلبُ رمزٍ سابقٌ ما زال يُجهَّز عند الخادم' . ($since !== null ? ' منذ ' . $since . ' ث' : '')
                    . ' — انتظر ثوانيَ ثم أعد المحاولة.'];
        }

        $this->trace = [];
        $startedAt = $this->clock();

        try {
            Cache::put(self::pairingKey($instance), true, now()->addMinutes(self::PAIRING_WINDOW_MIN));
            // علامةُ «يعمل الآن»: الشاشةُ تسأل الحالةَ كلَّ ثوانٍ، وطلبٌ قطعه الوسيطُ
            // ما زال يحذف وينشئ — فتُقال مدّتُه بدل سؤالٍ ثانٍ يتنازع معه
            Cache::put(self::runningKey($instance), $startedAt, 90);
            $this->pairDeadline = $startedAt + self::PAIR_BUDGET_S;

            $result = $this->pairLocked($instance, $phone);
        } catch (\Throwable $e) {
            // عطلٌ في منتصف الرحلة: يُسجَّل بأثره، وتبقى الخطواتُ التي سبقته للشاشة
            report($e);
            $this->lastError = 'عطلٌ غير متوقَّع أثناء الربط — سُجِّل بأثره للمطوّر.';
            $result = ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
        } finally {
            $this->pairDeadline = INF;
            Cache::forget(self::runningKey($instance));
            $lock->release();
        }

        $result['took'] = round($this->clock() - $startedAt, 1);
        $result['trace'] = $this->trace;

        // آخرُ محاولةٍ تُحفظ: طلبٌ قطعه الوسيطُ قبل ردّه تقرأ الشاشةُ نتيجتَه من هنا
        Cache::put(self::lastAttemptKey($instance), [
            'at' => time(), 'state' => $result['state'], 'message' => $result['message'] ?? null,
            'trace' => $this->trace, 'took' => $result['took'],
        ], 600);

        if ($result['state'] === 'close' || $result['took'] > 15) {
            Log::info('WhatsApp pair ' . $result['state'] . ' in ' . $result['took'] . 's: ' . implode(' | ', $this->trace)
                . (($result['message'] ?? null) ? ' — ' . $result['message'] : ''));
        }

        return $result;
    }

    protected static function runningKey(string $instance): string
    {
        return 'wa:pair:running:' . $instance;
    }

    protected static function lastAttemptKey(string $instance): string
    {
        return 'wa:pair:last:' . $instance;
    }

    protected static function deletedKey(string $instance): string
    {
        return 'wa:pair:deleted:' . $instance;
    }

    protected static function pendingKey(string $instance): string
    {
        return 'wa:pair:pending:' . $instance;
    }

    /** إنشاءٌ قُبل وما زال الجسرُ يفتح جلستَه: منذ كم ثانية — أو null. */
    public static function createPendingFor(): ?int
    {
        $since = Cache::get(self::pendingKey(WhatsAppSettings::evolutionInstance()));

        return is_numeric($since) ? max(0, time() - (int) $since) : null;
    }

    /** المكتبُ نفسُه طوى النسخةَ في الدقائق الخمس الأخيرة — فغيابُها عند الجسر متوقَّع. */
    protected static function deletedByUsRecently(string $instance): bool
    {
        $at = Cache::get(self::deletedKey($instance));

        return is_numeric($at) && time() - (int) $at <= 300;
    }

    /** طلبُ ربطٍ يعمل الآن عند الخادم: منذ كم ثانية — أو null إن لم يكن. */
    public static function pairingRunningFor(): ?int
    {
        $since = Cache::get(self::runningKey(WhatsAppSettings::evolutionInstance()));

        if (!is_numeric($since)) {
            return null;
        }

        $age = (int) round(microtime(true) - (float) $since);

        // عاملٌ قُتل في منتصف الطلب لا يمحو علامتَه (finally لا يجري): علامةٌ أقدمُ من أيّ
        // طلبٍ ممكن (الموعدُ وآخرُ نداءٍ بعده) لا تُصدَّق — وإلا بقيت الشاشةُ «تُجهَّز» دقيقتين
        return $age <= self::PAIR_BUDGET_S + 25 ? max(0, $age) : null;
    }

    /**
     * نتيجةُ آخر طلبِ ربط (at, state, message, trace, took) — أو null.
     *
     * @return array<string, mixed>|null
     */
    public static function lastAttempt(): ?array
    {
        $last = Cache::get(self::lastAttemptKey(WhatsAppSettings::evolutionInstance()));

        return is_array($last) ? $last : null;
    }

    /** المكتبُ يقترن الآن — الكنسُ والإصلاحُ لا يلمسان النسخة. */
    public static function pairingInProgress(): bool
    {
        return (bool) Cache::get(self::pairingKey(WhatsAppSettings::evolutionInstance()), false);
    }

    protected static function pairingKey(string $instance): string
    {
        return 'wa:pairing-active:' . $instance;
    }

    protected static function latestKey(string $instance): string
    {
        return 'wa:pairing-latest:' . $instance;
    }

    /**
     * أحدثُ رمزٍ أرسله الجسرُ في ويبهوك QRCODE_UPDATED — يُحفظ لتقرأه الشاشة.
     *
     * ‏أسلمُ من سؤال «connect»: ذاك في «close» يفتح جلسةً ثانية، والجلسةُ تمرّ
     * بـ«close» ثانيةً أو اثنتين بعد المسح الناجح نفسِه (الجسرُ يعيد التشغيل
     * 515)، وجلستان معاً تُضيّعان الاقترانَ الذي تمّ للتوّ. والويبهوكُ لا أثرَ له.
     *
     * يعيش ٥٥ ثانية: الرمزُ يتبدّل كلَّ ٤٥، فما فات ذلك قد مات.
     *
     * @param  array<string, mixed>  $qrcode  ‏{base64, code, pairingCode} كما في الحمولة
     */
    public static function rememberPairing(array $qrcode): void
    {
        $base64 = is_string($qrcode['base64'] ?? null) ? $qrcode['base64'] : '';
        $raw = is_string($qrcode['code'] ?? null) ? $qrcode['code'] : '';
        $code = is_string($qrcode['pairingCode'] ?? null) && $qrcode['pairingCode'] !== '' ? $qrcode['pairingCode'] : null;

        $qr = $base64 !== ''
            ? (str_starts_with($base64, 'data:') ? $base64 : 'data:image/png;base64,' . $base64)
            : ($raw !== '' ? 'text:' . $raw : null);

        if ($qr === null && $code === null) {
            return;
        }

        $key = self::latestKey(WhatsAppSettings::evolutionInstance());
        $prev = Cache::get($key);

        Cache::put($key, [
            'qr' => $qr,
            'code' => $code,
            'seq' => (int) (is_array($prev) ? ($prev['seq'] ?? 0) : 0) + 1,
        ], now()->addSeconds(55));
    }

    /** جلسةٌ جديدةٌ أو اقترانٌ تمّ: لا يُعرض رمزُ ما قبلها. */
    public static function forgetPairing(): void
    {
        Cache::forget(self::latestKey(WhatsAppSettings::evolutionInstance()));
        Cache::forget(self::pendingKey(WhatsAppSettings::evolutionInstance()));
    }

    /** @return array{qr: ?string, code: ?string, state: string, message: ?string} */
    protected function pairLocked(string $instance, ?string $phone): array
    {
        $state = $this->connectionState();

        // موصولٌ أصلاً: لا يُعرض رمزٌ لمن لا يحتاجه
        if ($state === 'open') {
            return $this->opened('الرقم موصولٌ بالفعل.');
        }

        // الصفُّ يُمهَل اثنتي عشرةَ ثانية لا ستّاً: استعلامُه يعدّ رسائلَ النسخة وجهاتِها كلَّها
        // (instanceInfo: _count) فيبطئ على قاعدةٍ كبيرة — وجوابُه هو ما يمنع حذفَ نسخةٍ مقترنة
        [$found, $row] = $this->instanceLookup(12);

        // مكتبٌ اقترن من قبل (يُختم عند أوّل فتحٍ ولا يُمحى): نسختُه لا تُطوى على جهل
        $pairedBefore = (string) \App\Models\Setting::get(WhatsAppSettings::KEY_CONNECTED_AT, '') !== '';

        // ═══ لا يُحذف شيءٌ على جهل ═══
        //
        // جسرٌ لا يجيب عن صفّ النسخة (مهلة، ٥٠٠، قاعدتُه تُعاد) لا يُستدلّ بصمته
        // على أنّها لم تقترن: الحذفُ عنده يبدأ بإلغاء اقتران الهاتف. فيُقال ذلك.
        // الحالةُ نفسُها قالت إنّ الجسر لا يعرف النسخة (٤٠٤): جوابٌ صريحٌ يغني عن
        // الصفّ — فيُعامَل غيابُها بقواعد الغياب (ومنها مهلةُ الجسر الذي يقلع)
        if ($found === 'unknown' && $this->stateMissing) {
            $found = 'missing';
        }

        if ($found === 'unknown') {
            // ═══ صفٌّ لا يُقرأ وحالةٌ تُقرأ ═══
            //
            // جسرٌ يجيب عن الحالة صراحةً (٢٠٠) ويعجز عن الصفّ وحده (قاعدتُه تردّ
            // ٥٠٠ على استعلام fetchInstances) ليس جسراً صامتاً: يُعرف منه أنّه لا
            // جلسةَ مفتوحةٌ الآن. فتُعاد الحالةُ بعد ثوانٍ — جلسةٌ مقترنةٌ تعيد وصلَها
            // تُفتح فيها — وما بقي مغلقاً أو ينتظر مسحاً لا يعمل، والمكتبُ يطلب الربط.
            // ولو بقي الصفُّ عصيّاً على القراءة أبداً لما ارتبط رقمٌ على هذا الجسر.
            // ومهلةٌ على الصفّ ليست جواباً كالـ٥٠٠: قد يكون الصفُّ هناك — مقترناً — وقاعدةُ
            // الجسر بطيئةٌ وحسب. فمكتبٌ اقترن من قبلُ لا تُطوى نسختُه عليها: الحذفُ يُلغي
            // اقترانَ هاتفه، ولا شيءَ يعيده إلا مسحٌ جديد
            if ($this->stateFailure === null && $this->lookupUnanswered && $pairedBefore) {
                $this->lastError = 'سجلُّ نسخة المكتب لم يُجب (' . $this->lookupFailure . ') والرقمُ اقترن من قبل — لم يُغيَّر شيء حتى لا يُلغى اقترانُه. '
                    . 'قاعدةُ الجسر بطيئة؛ أعد المحاولة بعد دقيقة، وإن تكرّر فافحص الجسرَ على الخادم.';

                return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
            }

            if ($this->stateFailure === null) {
                Log::warning('Evolution fetchInstances unreadable (' . $this->lookupFailure . ') — pairing from the live state alone');
                $this->pause(4000);

                if ($this->connectionState() === 'open') {
                    return $this->opened('الرقم موصولٌ بالفعل.');
                }

                if ($this->stateFailure === null) {
                    return $this->startFresh($instance, $phone);
                }
            }

            $this->lastError = 'الجسرُ لا يجيب الآن (' . ($this->stateFailure ?? $this->lookupFailure ?? 'بلا ردّ')
                . ') — لم يُغيَّر شيء. أعد المحاولة بعد دقيقة، وإن تكرّر فالجسرُ نفسُه متوقّف على الخادم.';

            return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
        }

        // ═══ نسخةٌ لا يعرفها الجسرُ الآن — وقد يكون يقلع ═══
        //
        // بعد إعادة تشغيل الجسر لا تكون النسخُ المقترنةُ في ذاكرته حتى يفرغ من
        // تحميلها (monitor.service.ts:296-307 — ومعه جلبُ إصدار واتساب بلا مهلة)،
        // فتبدو «غير موجودة». والإنشاءُ حينها يصطدم بصفّها فيمسحه الجسرُ مع
        // اعتمادها — يقطع اقتراناً صالحاً. فمكتبٌ اقترن من قبلُ يُمهَل الجسرُ له:
        // ثوانيَ هنا، ثمّ لا يُنشأ بديلٌ إلا إن بقيت غائبةً دقيقةً ونصفاً على الأقلّ.
        // (ونسخةٌ طواها المكتبُ نفسُه للتوّ — حذفٌ صدر ولم يتبعه إنشاء — غيابُها متوقَّع لا يُنتظر)
        if ($found === 'missing' && $pairedBefore && !self::deletedByUsRecently($instance)) {
            // ويُترك للإنشاء بعدها وقتُه: الجسرُ يُمسكه خمسَ ثوانٍ
            for ($i = 0; $i < 3 && $found === 'missing' && $this->remaining() > 10; $i++) {
                $this->pause(2500);
                [$found, $row] = $this->instanceLookup();
            }

            if ($found === 'row' && $this->connectionState() === 'open') {
                return $this->opened('الرقم موصولٌ بالفعل.');
            }

            if ($found !== 'row' && !$this->sustained('missing', $instance, 90)) {
                return ['qr' => null, 'code' => null, 'state' => 'close',
                    'message' => 'الجسرُ لا يعرف نسخةَ المكتب الآن — إن كان قد أُعيد تشغيلُه للتوّ فهو يحمّلها. انتظر دقيقتين ثم اضغط مرّةً أخرى.'];
            }
        }

        // ═══ نسخةٌ مقترنةٌ سقط اتصالُها: تُوصَل ولا تُحذف ═══
        if (self::isPairedRow($row)) {
            if ($state === 'close') {
                // ‏«close» مرّتين بينهما ثانيتان: الجسرُ يعيد الوصلَ بنفسه بعد كلّ سقوطٍ
                // عابر، و«connect» في لحظته يفتح جلسةً ثانيةً تتنازع اعتمادَه
                $this->pause(2000);
                $state = $this->connectionState();

                if ($state === 'close') {
                    $this->nudge($instance);
                    $this->pause(3000);
                    $state = $this->connectionState();
                }
            }

            WhatsAppSettings::setEvolutionState($state);

            if ($state === 'open') {
                return $this->opened('أُعيد وصلُ الرقم المقترن.');
            }

            // جلسةٌ مقترنةٌ لا تُصدر رموزاً. فإن بقيت لا تتّصل دقيقتين بين ضغطتين
            // فاعتمادُها ميتٌ وإن لم يكتب الجسرُ ذلك (سقوطٌ بأسبابٍ يعيد معها الوصلَ
            // أبداً) — فتُنشأ نسخةٌ نظيفةٌ في الضغطة التالية بعدهما.
            if (!$this->sustained('stuck', $instance, 120)) {
                return ['qr' => null, 'code' => null, 'state' => 'connecting',
                    'message' => 'الرقمُ مقترنٌ من قبل والجسرُ يعيد وصلَه الآن — انتظر. إن لم يتّصل خلال دقيقتين فاضغط مرّةً أخرى لرمزٍ جديد.'];
            }
        }

        // ═══ جلسةٌ في منتصف الربط: لا تُطوى قبل أن يُتأكَّد ═══
        //
        // بعد المسح الناجح يعيد واتساب تشغيلَ الاتصال (515) والصفُّ ما زال
        // «connecting» بلا مالك ثوانيَ حتى يُفتح. وضغطةٌ في تلك الثواني كانت تطوي
        // النسخة — والحذفُ يلغي اقترانَ الهاتف الذي تمّ للتوّ.
        if ((string) ($row['connectionStatus'] ?? '') === 'connecting') {
            $this->pause(2500);

            if ($this->connectionState() === 'open') {
                return $this->opened('تمّ الاقتران.');
            }
        }

        // ═══ لم تقترن، أو انتهت رموزُها، أو فصلها الهاتف: جلسةٌ نظيفة ═══
        return $this->startFresh($instance, $phone);
    }

    /** @return array{qr: null, code: null, state: string, message: string} */
    protected function opened(string $message): array
    {
        // يمحو معه رمزَ الجلسة وأختامَ «الغياب» و«التعثّر» (setEvolutionState)
        WhatsAppSettings::setEvolutionState('open');

        return ['qr' => null, 'code' => null, 'state' => 'open', 'message' => $message];
    }

    /** ما لم يُرَ خلالها يُعدّ انقطاعاً في الحال — تبدأ المدّةُ من جديد. */
    protected const SUSTAIN_GAP_S = 300;

    /**
     * أدامت هذه الحالُ هذه الثواني متّصلةً؟
     *
     * ما يُعدّ غيابَ نسخةٍ أو موتَ اقترانٍ لا يُبنى على لحظةٍ واحدة: الجسرُ يقلع
     * ويعيد الوصل في ثوانٍ. فيُبنى على حالٍ رُئيت مرّتين بينهما هذه المدّة —
     * ومتّصلةً: سقوطٌ رُئي قبل عشر دقائق ثمّ عاد الوصلُ بعده لا يُحسب مع سقوطٍ
     * عابرٍ الآن (وإلا طوت ضغطةٌ واحدةٌ اقتراناً يعمل). فتُختم أوّلُ رؤيةٍ وآخرُها،
     * وفجوةٌ أطولُ من SUSTAIN_GAP_S تبدأ العدَّ من جديد، والفتحُ يمحو الختم
     * (WhatsAppSettings::setEvolutionState).
     */
    protected function sustained(string $what, string $instance, int $seconds): bool
    {
        $key = self::sustainKey($what, $instance);
        $now = now()->getTimestamp();
        $seen = Cache::get($key);

        if (!is_array($seen) || $now - (int) ($seen['last'] ?? 0) > self::SUSTAIN_GAP_S) {
            $seen = ['first' => $now, 'last' => $now];
        }

        $seen['last'] = $now;
        Cache::put($key, $seen, now()->addMinutes(20));

        return $now - (int) $seen['first'] >= $seconds;
    }

    protected static function sustainKey(string $what, string $instance): string
    {
        return 'wa:sustained:' . $what . ':' . $instance;
    }

    /** الرقمُ موصول: لا رمزَ يُعرض، ولا تُحسب غيبةٌ أو تعثّرٌ سبقا الوصل. */
    public static function clearPairingTraces(): void
    {
        $instance = WhatsAppSettings::evolutionInstance();

        Cache::forget(self::latestKey($instance));
        Cache::forget(self::sustainKey('missing', $instance));
        Cache::forget(self::sustainKey('stuck', $instance));
        Cache::forget(self::deletedKey($instance));
        Cache::forget(self::pendingKey($instance));
    }

    /**
     * حالُ نسخة المكتب عند الجسر بكلمة — لأوامر الإصلاح، بلا أيّ تغيير.
     *
     * @return 'open'|'paired'|'unpaired'|'missing'|'unknown'
     */
    public function pairingStatus(): string
    {
        if ($this->connectionState() === 'open') {
            return 'open';
        }

        [$found, $row] = $this->instanceLookup();

        return match (true) {
            $found !== 'row' => $found,
            self::isPairedRow($row) => 'paired',
            default => 'unpaired',
        };
    }

    /**
     * نسخةٌ مقترنة: آخرُ ما كتبه الجسرُ في قاعدته «open» ولها رقمُ مالك.
     *
     * ‏ownerJid يُكتب عند الفتح ولا يُمحى أبداً، والحالةُ في القاعدة تصير
     * «connecting» مع كلّ رمز مسح و«close» عند الفصل من الهاتف — وسقوطٌ عابرٌ
     * لا يغيّرها (whatsapp.baileys.service.ts:413-497). ونسخةٌ أُنشئت للتوّ
     * حالتُها «open» افتراضاً بلا مالك (Instance.connectionStatus @default(open)).
     *
     * @param  array<string, mixed>|null  $row
     */
    protected static function isPairedRow(?array $row): bool
    {
        return $row !== null
            && (string) ($row['connectionStatus'] ?? '') === 'open'
            && trim((string) ($row['ownerJid'] ?? $row['owner'] ?? '')) !== '';
    }

    /**
     * طيُّ النسخة غير المقترنة وإنشاؤها من جديد — بالرقم إن وُجد.
     *
     * @return array{qr: ?string, code: ?string, state: string, message: ?string}
     */
    protected function startFresh(string $instance, ?string $phone): array
    {
        self::forgetPairing();

        // ═══ لا حذفَ بلا وقتٍ لإنشاءٍ بعده ═══
        //
        // الجسرُ ينفّذ الحذفَ وإن انقضت مهلتُنا، فحذفٌ لا يتبعه إنشاءٌ يترك المكتبَ بلا
        // نسخة والضغطةُ التالية تجدها «غائبة». فإن لم يبقَ للحذف ثمّ الإنشاء وقتُهما لم
        // يُبدأ شيء — والضغطةُ التالية تبدأ نظيفة
        if ($this->remaining() < self::CREATE_RESERVE_S + 1) {
            return $this->expired('قبل طيّ الجلسة القديمة');
        }

        // ‏٤٠٤ هنا لا شيءَ يُحذف (لم تُنشأ أو ليست في ذاكرة الجسر) — يُمضى إلى الإنشاء.
        // ويُعاد الحذفُ مرّةً إن بقي بعد مهلته (١٫٥ + ١ ث على الأقلّ) وقتُ الإنشاء
        $deleted = $this->deleteInstance($instance);

        if (!$deleted && $this->remaining() >= self::CREATE_RESERVE_S + 3) {
            $this->pause(1500);
            $deleted = $this->deleteInstance($instance);
        }

        // الحذفُ يُكمل تنظيفَ قاعدة الجسر بعد أن يردّ (monitor.service.ts:393-406)،
        // فإنشاءٌ فوريٌّ قد يجد الاسمَ «مستعمَلاً» أو الصفَّ باقياً (٤٠٠). يُعاد
        // ثلاثاً بمهلةٍ قصيرة، ولا يُعاد على رفض المفتاح أو عطلٍ في الخادم.
        $created = ['qr' => null, 'code' => null, 'error' => null, 'status' => 0];

        for ($try = 0; $try < 4; $try++) {
            // الإنشاءُ الأوّل يُجرى أبداً بعد حذفٍ صدر (within تحدّه بما بقي)؛ والإعادةُ
            // تحتاج ستَّ ثوانٍ على الأقلّ فلا تُبدأ لتُقطع
            if ($try > 0 && $this->remaining() < self::CREATE_RESERVE_S) {
                return $this->expired('قبل إعادة الإنشاء');
            }

            $created = $this->createInstance($instance, $phone);

            // ‏٤٠٣ برفض المفتاح يحمل سببَه (error) ولا يُعاد؛ و٤٠٣ «مستعمَل» بلا سبب
            $busy = in_array($created['status'], [400, 409], true)
                || ($created['status'] === 403 && $created['error'] === null);

            if (!$busy) {
                break;
            }

            // ═══ نسخةٌ عالقةٌ في ذاكرة الجسر لا تُحذف ولا تُستبدل ═══
            //
            // «مستعمَلة» بعد حذفٍ لم نتمكّن من تأكيده (مهلةٌ بلا ردّ، أو خطأ): الجسرُ لم
            // يُخرجها من ذاكرته — إلغاءُ اقترانها علق، أو قاعدتُه لا تُجيب (fetchInstances
            // يردّ ٥٠٠) — فيصطدم الإنشاءُ باسمها أبداً. إعادةُ المحاولة عبثٌ تأكل الموعدَ
            // ثمّ تقول «انقضى»، والعلّةُ الحقيقيّة أنّ الجسرَ نفسَه يحتاج إعادةَ تشغيل.
            if (!$deleted) {
                $this->lastError = 'نسخةُ المكتب عالقةٌ في الجسر: لا تُحذف (تعذّر إخراجها) ولا يقبل الجسرُ إنشاءَ بديلٍ عنها «مستعمَلة». '
                    . 'الجسرُ نفسُه يحتاج إعادةَ تشغيلٍ على الخادم — بعدها أعد المحاولة.';

                return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
            }

            $this->pause(1500);
        }

        if ($created['qr'] !== null || $created['code'] !== null) {
            // الإنشاءُ تمّ: علامةُ «طواها المكتبُ بنفسه» تُمحى — بقاؤها بعد اقترانٍ جديد كانت
            // تُسقط مهلةَ إقلاع الجسر في الدقائق الخمس التالية، فضغطةٌ أثناء إعادة تشغيله
            // تجد النسخةَ «غائبة» وتنشئ فوقها فيمحو الجسرُ صفَّها المقترن
            Cache::forget(self::deletedKey($instance));
            WhatsAppSettings::setEvolutionState('connecting');

            // بالرقم يُعرض الرمزُ وحده: رمزُ المسح المرافق له ليس ما طلبه المكتب
            return [
                'qr' => $phone === null ? $created['qr'] : null,
                'code' => $created['code'],
                'state' => 'connecting',
                'message' => $phone !== null && $created['code'] === null ? 'يُطلب رمزُ الربط من واتساب…' : null,
            ];
        }

        // ═══ إنشاءٌ قطعته مهلتُنا لا رفضُ الجسر ═══
        //
        // الجسرُ يفتح الجلسةَ ثمّ يُمسك الردَّ خمسَ ثوانٍ ينتظر أوّلَ رمز: فقد تكون فُتحت
        // ورمزُها يصل في الويبهوك — ولا يُقال «تعذّر» عن جلسةٍ حيّة، وإلا طوتها الضغطةُ
        // التالية وأُنشئت أخرى تُقطع مثلَها فلا يرى المكتبُ رمزاً أبداً
        if ($created['status'] === 0 && !empty($created['timedOut'])) {
            // ثلاثُ نظراتٍ في ستّ ثوانٍ لا واحدة: حالةُ النسخة تبقى «close» حتى يفتح
            // Baileys مقبسَه، والجسرُ البطيء قد يكون بعدُ في إدراج صفّها
            $this->pairDeadline = max($this->pairDeadline, $this->clock() + 7);

            for ($look = 0; $look < 3; $look++) {
                if ($this->connectionState() === 'connecting') {
                    Cache::forget(self::deletedKey($instance));
                    WhatsAppSettings::setEvolutionState('connecting');

                    return ['qr' => null, 'code' => null, 'state' => 'connecting',
                        'message' => 'الجسرُ بطيء — فُتحت الجلسةُ عند الخادم ويصل رمزُها خلال ثوانٍ…'];
                }

                $this->pause(2000);
            }

            // النسخةُ في ذاكرة الجسر («close» بـ٢٠٠ لا ٤٠٤): الإنشاءُ قُبل والجسرُ بعدُ يقرأ
            // الاعتمادَ أو يجلب إصدارَ واتساب (بلا مهلة عنده) قبل أن يفتح المقبس. فلا يُقال
            // «تعذّر» — وإلا طوت الضغطةُ التالية جلسةً كانت على وشك أن تُصدر رمزَها —
            // بل «ما زال يفتحها»، وتُرفع علامةٌ تجعل سؤالَ الشاشة ينتظر لا يعلن انتهاءً
            if ($this->stateFailure === null) {
                Cache::forget(self::deletedKey($instance));
                Cache::put(self::pendingKey($instance), time(), 90);
                WhatsAppSettings::setEvolutionState('connecting');

                return ['qr' => null, 'code' => null, 'state' => 'connecting',
                    'message' => 'الجسرُ بطيء — ما زال يفتح الجلسةَ عند الخادم، والشاشةُ تتابع…'];
            }
        }

        if ($created['error'] !== null) {
            $this->lastError = $created['error'];

            return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $created['error']];
        }

        if ($created['status'] >= 200 && $created['status'] < 300) {
            // أُنشئت ولم يصل رمزُها في مهلة الجسر (خمسُ ثوانٍ): يصل في السؤال التالي
            Cache::forget(self::deletedKey($instance));
            WhatsAppSettings::setEvolutionState('connecting');

            return ['qr' => null, 'code' => null, 'state' => 'connecting',
                'message' => $phone !== null ? 'يُطلب رمزُ الربط من واتساب…' : 'يُجهَّز رمزُ المسح…'];
        }

        $this->lastError = 'لم يقبل الجسرُ إنشاءَ جلسةٍ جديدة الآن — أعد المحاولة بعد دقيقة.';

        return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
    }

    /** انقضى الموعدُ قبل خطوةٍ لا تُبدأ لتُقطع — بلا تغييرٍ عند الجسر. */
    protected function expired(string $where): array
    {
        $this->lastError = 'انقضى موعدُ الطلب (' . self::PAIR_BUDGET_S . ' ث) ' . $where . ' — الجسرُ بطيءٌ في الردّ. '
            . 'أعد المحاولة، وإن تكرّر فالجسرُ أو قاعدتُه على الخادم هي البطيئة.';

        return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
    }

    /**
     * الرمزُ الحاليّ لجلسةٍ تنتظر — تسأله الشاشةُ كلَّ ثوانٍ.
     *
     * الجسرُ يبدّل رمزَ المسح كلَّ ٤٥ ثانية، ومع الرقم يطلب رمزَ ربطٍ جديداً مع
     * كلّ تبديلة — وواتساب لا يقبل إلا الأحدث. فالرمزُ الذي عُرض مرّةً يموت
     * والشاشةُ ما زالت تقول «امسح». وهنا يُقرأ الأحدثُ دائماً.
     *
     * ولا يُسأل «connect» إلا وجلسةٌ تنتظر مسحاً فعلاً: في «connecting» يعيد
     * الرمزَ المخزَّن بلا أثر، أمّا في «close» ففتحُ جلسةٍ جديدة — وجلسةٌ مقترنةٌ
     * تعيد وصلَها تحمل رمزاً قديماً لا يُعرض.
     *
     * @return array{state: string, qr: ?string, code: ?string, count: ?int}
     */
    public function currentPairing(): array
    {
        // موعدُ سؤال الشاشة خمسَ عشرةَ ثانية: أقلُّ من مهلة المتصفّح (٢٠ ث)، فلا يبقى عاملُ PHP
        // على الجسر بعد أن يئس المتصفّحُ وأرسل سؤالاً ثانياً — اثنان على جسرٍ بطيء بدل واحد
        $this->pairDeadline = $this->clock() + 15;

        try {
            return $this->currentPairingWithin();
        } finally {
            $this->pairDeadline = INF;
        }
    }

    /** @return array{state: string, qr: ?string, code: ?string, count: ?int} */
    protected function currentPairingWithin(): array
    {
        $state = $this->connectionState();
        $none = ['state' => $state, 'qr' => null, 'code' => null, 'count' => null];

        if ($state !== 'connecting') {
            return $none;
        }

        // ما أرسله الجسرُ نفسُه في ويبهوكه — بلا نداءٍ قد يفتح جلسةً ثانية. ويُقرأ قبل
        // صفّ النسخة: استعلامُ الصفّ يعدّ رسائلَ النسخة وجهاتِها ومحادثاتِها كلَّها في
        // قاعدة الجسر (monitor.service.ts instanceInfo: _count)، والشاشةُ تسأل كلَّ
        // ثانيتين ونصف — فعدٌّ ثقيلٌ لا يُطلب والرمزُ في اليد. والرمزُ يُمحى عند الفتح
        // (clearPairingTraces) وعند طيّ الجلسة (forgetPairing) فلا يُعرض رمزُ جلسةٍ ماضية.
        $latest = Cache::get(self::latestKey(WhatsAppSettings::evolutionInstance()));

        if (is_array($latest)) {
            return ['state' => 'connecting', 'qr' => $latest['qr'] ?? null, 'code' => $latest['code'] ?? null,
                'count' => isset($latest['seq']) ? (int) $latest['seq'] : null];
        }

        [$found, $row] = $this->instanceLookup();

        // صفٌّ يُقرأ ولا ينتظر مسحاً (مقترنٌ يعيد وصلَه) أو غائب: لا رمزَ يُعرض. وصفٌّ
        // لا يُقرأ أصلاً: الجلسةُ هي التي فتحها المكتبُ للتوّ (pairLocked)
        if ($found === 'missing' || ($found === 'row' && (string) ($row['connectionStatus'] ?? '') !== 'connecting')) {
            return $none;
        }

        // الويبهوكُ لا يصلنا (الجسرُ لا يبلغ عنوانَ المكتب): يُسأل «connect» —
        // بعد تأكيد الحالة في اللحظة نفسها، فـ«close» عنده يعني جلسةً ثانية
        if ($this->connectionState() !== 'connecting') {
            return $none;
        }

        $from = $this->clock();

        try {
            $response = $this->http()->timeout($this->within(6))->get($this->url('instance/connect/' . WhatsAppSettings::evolutionInstance()));
        } catch (\Throwable $e) {
            $this->traced('الرمز', $from, self::transportReason($e));

            return $none;
        }

        $this->traced('الرمز', $from, 'HTTP ' . $response->status());

        if (!$response->successful() || $response->json('error') === true) {
            return $none;
        }

        $count = $response->json('count') ?? $response->json('qrcode.count');

        return [
            'state' => 'connecting',
            'qr' => $this->qrFrom($response),
            'code' => $this->pairingCodeFrom($response),
            'count' => is_numeric($count) ? (int) $count : null,
        ];
    }

    /**
     * حذفُ نسخةٍ غير مقترنة — تمهيداً لإنشاءٍ نظيف.
     *
     * لا يُنادى إلا من startFresh بعد أن ثبت أنّ النسخة غير مقترنة: حذفُ
     * نسخةٍ مقترنة يعني قطعَ اقترانِ مكتبٍ يعمل، وذلك ما لا نفعله من طرفنا.
     */
    protected function deleteInstance(string $instance): bool
    {
        $from = $this->clock();
        // مهلتُه لا تأكل نافذةَ الإنشاء بعده: الجسرُ ينفّذ الحذفَ وإن انقطعنا عنه
        $cap = $this->remaining() === INF ? 10 : max(1, min(10, (int) floor($this->remaining() - self::CREATE_RESERVE_S)));

        try {
            $response = $this->http()->timeout($this->within($cap))->delete($this->url('instance/delete/' . $instance));
        } catch (\Throwable $e) {
            $this->traced('الحذف', $from, self::transportReason($e));
            // قد يكون نُفِّذ رغم انقطاعنا: يُحسب حذفاً صدر
            Cache::put(self::deletedKey($instance), time(), 300);

            return false;
        }

        $this->traced('الحذف', $from, 'HTTP ' . $response->status());

        if (!$response->successful()) {
            // ‏٤٠٤ هنا نجاحٌ: لا شيءَ في ذاكرة الجسر يُحذف
            return $response->status() === 404;
        }

        Cache::put(self::deletedKey($instance), time(), 300);

        return true;
    }

    /** حالةُ الاقتران عند الخادم: open|connecting|close. */
    public function connectionState(): string
    {
        if (!$this->isConfigured()) {
            return 'close';
        }

        $from = $this->clock();

        try {
            $response = $this->http()->timeout($this->within(6))->get(
                $this->url('instance/connectionState/' . WhatsAppSettings::evolutionInstance())
            );
        } catch (\Throwable $e) {
            $this->stateFailure = self::transportReason($e);
            $this->stateMissing = false;
            $this->traced('الحالة', $from, $this->stateFailure);

            return 'close';
        }

        if (!$response->successful()) {
            $this->stateFailure = 'HTTP ' . $response->status();
            $this->stateMissing = $response->status() === 404;
            $this->traced('الحالة', $from, $this->stateFailure);

            return 'close';
        }

        $this->stateFailure = null;
        $this->stateMissing = false;
        $state = (string) ($response->json('instance.state') ?? $response->json('state') ?? 'close');
        $state = in_array($state, ['open', 'connecting', 'close'], true) ? $state : 'close';
        $this->traced('الحالة', $from, $state);

        return $state;
    }

    /** فصلُ الجلسة عند الخادم — الهاتفُ يُلغي الاقتران. */
    public function logout(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->http()->delete(
                $this->url('instance/logout/' . WhatsAppSettings::evolutionInstance())
            );
        } catch (\Throwable) {
            return false;
        }

        WhatsAppSettings::setEvolutionState('close');

        return $response->successful();
    }

    /**
     * إحياءُ اقترانٍ سقط — بلا رمزٍ يُمسح وبلا يدِ أحد.
     *
     * ═══ «ما ينفصل أبداً إلا إذا المكتب فصله» ═══
     *
     * جلسةُ واتساب ويب تسقط أحياناً: الجسرُ يُعاد تشغيله، أو الاتصال
     * ينقطع لحظةً، فتصير الحالة close والاعتمادُ عند الخادم ما زال
     * صالحاً. كان المكتب يبقى مفصولاً حتى يفتح أحدُهم الإعدادات
     * صدفةً — والرسائلُ تُقيَّد «في البوابة» أياماً.
     *
     * ═══ ولا يُفتح بابٌ لمن لم يقترن ═══
     *
     * ‏«connect» على نسخةٍ بلا اعتمادٍ صالح لا يُحيي شيئاً: يفتح جلسةَ رموزٍ
     * لا أحدَ ينظر إليها. وكان الكنسُ يفعل ذلك كلَّ خمس دقائق لمكتبٍ فصله
     * هاتفُه — فبقيت نسختُه «تنتظر مسحاً» أبداً: يُهمَل فيها الرقمُ حين يطلب
     * المكتبُ رمزَ ربط، ويستنفد عدّادَ رموزها فتُرفض في منتصف مسحه، ويرى
     * واتساب من الخادم طلباتِ ربطٍ لا تنتهي. فلا يُطرق البابُ إلا لنسخةٍ
     * مقترنة (isPairedRow)، ولا وهي تعيد وصلَ نفسها، ولا والمكتبُ يقترن الآن.
     */
    public function reconnect(): string
    {
        if (!$this->isConfigured()) {
            return 'close';
        }

        $state = $this->connectionState();

        if ($state === 'open' || $state === 'connecting' || self::pairingInProgress()) {
            WhatsAppSettings::setEvolutionState($state);

            return $state;
        }

        if (!self::isPairedRow($this->instanceRow())) {
            WhatsAppSettings::setEvolutionState($state);

            return $state;
        }

        // ‏«close» مرّتين: الجسرُ يعيد وصلَ ما سقط بنفسه، و«connect» في لحظة
        // إعادته يفتح جلسةً ثانيةً تتنازع الاعتمادَ نفسَه
        $this->pause(3000);

        if (($again = $this->connectionState()) !== 'close') {
            WhatsAppSettings::setEvolutionState($again);

            return $again;
        }

        $this->nudge(WhatsAppSettings::evolutionInstance());

        try {
            $state = $this->connectionState();
        } catch (\Throwable) {
            return WhatsAppSettings::evolutionState();
        }

        WhatsAppSettings::setEvolutionState($state);

        return $state;
    }

    /** طرقُ باب الوصل بلا رقم — الاعتمادُ المحفوظُ يفتح الجلسةَ وحده. */
    protected function nudge(string $instance): void
    {
        $from = $this->clock();

        try {
            $response = $this->http()->timeout($this->within(10))->get($this->url('instance/connect/' . $instance));
            $this->traced('طرقُ الوصل', $from, 'HTTP ' . $response->status());
        } catch (\Throwable $e) {
            // الجسرُ نفسُه لا يردّ: الحالةُ التالية ستقول ذلك
            $this->traced('طرقُ الوصل', $from, self::transportReason($e));
        }
    }

    /**
     * صفُّ النسخة في قاعدة الجسر — أو null إن لم تكن في ذاكرته.
     *
     * فيه رمزُ النسخة السرّيّ (token): لا يُكتب في سجلٍّ ولا يُعاد إلى الواجهة.
     *
     * @return array<string, mixed>|null
     */
    protected function instanceRow(): ?array
    {
        return $this->instanceLookup()[1];
    }

    /**
     * صفُّ النسخة بثلاثة أجوبة: وُجد، أو أقرّ الجسرُ أنّه لا يعرفها (٤٠٤)، أو لم
     * يُعرف (مهلة، ٥٠٠، جسمٌ غريب). والثالثُ لا يُعامَل معاملةَ الثاني: صمتُ الجسر
     * ليس دليلاً على أنّ النسخة لم تقترن.
     *
     * @return array{0: 'row'|'missing'|'unknown', 1: array<string, mixed>|null}
     */
    protected function instanceLookup(int $timeoutS = 6): array
    {
        $this->lookupFailure = null;
        $this->lookupUnanswered = false;
        $from = $this->clock();

        try {
            $response = $this->http()->timeout($this->within($timeoutS))->get($this->url('instance/fetchInstances'), [
                'instanceName' => WhatsAppSettings::evolutionInstance(),
            ]);
        } catch (\Throwable $e) {
            $this->lookupFailure = self::transportReason($e);
            $this->lookupUnanswered = true;
            $this->traced('السجلّ', $from, $this->lookupFailure);

            return ['unknown', null];
        }

        $this->traced('السجلّ', $from, 'HTTP ' . $response->status());

        if ($response->status() === 404) {
            return ['missing', null];
        }

        if (!$response->successful()) {
            // جسمُ الخطأ لا رمزَ فيه (الرمزُ في صفوف النجاح وحدها) — فيُقال سببُه.
            // وسطرٌ في الدقيقة لا أكثر: الشاشةُ تسأل كلَّ ثانيتين ونصف، فجسرٌ عاطلٌ
            // كان يملأ السجلّ بنحو ١٤٠ سطراً متطابقاً في جلسة اقترانٍ واحدة
            $this->lookupFailure = 'HTTP ' . $response->status();

            if (Cache::add('wa:lookup-failure-logged', true, 60)) {
                Log::warning('Evolution fetchInstances failed (' . $response->status() . '): ' . mb_substr((string) $response->body(), 0, 300));
            }

            return ['unknown', null];
        }

        $rows = $response->json();

        if (is_array($rows) && $rows === []) {
            return ['missing', null];
        }

        $row = is_array($rows) ? (array_is_list($rows) ? ($rows[0] ?? null) : $rows) : null;

        if (!is_array($row)) {
            // ردٌّ ناجحٌ بجسمٍ غير مفهوم (صفحةُ وكيلٍ مثلاً) — بلا سببٍ يُسمّى لقال المكتبُ «بلا ردّ»
            $this->lookupFailure = 'ردٌّ غير مفهوم';

            return ['unknown', null];
        }

        $row = is_array($row['instance'] ?? null) ? $row['instance'] : $row;

        return $row === [] ? ['missing', null] : ['row', $row];
    }

    /** ما بقي من موعد الطلب بالثواني — بلا حدٍّ (INF) خارج طلب الربط. */
    protected function remaining(): float
    {
        return $this->pairDeadline === INF ? INF : $this->pairDeadline - $this->clock();
    }

    /**
     * مهلةُ نداءٍ واحد: المعتادةُ، أو ما بقي من موعد الطلب إن كان أقلّ.
     *
     * مهلٌ ثابتةٌ كانت تتراكم: حذفٌ بلا ردّ (١٥ ث) ثمّ إعادتُه ثمّ إنشاءٌ (٢٥ ث)
     * يتجاوز دقيقةَ الوسيط — فيصل المكتبَ ٥٠٤ بلا رمزٍ ولا سبب.
     */
    protected function within(int $nominalS): int
    {
        $left = $this->remaining();

        return $left === INF ? $nominalS : max(1, min($nominalS, (int) ceil($left)));
    }

    /** الساعةُ — تُبدَّل في الاختبارات لتمضي الثواني بلا انتظار. */
    protected function clock(): float
    {
        return microtime(true);
    }

    /** خطوةٌ في رحلة الطلب: اسمُها وردُّها ومدّتُها. */
    protected function traced(string $step, float $from, string $result): void
    {
        $this->trace[] = $step . ': ' . $result . ' (' . number_format($this->clock() - $from, 1) . ' ث)';
    }

    /**
     * سببُ انقطاعٍ قبل أيّ ردّ، بكلمةٍ يفهمها المشغّل.
     *
     * ‏curl ٨ يقول عن جسرٍ متوقّف «Failed to connect … Couldn't connect to server»
     * لا «Connection refused» — فكان نصُّه بعنوان الجسر ومنفذه يُعرض على الشاشة
     * بدل «متوقّف». وما لم يُعرف يُقتطع بلا عنوان: رسالةُ Guzzle تحمل الرابطَ كلَّه.
     */
    protected static function transportReason(\Throwable $e): string
    {
        $m = mb_strtolower($e->getMessage());

        return match (true) {
            str_contains($m, 'timed out') || str_contains($m, 'timeout') => 'مهلةٌ بلا ردّ',
            str_contains($m, 'refused') || str_contains($m, 'failed to connect') || str_contains($m, "couldn't connect")
                => 'الاتصال مرفوض — الجسرُ متوقّف؟',
            str_contains($m, 'reset by peer') || str_contains($m, 'empty reply') || str_contains($m, 'connection closed')
                => 'انقطع الاتصالُ قبل الردّ — الجسرُ يُعاد تشغيلُه؟',
            str_contains($m, 'resolve') => 'عنوانُ الجسر لا يُعرف',
            default => 'انقطاع: ' . mb_substr(trim((string) preg_replace('~\(see https?://\S+\)|\bfor https?://\S+|https?://\S+~i', '', $e->getMessage())), 0, 60),
        };
    }

    /** مهلةٌ بين خطوات الجسر — تُطوى في الاختبارات. */
    protected function pause(int $ms): void
    {
        // لا يُنام بعد الموعد: ما بقي منه يُترك للنداء التالي ليُقال سببُه
        if ($this->remaining() !== INF) {
            $ms = (int) min($ms, max(0, $this->remaining() * 1000));
        }

        if ($ms > 0) {
            $this->sleep($ms);
        }
    }

    /** النومُ نفسُه — يُطوى في الاختبارات، وتُبدَّل به ساعةٌ تُحرَّك باليد. */
    protected function sleep(int $ms): void
    {
        if (!app()->runningUnitTests()) {
            usleep($ms * 1000);
        }
    }

    // ── الإرسال ──────────────────────────────────────────────────

    public function sendText(string $to, string $body): SendResult
    {
        return $this->post('message/sendText', [
            'number' => $this->recipient($to),
            'text' => $body,
        ]);
    }

    /**
     * لا قوالبَ في واتساب ويب — تُرسَل نصّاً بعد ملء متغيّراتها.
     *
     * والنصُّ يُقرأ من جدول القوالب إن وُجد (فيبقى للمكتب مكانٌ واحد
     * يحرّر منه صيغةَ رسائله)، وإلا فالمتغيّراتُ وحدها مفصولةً بأسطر
     * — وذلك أنفعُ للموكّل من ألّا يصله شيء.
     */
    public function sendTemplate(string $to, string $name, string $language, array $bodyParams = []): SendResult
    {
        return $this->sendText($to, self::renderTemplate($name, $language, $bodyParams));
    }

    /** @param array<int, string> $params */
    public static function renderTemplate(string $name, string $language, array $params): string
    {
        $body = '';

        try {
            $template = \App\Models\WhatsAppTemplate::where('name', $name)
                ->where('language', $language)
                ->first() ?? \App\Models\WhatsAppTemplate::where('name', $name)->first();

            $body = (string) ($template?->body ?? '');
        } catch (\Throwable) {
            // جدولٌ غير مهاجَر — يُبنى النصُّ من المتغيّرات وحدها
        }

        if ($body === '') {
            return implode("\n", array_filter($params, static fn ($p): bool => trim((string) $p) !== ''));
        }

        foreach ($params as $i => $value) {
            $body = str_replace('{{' . ($i + 1) . '}}', (string) $value, $body);
        }

        // متغيّرٌ لم تصله قيمة يُحذف بدل أن يصل الموكّلَ «{{3}}»
        return trim((string) preg_replace('/\{\{\s*\d+\s*\}\}/', '', $body));
    }

    public function sendMedia(
        string $to,
        string $type,
        string $mediaId,
        ?string $caption = null,
        ?string $filename = null,
    ): SendResult {
        // ‏mediaId هنا عنوانٌ أو base64 لا معرّفٌ عند Meta: الجسر لا
        // يملك مخزناً، فيرفع مع الرسالة نفسها
        return $this->post('message/sendMedia', array_filter([
            'number' => $this->recipient($to),
            'mediatype' => $type === 'sticker' ? 'image' : $type,
            'media' => $mediaId,
            'caption' => $caption,
            'fileName' => $filename,
        ], static fn ($v): bool => $v !== null));
    }

    /** لا مخزنَ وسائط في الجسر — الملفّ يُرسَل بمساره كما هو. */
    public function uploadMedia(string $absolutePath, string $mime): ?string
    {
        if (!is_readable($absolutePath)) {
            $this->lastError = 'الملفّ غير مقروء.';

            return null;
        }

        $data = @file_get_contents($absolutePath);

        return $data === false ? null : base64_encode($data);
    }

    /**
     * الوسيطُ الوارد يصل كاملاً في الحمولة — لا عنوانَ يُطلب.
     *
     * فتُعاد بياناتُه من الرسالة المحفوظة بدل نداءٍ لا وجود له عند
     * الجسر. وغيابُ الطريق ليس عطلاً يُخفى: يُقال إنّه غيرُ متاح.
     */
    public function mediaMeta(string $mediaId): ?array
    {
        $this->lastError = 'الجسرُ لا يحفظ الوسائط عنده — تصل مع الرسالة.';

        return null;
    }

    public function downloadMedia(string $url): ?string
    {
        try {
            $response = Http::timeout((int) config('whatsapp.http_timeout_s', 30))->get($url);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    public function markRead(string $wamid): bool
    {
        // الجسرُ يحتاج معرّفَ المحادثة مع معرّف الرسالة، ولا نحفظه —
        // وتعليمُ القراءة تحسينٌ لا شرطٌ لعمل شيء
        return false;
    }

    // ── الفحص ────────────────────────────────────────────────────

    public function testConnection(): array
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'خادم Evolution غير مضبوط — العنوان أو المفتاح ناقص في ملفّ البيئة.';

            return ['ok' => false, 'message' => $this->lastError];
        }

        $state = $this->connectionState();
        WhatsAppSettings::setEvolutionState($state);

        if ($state !== 'open') {
            $this->lastError = $state === 'connecting'
                ? 'النسخة تنتظر مسحَ الرمز من الهاتف.'
                : 'الرقم غير مقترن — امسح الرمز من واتساب في هاتفك.';

            return ['ok' => false, 'message' => $this->lastError];
        }

        $me = $this->instanceOwner();

        if ($me !== null) {
            WhatsAppSettings::rememberIdentity($me['number'], $me['name']);
        }

        return [
            'ok' => true,
            'message' => 'الرقم مقترنٌ ويرسل.',
            'display_phone_number' => $me['number'] ?? '',
            'verified_name' => $me['name'] ?? '',
        ];
    }

    /** لا قوالبَ معتمَدة في الجسر — النصوصُ حرّة. */
    public function fetchTemplates(): array
    {
        $this->lastError = 'لا قوالبَ في واتساب ويب — النصوصُ تُرسَل حرّة.';

        return [];
    }

    /** الويبهوك يُضبط عند الاقتران، فلا يُطلب من المكتب شيء. */
    public function registerWebhook(string $callbackUrl, string $verifyToken, array $fields): bool
    {
        return $this->applyWebhook(WhatsAppSettings::evolutionInstance());
    }

    public function subscribeAccount(): bool
    {
        return true; // لا حسابَ يُشترَك فيه — النسخةُ هي الحساب
    }

    /**
     * لا اشتراكَ منفصلٌ في الجسر: الويبهوك يُضبط على النسخة عند
     * الاقتران. فالجوابُ مشتقٌّ من الاقتران نفسه.
     */
    public function subscribedFields(): ?array
    {
        return $this->connectionState() === 'open' ? ['messages'] : [];
    }

    public function discover(): array
    {
        $me = $this->instanceOwner();

        return [
            'waba_id' => null,
            'phone_number_id' => $me['number'] ?? null,
            'display_phone' => $me['number'] ?? null,
            'choices' => [],
        ];
    }

    // ── داخلي ────────────────────────────────────────────────────

    /**
     * إنشاءُ النسخة — يعيد الرمزَ إن أُنشئت، أو سببَ الإخفاق، أو لا شيء
     * إن كانت قائمةً من قبل. ومعه رمزُ حالة الردّ: «مستعمَل» (٤٠٣ بلا سبب)
     * و٤٠٠ بعد حذفٍ للتوّ يعنيان أنّ الجسر لم يُكمل تنظيفَه بعد فيُعاد.
     *
     * ═══ لماذا الويبهوك في جسم الإنشاء ═══
     *
     * ‏Evolution يقبل الويبهوك داخل «instance/create» ويضبطه في نفس
     * اللحظة. وضبطُه هنا يعني أنّ نسخةً جديدةً لا تعيش لحظةً واحدة بلا
     * عنوانِ استقبال — وكان ضبطُه بطلبٍ ثانٍ يفشل أحياناً فيُوقف
     * الاقترانَ كلَّه ويبقى المكتب بلا رمز.
     *
     * @return array{qr: ?string, code: ?string, error: ?string, status: int}
     */
    protected function createInstance(string $instance, ?string $phone = null): array
    {
        $from = $this->clock();

        try {
            // الجسرُ يُمسك الإنشاءَ خمسَ ثوانٍ ينتظر أوّلَ رمز (instance.controller.ts:153-157)
            $response = $this->http()->timeout($this->within(20))->post($this->url('instance/create'), array_filter([
                'instanceName' => $instance,
                'qrcode' => true,
                'integration' => (string) config('whatsapp.evolution.integration', 'WHATSAPP-BAILEYS'),
                'webhook' => $this->webhookPayload(),
                // رقمٌ مُمرَّر ⇐ رمزُ ربطٍ بدل المسح
                'number' => $phone,
            ], static fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            Log::warning('Evolution instance create failed: ' . $e->getMessage());
            $this->traced('الإنشاء', $from, self::transportReason($e));

            $reason = self::transportReason($e);

            return ['qr' => null, 'code' => null, 'error' => 'تعذّر الاتصال بخادم الجسر لإنشاء نسخة المكتب (' . $reason . ').',
                'status' => 0, 'timedOut' => $reason === 'مهلةٌ بلا ردّ'];
        }

        $this->traced('الإنشاء', $from, 'HTTP ' . $response->status());

        if ($response->successful()) {
            // الرمزُ في جسم الإنشاء نفسِه (qrcode.base64) — يُقرأ الآن
            return ['qr' => $this->qrFrom($response), 'code' => $this->pairingCodeFrom($response), 'error' => null, 'status' => $response->status()];
        }

        $body = (string) $response->body();

        // «الاسم مستعمَل» = النسخةُ قائمة: لا رمزَ هنا ولا خطأ — يُطلب الرمزُ منها
        if (str_contains(mb_strtolower($body), 'already in use') || $response->status() === 409) {
            return ['qr' => null, 'code' => null, 'error' => null, 'status' => $response->status()];
        }

        Log::warning('Evolution instance create rejected (' . $response->status() . '): ' . mb_substr($body, 0, 500));

        // ٥٠٠ من Evolution عند الإنشاء علامتُه الغالبة أنّ قاعدة
        // بياناته غير موصولة: النسخةُ صفٌّ في جدول، فبلا قاعدةٍ لا
        // تُنشأ. وقولُ «أخفق» وحدها يترك المشغّل يبحث في الرقم
        // والمفتاح، والعلّةُ في تنصيب الخادم لا في المكتب.
        //
        // ═══ لماذا يُفرَّق بين ٤٠٤ و٥٠٠ هنا ═══
        //
        // ‏٤٠٤ على «instance/create» ليست «نسخةٌ غير موجودة» — المسارُ
        // نفسُه غير مسجَّل عند الخادم. ويقع ذلك حين يقوم الجسرُ قبل أن
        // تكتمل هجراتُ قاعدته: يردّ على الجذر بـ٢٠٠ ويبدو حيّاً، ولم
        // تُركَّب مساراتُ النسخ بعد.
        //
        // والعلاجان مختلفان تماماً — إعادةُ تشغيلٍ بانتظارٍ أطول في
        // الأولى، وفحصُ وصلِ القاعدة في الثانية — فلا يُقال فيهما نصٌّ
        // واحد يترك المشغّل يجرّب على غير هدى.
        $probe = 'sudo bash scripts/install-evolution.sh status';

        return ['qr' => null, 'code' => null, 'status' => $response->status(), 'error' => match (true) {
            $response->status() === 401 || $response->status() === 403
                => 'خادم الجسر رفض المفتاح — راجع EVOLUTION_API_KEY في ملفّ بيئة المكتب.',
            $response->status() === 404
                => 'خادم الجسر يعمل لكنّ مسار إنشاء النسخ غير مُركَّب عنده'
                    . ' — الأرجح أنّه قام قبل أن تكتمل هجرات قاعدته. على الخادم: ' . $probe,
            $response->status() >= 500
                => 'خادم الجسر لم يستطع إنشاء النسخة — الأرجح أنّ قاعدة بياناته غير موصولة.'
                    . ' على الخادم: ' . $probe,
            default
                => 'خادم الجسر رفض إنشاء النسخة (' . $response->status() . ').',
        }];
    }

    /**
     * حمولةُ الويبهوك بأسماء حقول Evolution 2.
     *
     * ═══ حرفان كلّفا وسائطَ الرسائل ═══
     *
     * كنّا نرسل webhookByEvents و webhookBase64 — وهي أسماءُ الإصدار
     * الأوّل. ومخطَّطُ الثاني يسمّيهما byEvents و base64 ولا يرفض
     * الزائد، فكان الطلبُ ينجح ٢٠١ والإعدادان لا يُضبطان: تصل صورةُ
     * الموكّل بلا محتوى Base64 فلا تُحفظ.
     *
     * @return array<string, mixed>
     */
    protected function webhookPayload(): array
    {
        return [
            'enabled' => true,
            'url' => url('/webhooks/evolution/' . WhatsAppSettings::evolutionSecret()),
            'byEvents' => false,
            'base64' => true,
            'events' => ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
        ];
    }

    /**
     * ضبطُ عنوان الويبهوك على النسخة.
     *
     * والسرُّ في المسار لأنّ الجسر لا يوقّع حمولاتِه: بابٌ بلا سرٍّ
     * يقبل من أيّ أحدٍ رسالةً يزعم أنّها من موكّل، فتُكتب في خيطه
     * ويقرؤها المحامي على أنّها منه.
     */
    protected function applyWebhook(string $instance): bool
    {
        $v2 = $this->webhookPayload();

        // الصيغةُ القديمة احتياطاً: جسورٌ لم تُرقَّ بعد تقرأ هذه وحدها
        $legacy = [
            'enabled' => $v2['enabled'],
            'url' => $v2['url'],
            'webhookByEvents' => false,
            'webhookBase64' => true,
            'events' => $v2['events'],
        ];

        // ثلاثُ صيغٍ تُجرَّب بالترتيب: مغلَّفةٌ بـv2، مغلَّفةٌ بالقديمة،
        // ثمّ مسطّحة. ومخطَّطُ الإصدار الثاني يشترط الغلاف «webhook»،
        // فالمسطّحةُ آخرُ ما يُجرَّب لا أوّلُه.
        $last = null;

        foreach ([['webhook' => $v2], ['webhook' => $legacy], $v2] as $payload) {
            try {
                $response = $this->http()->post($this->url('webhook/set/' . $instance), $payload);
            } catch (\Throwable) {
                $this->lastError = 'تعذّر الاتصال بخادم الجسر لضبط عنوان الاستقبال.';

                return false;
            }

            if ($response->successful()) {
                return true;
            }

            $last = $response;
        }

        $status = $last?->status() ?? 0;

        Log::warning('Evolution webhook set failed (' . $status . '): '
            . mb_substr((string) $last?->body(), 0, 300));

        $this->lastError = 'خادم الجسر رفض ضبط عنوان الاستقبال (' . $status . ').';

        return false;
    }

    /** @return array{number: string, name: string}|null */
    protected function instanceOwner(): ?array
    {
        $instance = $this->instanceRow();

        if ($instance === null) {
            return null;
        }

        $owner = (string) ($instance['owner'] ?? $instance['ownerJid'] ?? '');
        $number = $owner !== '' ? (string) strtok($owner, '@') : '';

        return [
            'number' => $number,
            'name' => (string) ($instance['profileName'] ?? $instance['profilename'] ?? ''),
        ];
    }

    /**
     * رمزُ الربط بالرقم — بابٌ ثانٍ حين يتعذّر المسح.
     *
     * ═══ لماذا بابان ═══
     *
     * واتساب يرفض ربطَ أجهزةٍ جديدةٍ أحياناً («Can't link new devices
     * right now») بعد محاولاتٍ متكرّرة، فيقف المكتبُ أمام رمزٍ صحيحٍ
     * لا يُقبل. والربطُ بالرقم مسارٌ آخر عند واتساب نفسِه: ثمانيةُ
     * محارفَ تُكتب في الهاتف، وهو الخيارُ المعروض في شاشة المسح
     * («Link with phone number instead»).
     */
    protected function pairingCodeFrom(Response $response): ?string
    {
        $code = $response->json('pairingCode')
            ?? $response->json('qrcode.pairingCode')
            ?? $response->json('qr.pairingCode');

        return is_string($code) && $code !== '' ? $code : null;
    }

    protected function qrFrom(Response $response): ?string
    {
        $qr = $response->json('base64')
            ?? $response->json('qrcode.base64')
            ?? $response->json('qr.base64');

        if (!is_string($qr) || $qr === '') {
            $code = $response->json('code') ?? $response->json('qrcode.code');

            return is_string($code) && $code !== '' ? 'text:' . $code : null;
        }

        // بعضُ الإصدارات تُرجعه ببادئة data: وبعضُها بلا
        return str_starts_with($qr, 'data:') ? $qr : 'data:image/png;base64,' . $qr;
    }

    /** @param array<string, mixed> $payload */
    protected function post(string $path, array $payload): SendResult
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            return SendResult::failed(null, 'خادم Evolution غير مضبوط.', retryable: false);
        }

        try {
            $response = $this->http()->post(
                $this->url($path . '/' . WhatsAppSettings::evolutionInstance()),
                $payload,
            );
        } catch (\Throwable) {
            // انقطاعُ شبكةٍ عابر: تُعاد المحاولة ولا تُسقَط رسالةُ موكّل
            return SendResult::failed(null, 'تعذّر الاتصال بخادم الإرسال.', retryable: true);
        }

        if (!$response->successful()) {
            $this->failureFrom($response);

            // ‏401/403 اعتمادٌ خاطئ — إعادةُ المحاولة لا تُصلحه.
            // و404 نسخةٌ غير موجودة: تحتاج اقتراناً لا محاولةً أخرى.
            //
            // ═══ لكنّ ٤٠٠ «Connection Closed» عابرةٌ لا دائمة ═══
            //
            // ‏Evolution يردّ بـ٤٠٠ حين تكون الجلسةُ ساقطةً لحظةَ الإرسال.
            // وعدُّها دائمةً كان يقتل رسالةَ الموكّل نهائياً ويكتب
            // «أخفق» — بينما الكنسُ يعيد وصلَ الرقم بعد دقيقة والرسالةُ
            // ماتت بلا إعادة. فما كان سببُه سقوطَ الاتصال يُعاد.
            $transient = $response->status() === 400
                && preg_match('/connection closed|connection lost|not connected|closed connection|no session/i', (string) $this->lastError) === 1;

            $permanent = !$transient && in_array($response->status(), [400, 401, 403, 404], true);

            return SendResult::failed((string) $response->status(), (string) $this->lastError, retryable: !$permanent);
        }

        $id = $response->json('key.id') ?? $response->json('messageId');

        return SendResult::sent(is_string($id) ? $id : ('evo.' . uniqid()));
    }

    protected function failureFrom(Response $response): void
    {
        // ═══ النصُّ الحقيقي في response.message لا في message ═══
        //
        // جسمُ الخطأ عند Evolution v2 هكذا:
        //   {"status":400,"error":"Bad Request",
        //    "response":{"message":["Connection Closed"]}}
        //
        // فلا مفتاحَ «message» في الأعلى، وكان يقع الاختيارُ على
        // «error» فيُخزَّن السببُ «Bad Request» — وهي لا تفرّق بين
        // «الرقم ليس على واتساب» (عطلٌ دائم) و«انقطع الاتصال»
        // (عابرٌ تُعاد معه المحاولة). وهذا هو ما يُعرض للمكتب حين
        // يسأل: لماذا لم تصل؟
        $message = $response->json('response.message')
            ?? $response->json('message')
            ?? $response->json('error')
            ?? '';

        if (is_array($message)) {
            $message = implode(' — ', array_map(static fn ($m): string => (string) $m, $message));
        }

        $message = trim((string) $message);

        $this->lastError = match (true) {
            $response->status() === 401 || $response->status() === 403
                => 'خادم Evolution رفض المفتاح — راجع EVOLUTION_API_KEY.',
            $response->status() === 404
                => 'نسخةُ المكتب غير موجودة على الخادم — أعد الاقتران.',
            $message !== '' => mb_substr($message, 0, 190),
            default => 'أخفق الطلب عند خادم Evolution (' . $response->status() . ').',
        };

        WhatsAppSettings::recordError((string) $this->lastError);
    }

    /** رقمُ المستقبِل بالصيغة التي يقبلها الجسر: أرقامٌ بلا زائد. */
    protected function recipient(string $to): string
    {
        return preg_replace('/\D+/', '', $to) ?: $to;
    }

    protected function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    protected function http()
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->timeout((int) config('whatsapp.http_timeout_s', 30))
            ->connectTimeout((int) config('whatsapp.connect_timeout_s', 10))
            ->acceptJson();
    }
}
