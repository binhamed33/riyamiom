<?php

namespace App\Services\WhatsApp;

use App\Models\Setting;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;

/**
 * حاكمُ الإرسال — ما يخفض احتمالَ حظر الرقم.
 *
 * ═══ ما يُحظَر لأجله رقمٌ فعلاً ═══
 *
 * ليس «استعمالُ أداة» بحدّ ذاته ما يُرصد أوّلاً، بل السلوك: دفعةٌ من
 * الرسائل في دقيقة، وإرسالٌ إلى من لم يراسلك قطّ، ونصٌّ واحدٌ يتكرّر
 * على عشرات الأرقام، ورسائلُ في الثالثة فجراً — ثمّ بلاغاتٌ وحظرُ
 * مستقبِلين. البلاغُ هو الوقودُ الحقيقي.
 *
 * فالحمايةُ ليست إخفاءَ الأداة بل تقليلَ ما يُشتكى منه:
 *
 *  ١) لا يُراسَل إلا موكّلٌ في سجلّ المكتب — لا رقمٌ عابر.
 *  ٢) وبمهلةٍ بين رسالةٍ وأخرى، متفاوتةٍ لا ثابتة.
 *  ٣) وبسقفٍ في الساعة وسقفٍ في اليوم.
 *  ٤) ولا يُرسَل في ساعات النوم — رسالةُ الثالثة فجراً تُبلَّغ.
 *  ٥) وبتدرّجٍ في الأيام الأولى بعد الاقتران: رقمٌ جديد يندفع فجأةً
 *     أظهرُ من رقمٍ يزيد قليلاً كلَّ يوم.
 *
 * ═══ وما لا يفعله ═══
 *
 * لا يَعِد بشيء. لا ضمانَ في هذا الطريق، والضمانُ الوحيد هو الواجهةُ
 * الرسمية. هذا يخفض الاحتمال ولا يُلغيه.
 */
class SendingGuard
{
    public const KEY_ENABLED = 'wa_guard_enabled';
    public const KEY_PER_HOUR = 'wa_guard_per_hour';
    public const KEY_PER_DAY = 'wa_guard_per_day';
    public const KEY_MIN_GAP = 'wa_guard_min_gap_s';
    public const KEY_QUIET_FROM = 'wa_guard_quiet_from';
    public const KEY_QUIET_TO = 'wa_guard_quiet_to';
    public const KEY_CLIENTS_ONLY = 'wa_guard_clients_only';
    public const KEY_PAIRED_AT = 'wa_guard_paired_at';

    public const GROUP = 'whatsapp';

    /**
     * حدودٌ محافظة: مكتبُ محاماةٍ يراسل موكّليه لا سوقاً.
     *
     * ومئةٌ في اليوم بسقف خمسَ عشرةَ في الساعة متّسقان: نافذةُ النهار
     * ‏(٨ ← ٢١) تسع مئةً وخمساً وتسعين، فالسقفُ اليومي هو الحدُّ لا
     * الساعيّ — ولا يقع أن يبلغ المكتبُ يومَه ثمّ يجد ساعتَه مغلقة.
     */
    public const DEFAULT_PER_HOUR = 15;
    public const DEFAULT_PER_DAY = 100;
    public const DEFAULT_MIN_GAP = 15;
    public const DEFAULT_QUIET_FROM = 21;
    public const DEFAULT_QUIET_TO = 8;

    /** أيامُ التدرّج بعد أوّل اقتران، ونسبةُ السقف في أوّلها. */
    private const WARMUP_DAYS = 7;

    /**
     * ═══ مفاتيحُ لا يملكها المكتب ═══
     *
     * ثلاثةُ إعداداتٍ تحمي الرقمَ نفسَه: تفعيلُ الحدود، وقصرُ
     * المراسلة على الموكّلين، وإظهارُ صندوق الوارد. وإطفاءُ أيٍّ منها
     * يفتح الطريقَ إلى ما يُحظَر لأجله رقمٌ في يوم — والرقمُ حين
     * يُحظر لا يُستردّ، ولا يُصلحه ندمُ من أطفأه.
     *
     * فهي مقروءةٌ للمكتب ومقفلةٌ عليه، ولا يبدّلها إلا المطوّر. ومديرُ
     * المكتب يرى حالتَها ويعرف لماذا هي كذلك — لا تُخفى عنه.
     */
    public static function lockedForOffice(): bool
    {
        return !(auth()->user()?->isDeveloper() ?? false);
    }

