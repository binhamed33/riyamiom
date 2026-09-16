<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * أثرُ الموظّف: «نشِطٌ الآن» للوحة، و«آخرُ نشاطٍ بشريّ» لسجلّ الحضور.
 *
 * ═══ الطلباتُ البشريّة وحدها ═══
 *
 * الواجهةُ تقرع الخادمَ من تلقاء نفسها: مزامنةٌ كلَّ ثلاثين ثانية، وإبقاءٌ
 * على الجلسة، وعدّادُ الإشعارات. هذه لا تعني أنّ إنساناً أمام الشاشة —
 * فتبويبٌ نُسي مفتوحاً كان يُبقي «آخرَ نشاط» حيّاً ساعاتٍ بعد خروج
 * صاحبه، ويُقرأ في سقف المناوبة حضوراً. فلا يُختم الأثرُ إلا من طلبٍ
 * فيه فعلُ إنسان: صفحةٌ فُتحت أو نموذجٌ أُرسل.
 *
 * ═══ ولماذا عمودٌ لا الكاش ولا جدولُ الجلسات ═══
 *
 * الكاشُ عمرُه ثماني دقائق، وصفُّ الجلسة يُحذف بالخروج ويُكنَس بعد
 * ساعتين — فمن خرج من النظام الرابعةَ ضاع أثرُه وأُقفل سجلُّه بالسقف
 * قبلها بساعات. العمودُ يبقى.
 */
class TrackUserActivity
{
    /**
     * مساراتُ الآلة: تُجدَّد بلا يد إنسان فلا تُعدّ نشاطاً.
     *
     * والخروجُ معها: خروجُ الخمول نموذجٌ يُرسله المؤقّت لا الإنسان، وخروجُ
     * الزرّ يختمه LoginController بنفسه.
     */
    public const MACHINE_ROUTES = [
        'sync', 'session.keepalive', 'notifications.count', 'notifications.latest',
        'chat.unread', 'chat.messages.fetch', 'assistant.history', 'logout',
    ];

    /**
     * هل هذا طلبُ آلة؟ بالاسم، أو أيُّ قراءةٍ بـXHR: إعادةُ جلب الصفحة عند
     * تغيّر البيانات (refreshContent) تحمل اسمَ الصفحة نفسِها — فتبويبٌ منسيٌّ
     * على قائمة القضايا كان يُختم نشاطاً كلّما عدّل زميلٌ قضية.
     */
    public static function isMachine(Request $request): bool
    {
        return in_array((string) $request->route()?->getName(), self::MACHINE_ROUTES, true)
            || ($request->isMethod('GET') && $request->ajax());
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'client') {
            $key = 'staff_active_' . $user->id;
            $last = Cache::get($key);

            if (!$last || $last->lt(now()->subMinute())) {
                Cache::put($key, now(), now()->addMinutes(8));
            }

            if (! self::isMachine($request)) {
                $this->stampHumanActivity($user);
                $this->settlePendingLogin($user);
            }
        }

        return $next($request);
    }

    /**
     * حضورٌ أُجّل: دخولُ «تذكّرني» أطلقه طلبُ آلة (نبضةُ مزامنةٍ من تبويبٍ
     * نائم) فأرجأه المستمعُ إلى أوّل طلبٍ بشريّ — وهو هذا.
     */
    private function settlePendingLogin($user): void
    {
        try {
            if (! session()->pull('attendance_login_pending')) {
                return;
            }

            $result = \App\Support\AttendanceGuard::checkInOnLogin($user);

            if ($result && ($result['created'] || $result['resumed'])) {
                session()->put('attendance_flash', [
                    'created' => $result['created'],
                    'resumed' => $result['resumed'],
                    'at' => $result['record']->intervalStart()->timezone('Asia/Muscat')->format('h:i A'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('deferred check-in failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * ختمٌ كلَّ دقيقةٍ لا كلَّ طلب، وبلا لمس updated_at ولا مراقبي النموذج.
     */
    private function stampHumanActivity($user): void
    {
        try {
            $seenKey = 'staff_seen_' . $user->id;

            if (! Cache::has($seenKey)) {
                DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
                Cache::put($seenKey, true, now()->addMinute());
            }
        } catch (\Throwable $e) {
            // عمودٌ لم يُهاجَر بعدُ أو قاعدةٌ لا تردّ: الأثرُ يسقط والطلبُ يمضي
            Log::warning('activity stamp failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
