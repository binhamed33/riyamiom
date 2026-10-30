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
    public const PAIR_DEADLINE_S = 40;

    protected float $pairDeadline = INF;

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
        // أطولُ من أسوأ رحلةٍ يسمح بها موعدُ PAIR_DEADLINE_S — وإلا انقضى القفلُ
        // والطلبُ ما زال يحذف وينشئ، فدخل طلبٌ ثانٍ عليه
        $lock = Cache::lock('wa:pair:' . $instance, 90);

        if (!$lock->get()) {
            return ['qr' => null, 'code' => null, 'state' => 'connecting',
                'message' => 'طلبُ رمزٍ سابقٌ ما زال يُجهَّز — انتظر ثوانيَ ثم أعد المحاولة.'];
        }

        try {
            Cache::put(self::pairingKey($instance), true, now()->addMinutes(self::PAIRING_WINDOW_MIN));
            $this->pairDeadline = microtime(true) + self::PAIR_DEADLINE_S;

            return $this->pairLocked($instance, $phone);
        } finally {
            $lock->release();
        }
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
    }

    /** @return array{qr: ?string, code: ?string, state: string, message: ?string} */
    protected function pairLocked(string $instance, ?string $phone): array
    {
        $state = $this->connectionState();

        // موصولٌ أصلاً: لا يُعرض رمزٌ لمن لا يحتاجه
        if ($state === 'open') {
            return $this->opened('الرقم موصولٌ بالفعل.');
        }

        [$found, $row] = $this->instanceLookup();

        // ═══ لا يُحذف شيءٌ على جهل ═══
        //
        // جسرٌ لا يجيب عن صفّ النسخة (مهلة، ٥٠٠، قاعدتُه تُعاد) لا يُستدلّ بصمته
        // على أنّها لم تقترن: الحذفُ عنده يبدأ بإلغاء اقتران الهاتف. فيُقال ذلك.
        if ($found === 'unknown') {
            $this->lastError = 'الجسرُ لا يجيب الآن عن حالة نسخة المكتب — لم يُغيَّر شيء. أعد المحاولة بعد دقيقة.';

            return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $this->lastError];
        }

        // ═══ نسخةٌ لا يعرفها الجسرُ الآن — وقد يكون يقلع ═══
        //
        // بعد إعادة تشغيل الجسر لا تكون النسخُ المقترنةُ في ذاكرته حتى يفرغ من
        // تحميلها (monitor.service.ts:296-307 — ومعه جلبُ إصدار واتساب بلا مهلة)،
        // فتبدو «غير موجودة». والإنشاءُ حينها يصطدم بصفّها فيمسحه الجسرُ مع
        // اعتمادها — يقطع اقتراناً صالحاً. فمكتبٌ اقترن من قبلُ يُمهَل الجسرُ له:
        // ثوانيَ هنا، ثمّ لا يُنشأ بديلٌ إلا إن بقيت غائبةً دقيقةً ونصفاً على الأقلّ.
        if ($found === 'missing' && (string) \App\Models\Setting::get(WhatsAppSettings::KEY_CONNECTED_AT, '') !== '') {
            for ($i = 0; $i < 3 && $found === 'missing' && !$this->pastDeadline(); $i++) {
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

        // ‏٤٠٤ هنا لا شيءَ يُحذف (لم تُنشأ أو ليست في ذاكرة الجسر) — يُمضى إلى الإنشاء
        if (!$this->deleteInstance($instance) && !$this->pastDeadline()) {
            $this->pause(1500);
            $this->deleteInstance($instance);
        }

        // الحذفُ يُكمل تنظيفَ قاعدة الجسر بعد أن يردّ (monitor.service.ts:393-406)،
        // فإنشاءٌ فوريٌّ قد يجد الاسمَ «مستعمَلاً» أو الصفَّ باقياً (٤٠٠). يُعاد
        // ثلاثاً بمهلةٍ قصيرة، ولا يُعاد على رفض المفتاح أو عطلٍ في الخادم.
        $created = ['qr' => null, 'code' => null, 'error' => null, 'status' => 0];

        for ($try = 0; $try < 4; $try++) {
            if ($try > 0 && $this->pastDeadline()) {
                break;
            }

            $created = $this->createInstance($instance, $phone);

            // ‏٤٠٣ برفض المفتاح يحمل سببَه (error) ولا يُعاد؛ و٤٠٣ «مستعمَل» بلا سبب
            $busy = in_array($created['status'], [400, 409], true)
                || ($created['status'] === 403 && $created['error'] === null);

            if (!$busy) {
                break;
            }

            $this->pause(1500);
        }

        if ($created['qr'] !== null || $created['code'] !== null) {
            WhatsAppSettings::setEvolutionState('connecting');

            // بالرقم يُعرض الرمزُ وحده: رمزُ المسح المرافق له ليس ما طلبه المكتب
            return [
                'qr' => $phone === null ? $created['qr'] : null,
                'code' => $created['code'],
                'state' => 'connecting',
                'message' => $phone !== null && $created['code'] === null ? 'يُطلب رمزُ الربط من واتساب…' : null,
            ];
        }

        if ($created['error'] !== null) {
            $this->lastError = $created['error'];

            return ['qr' => null, 'code' => null, 'state' => 'close', 'message' => $created['error']];
        }

        if ($created['status'] >= 200 && $created['status'] < 300) {
            // أُنشئت ولم يصل رمزُها في مهلة الجسر (خمسُ ثوانٍ): يصل في السؤال التالي
            WhatsAppSettings::setEvolutionState('connecting');

            return ['qr' => null, 'code' => null, 'state' => 'connecting',
                'message' => $phone !== null ? 'يُطلب رمزُ الربط من واتساب…' : 'يُجهَّز رمزُ المسح…'];
        }

        $this->lastError = 'لم يقبل الجسرُ إنشاءَ جلسةٍ جديدة الآن — أعد المحاولة بعد دقيقة.';

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
        $state = $this->connectionState();
        $none = ['state' => $state, 'qr' => null, 'code' => null, 'count' => null];

        if ($state !== 'connecting') {
            return $none;
        }

        $row = $this->instanceRow();

        if ((string) ($row['connectionStatus'] ?? '') !== 'connecting') {
            return $none;
        }

        // ما أرسله الجسرُ نفسُه في ويبهوكه — بلا نداءٍ قد يفتح جلسةً ثانية
        $latest = Cache::get(self::latestKey(WhatsAppSettings::evolutionInstance()));

        if (is_array($latest)) {
            return ['state' => 'connecting', 'qr' => $latest['qr'] ?? null, 'code' => $latest['code'] ?? null,
                'count' => isset($latest['seq']) ? (int) $latest['seq'] : null];
        }

        // الويبهوكُ لا يصلنا (الجسرُ لا يبلغ عنوانَ المكتب): يُسأل «connect» —
        // بعد تأكيد الحالة في اللحظة نفسها، فـ«close» عنده يعني جلسةً ثانية
        if ($this->connectionState() !== 'connecting') {
            return $none;
        }

        try {
            $response = $this->http()->timeout(8)->get($this->url('instance/connect/' . WhatsAppSettings::evolutionInstance()));
        } catch (\Throwable) {
            return $none;
        }

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
        try {
            $response = $this->http()->timeout(15)->delete($this->url('instance/delete/' . $instance));
        } catch (\Throwable) {
            return false;
        }

        if (!$response->successful()) {
            // ‏٤٠٤ هنا نجاحٌ: لا شيءَ في ذاكرة الجسر يُحذف
            return $response->status() === 404;
        }

        return true;
    }

    /** حالةُ الاقتران عند الخادم: open|connecting|close. */
    public function connectionState(): string
    {
        if (!$this->isConfigured()) {
            return 'close';
        }

        try {
            $response = $this->http()->timeout(8)->get(
                $this->url('instance/connectionState/' . WhatsAppSettings::evolutionInstance())
            );
        } catch (\Throwable) {
            return 'close';
        }

        if (!$response->successful()) {
            return 'close';
        }

        $state = (string) ($response->json('instance.state') ?? $response->json('state') ?? 'close');

        return in_array($state, ['open', 'connecting', 'close'], true) ? $state : 'close';
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
        try {
            $this->http()->timeout(15)->get($this->url('instance/connect/' . $instance));
        } catch (\Throwable) {
            // الجسرُ نفسُه لا يردّ: الحالةُ التالية ستقول ذلك
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
    protected function instanceLookup(): array
    {
        try {
            $response = $this->http()->timeout(8)->get($this->url('instance/fetchInstances'), [
                'instanceName' => WhatsAppSettings::evolutionInstance(),
            ]);
        } catch (\Throwable) {
            return ['unknown', null];
        }

        if ($response->status() === 404) {
            return ['missing', null];
        }

        if (!$response->successful()) {
            return ['unknown', null];
        }

        $rows = $response->json();

        if (is_array($rows) && $rows === []) {
            return ['missing', null];
        }

        if (!is_array($rows)) {
            return ['unknown', null];
        }

        $row = array_is_list($rows) ? ($rows[0] ?? null) : $rows;

        if (!is_array($row)) {
            return ['unknown', null];
        }

        $row = is_array($row['instance'] ?? null) ? $row['instance'] : $row;

        return $row === [] ? ['missing', null] : ['row', $row];
    }

    protected function pastDeadline(): bool
    {
        return microtime(true) >= $this->pairDeadline;
    }

    /** مهلةٌ بين خطوات الجسر — تُطوى في الاختبارات. */
    protected function pause(int $ms): void
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
        try {
            // الجسرُ يُمسك الإنشاءَ خمسَ ثوانٍ ينتظر أوّلَ رمز (instance.controller.ts:153-157)
            $response = $this->http()->timeout(25)->post($this->url('instance/create'), array_filter([
                'instanceName' => $instance,
                'qrcode' => true,
                'integration' => (string) config('whatsapp.evolution.integration', 'WHATSAPP-BAILEYS'),
                'webhook' => $this->webhookPayload(),
                // رقمٌ مُمرَّر ⇐ رمزُ ربطٍ بدل المسح
                'number' => $phone,
            ], static fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            Log::warning('Evolution instance create failed: ' . $e->getMessage());

            return ['qr' => null, 'code' => null, 'error' => 'تعذّر الاتصال بخادم الجسر لإنشاء نسخة المكتب.', 'status' => 0];
        }

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