    /**
     * أسماءُ الإعدادات المقفلة — يقرؤها المتحكّم فيتجاهلها.
     *
     * والأرقامُ مقفلةٌ كالمفاتيح: سقفٌ يُرفع إلى ألفٍ يُبطل الحمايةَ
     * كما يُبطلها إطفاؤها، ومهلةٌ تُنزَّل إلى ثلاثِ ثوانٍ تجعل
     * الإرسالَ دفعةً واحدة. فالحدُّ الذي يملك المكتبُ توسيعَه ليس حدّاً.
     */
    public static function lockedKeys(): array
    {
        return [
            self::KEY_ENABLED,
            self::KEY_CLIENTS_ONLY,
            self::KEY_PER_HOUR,
            self::KEY_PER_DAY,
            self::KEY_MIN_GAP,
            self::KEY_QUIET_FROM,
            self::KEY_QUIET_TO,
            \App\Support\WhatsAppSettings::KEY_INBOX_VISIBLE,
        ];
    }

    public static function enabled(): bool
    {
        return self::setting(self::KEY_ENABLED, '1') !== '0';
    }

    public static function clientsOnly(): bool
    {
        return self::setting(self::KEY_CLIENTS_ONLY, '1') !== '0';
    }

    /**
     * هل يجوز الإرسال الآن؟ وإن لا، فبعد كم ثانية يُعاد السؤال؟
     *
     * تُعيد `null` إن جاز، أو عددَ الثواني التي تُؤجَّل بها الرسالة.
     * والتأجيلُ لا الإسقاط: رسالةُ الموكّل لا تُلغى لأنّ الساعة
     * متأخّرة — تنتظر الصباح.
     */
    public static function delayFor(WhatsAppMessage $message): ?int
    {
        if (!self::enabled()) {
            return null;
        }

        $now = Carbon::now(config('app.timezone', 'Asia/Muscat'));

        // ═══ ساعاتُ الصمت لا تمنع ردَّ إنسان ═══
        //
        // الصمتُ الليلي وُضع لئلّا يوقظ النظامُ موكّلاً برسالةٍ آليّة
        // في الثالثة فجراً. أمّا محامٍ يجلس الآن ويكتب رداً بيده في
        // محادثةٍ مفتوحة، فذاك سلوكُ إنسانٍ عادي — وهو ما يريده واتساب
        // لا ما يعاقب عليه.
        //
        // ومنعُه كان يعني أن تُحجَز رسالتُه خمسَ ساعات وهو ينتظر
        // وصولَها — أو تُعلَن «فشلاً» كما وقع فعلاً.
        //
        // والسقوفُ تبقى عليه: عشرون رسالةً يدويّةً في الساعة سلوكٌ
        // آخر، وليس رداً على محادثة.
        $byHuman = $message->sent_by !== null;

        if (!$byHuman && ($wait = self::quietDelay($now))) {
            return $wait;
        }

        // ── المهلةُ بين رسالةٍ وأخرى ────────────────────────────
        $last = WhatsAppMessage::where('direction', WhatsAppMessage::OUT)
            ->whereNotNull('sent_at')
            ->where('id', '!=', $message->id)
            ->max('sent_at');

        if ($last !== null) {
            $gap = self::minGap();
            $since = Carbon::parse($last)->diffInSeconds($now, absolute: true);

            if ($since < $gap) {
                // تفاوتٌ لا ثبات: إيقاعٌ ثابتٌ بالثانية أظهرُ من
                // إنسانٍ يردّ حين يفرغ
                return (int) ($gap - $since) + random_int(1, 9);
            }
        }

        // ── السقفان ─────────────────────────────────────────────
        $hourly = self::sentSince($now->copy()->subHour());

        if ($hourly >= self::perHour()) {
            return 600;
        }

        $daily = self::sentSince($now->copy()->startOfDay());

        if ($daily >= self::perDay()) {
            // إلى الغد: العدُّ يوميٌّ لا متحرّك
            return max(60, $now->copy()->addDay()->startOfDay()->diffInSeconds($now, absolute: true));
        }

        return null;
    }

