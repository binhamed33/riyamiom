<?php

namespace App\Services\ClientPortal;

use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\ClientPortalLink;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * الرابطُ الذي يفتح البوابة من رسالة واتساب.
 *
 * ═══ ما لا يُفعل ═══
 *
 * لا `?client_id=123`، ولا `/case/123` بلا تحقّق. معرّفٌ في عنوانٍ
 * ليس حمايةً بل دعوةٌ لتغييره: الموكّل «أ» يكتب 124 فيرى ملفّ «ب».
 *
 * ═══ ما يُفعل ═══
 *
 * رمزٌ عشوائيٌّ لا يُخمَّن (٦٤ محرفاً من مولّد آمن)، تُخزَّن **بصمتُه**
 * وحدها. فنسخةٌ من قاعدة البيانات — احتياطيّةٌ مسرَّبة أو قرصٌ
 * مُهمَل — لا تعطي رابطاً واحداً صالحاً.
 *
 * وله ثلاثةُ حدود: مدّةٌ تنتهي، واستعمالٌ واحد، وإبطالٌ صريح.
 * ورسالةُ واتساب تبقى في الهاتف سنين، وهاتفٌ يُباع بعد عامٍ لا يجوز
 * أن يفتح ملفَّ قضيّة.
 *
 * ═══ وهو لا يوسّع صلاحيةَ أحد ═══
 *
 * فتحُه يُنشئ جلسةَ بوابةٍ عاديّة لهذا الموكّل — نفسَ ما ينشئه دخولُه
 * بالهوية وآخرِ ثلاثة أرقام. وكلُّ صفحةٍ بعده تمرّ بـClientDataGateway
 * الذي يحصر الاستعلام في قضاياه هو. فالرابطُ اختصارُ دخولٍ لا مفتاحُ
 * تجاوز.
 */
class PortalLinks
{
    public const KEY_ENABLED = 'cn_links_enabled';
    public const KEY_TTL_HOURS = 'cn_links_ttl_hours';

    /** المدّةُ الافتراضية — أسبوعٌ يكفي لمن يفتح رسالته متأخّراً. */
    public const DEFAULT_TTL_HOURS = 168;

    public static function enabled(): bool
    {
        try {
            return Setting::get(self::KEY_ENABLED, '1') !== '0';
        } catch (\Throwable) {
            return true;
        }
    }

    public static function ttlHours(): int
    {
        try {
            $raw = (int) Setting::get(self::KEY_TTL_HOURS, self::DEFAULT_TTL_HOURS);
        } catch (\Throwable) {
            $raw = self::DEFAULT_TTL_HOURS;
        }

        // بين ساعةٍ وثلاثين يوماً: صفرٌ يعطّل كلَّ رابطٍ صامتاً، وسنةٌ
        // تُبطل معنى المدّة أصلاً
        return max(1, min(720, $raw ?: self::DEFAULT_TTL_HOURS));
    }

    /**
     * رابطٌ جديد — يعيد العنوانَ كاملاً، والرمزُ لا يُخزَّن.
     *
     * ويعيد رابطَ الدخول العادي إن أطفأ المكتبُ الروابطَ الموقّعة:
     * الموكّل يدخل بهويّته كما كان، ولا يُترك بلا طريق.
     */
    public static function for(
        Client $client,
        string $target = 'home',
        ?int $targetId = null,
        ?ClientNotification $notification = null,
    ): string {
        if (!self::enabled()) {
            return route('client.access');
        }

        try {
            $token = Str::random(64);

            ClientPortalLink::create([
                'client_id' => $client->id,
                'notification_id' => $notification?->id,
                'token_hash' => self::hash($token),
                'target' => $target,
                'target_id' => $targetId,
                'expires_at' => now()->addHours(self::ttlHours()),
            ]);

            return route('client.link.open', ['token' => $token]);
        } catch (\Throwable) {
            // جدولٌ غير مهاجَر، أو قرصٌ ممتلئ: يُعطى الموكّل بابَ
            // الدخول العادي بدل رسالةٍ بلا رابط
            return route('client.access');
        }
    }

    /** الصفُّ المطابق لرمزٍ ورد — أو null. */
    public static function find(string $token): ?ClientPortalLink
    {
        if ($token === '') {
            return null;
        }

        return ClientPortalLink::where('token_hash', self::hash($token))->first();
    }

    /**
     * بصمةٌ حتميّة بمفتاح التطبيق.
     *
     * ‏hash_hmac لا hash المجرّد: بصمةٌ بلا مفتاح تُبنى لها جداولُ
     * مقارنةٍ مسبقة. والمفتاحُ مفتاحُ هذا المكتب — فبصمةٌ نُسخت إلى
     * قاعدة مكتبٍ آخر لا تطابق شيئاً عنده.
     */
    public static function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    /** إبطالُ كلّ روابط موكّلٍ — يُنادى عند تغيّر هاتفه أو حذفه. */
    public static function revokeAllFor(Client $client): int
    {
        try {
            return ClientPortalLink::where('client_id', $client->id)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** تقليمُ المنتهية — يُنادى من المجدوَل. */
    public static function prune(int $keepDays = 30): int
    {
        try {
            return ClientPortalLink::where('expires_at', '<', now()->subDays($keepDays))->delete();
        } catch (\Throwable) {
            return 0;
        }
    }
}