    /** كم بقي من سقف اليوم — للعرض في الشاشة. */
    public static function remainingToday(): int
    {
        $now = Carbon::now(config('app.timezone', 'Asia/Muscat'));

        return max(0, self::perDay() - self::sentSince($now->copy()->startOfDay()));
    }

    // ── الحدود ───────────────────────────────────────────────────

    public static function perHour(): int
    {
        return self::warmed((int) self::setting(self::KEY_PER_HOUR, (string) self::DEFAULT_PER_HOUR));
    }

    public static function perDay(): int
    {
        return self::warmed((int) self::setting(self::KEY_PER_DAY, (string) self::DEFAULT_PER_DAY));
    }

    public static function minGap(): int
    {
        return max(3, (int) self::setting(self::KEY_MIN_GAP, (string) self::DEFAULT_MIN_GAP));
    }

    /** أوّلُ اقتران — يُختم مرّةً ولا يُعاد، فالتدرّج يُحسب منه. */
    public static function markPaired(): void
    {
        if (self::setting(self::KEY_PAIRED_AT, '') === '') {
            Setting::set(self::KEY_PAIRED_AT, now()->toIso8601String(), self::GROUP);
        }
    }

    /**
     * السقفُ متدرّجٌ في الأيام الأولى.
     *
     * رقمٌ اقترن اليوم وأرسل خمسين رسالةً ملفتٌ بذاته. فيبدأ بالخُمس
     * ويصعد يوماً بيوم حتى يبلغ السقفَ في اليوم السابع.
     */
    private static function warmed(int $limit): int
    {
        $pairedAt = self::setting(self::KEY_PAIRED_AT, '');

        if ($pairedAt === '') {
            return $limit;
        }

        try {
            $days = Carbon::parse($pairedAt)->diffInDays(now());
        } catch (\Throwable) {
            return $limit;
        }

        if ($days >= self::WARMUP_DAYS) {
            return $limit;
        }

        $share = ($days + 1) / self::WARMUP_DAYS;

        return max(3, (int) floor($limit * $share));
    }

    /** @return int|null ثوانٍ حتى نهاية الصمت، أو null إن لم نكن فيه */
    private static function quietDelay(Carbon $now): ?int
    {
        $from = (int) self::setting(self::KEY_QUIET_FROM, (string) self::DEFAULT_QUIET_FROM);
        $to = (int) self::setting(self::KEY_QUIET_TO, (string) self::DEFAULT_QUIET_TO);

        if ($from === $to) {
            return null; // بلا ساعات صمت
        }

        $hour = (int) $now->format('H');

        // النافذةُ تعبر منتصف الليل عادةً (٢١ ← ٨)
        $inQuiet = $from > $to
            ? ($hour >= $from || $hour < $to)
            : ($hour >= $from && $hour < $to);

        if (!$inQuiet) {
            return null;
        }

        $wake = $now->copy()->setTime($to, random_int(0, 20));

        if ($wake->lessThanOrEqualTo($now)) {
            $wake->addDay();
        }

        return max(60, (int) $wake->diffInSeconds($now, absolute: true));
    }

    private static function sentSince(Carbon $since): int
    {
        return WhatsAppMessage::where('direction', WhatsAppMessage::OUT)
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $since)
            ->count();
    }

    private static function setting(string $key, string $default): string
    {
        try {
            $value = Setting::get($key);
        } catch (\Throwable) {
            return $default;
        }

        return $value === null || $value === '' ? $default : (string) $value;
    }
}
