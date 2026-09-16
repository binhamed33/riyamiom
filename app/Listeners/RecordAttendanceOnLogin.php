<?php

namespace App\Listeners;

use App\Support\AttendanceGuard;
use Illuminate\Auth\Events\Login;

/**
 * حضورُ اليوم عند كلّ دخولٍ — بالنموذج أو بكعكة «تذكّرني».
 *
 * ═══ لماذا حدثُ الدخول لا المتحكّم ═══
 *
 * كان الحضورُ يُسجَّل في LoginController وحدَه، ودخولُ من أشّر «تذكّرني»
 * لا يمرّ عليه: يفتح المتصفّحَ صباحاً فيجد نفسَه داخلاً — بلا سجلّ
 * حضور، حتى ينتبه ويضغط الزرَّ العاشرةَ فيُحسب يومُه من العاشرة.
 * وحدثُ Login يطلقه لارافل في الحالين، فهو المكانُ الواحد.
 *
 * والإشعارُ يوضع في الجلسة هنا ليُعرض في أوّل صفحة: «تم تسجيل حضورك»
 * أو «استُؤنف دوامك» بوقته.
 */
class RecordAttendanceOnLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! AttendanceGuard::tracks($event->user)) {
            return;
        }

        // كعكةُ «تذكّرني» تُدخل صاحبَها حين تقرع نبضةُ مزامنةٍ من تبويبٍ نائم
        // الخادمَ بعد انتهاء الجلسة — ولا إنسانَ هناك. لا يُفتح يومٌ ولا
        // يُستأنف من نبضة؛ يُؤجَّل إلى أوّل طلبٍ بشريّ (TrackUserActivity).
        try {
            $request = request();

            // في الطرفيّة لا مسارَ للطلب فلا يُعدّ آلة — والأمرُ المجدول يمضي كما هو
            if ($request && $request->route() && \App\Http\Middleware\TrackUserActivity::isMachine($request)) {
                session()->put('attendance_login_pending', true);

                return;
            }
        } catch (\Throwable) {
            // بلا طلبٍ: يُكمَل
        }

        // AttendanceGuard يبتلع عطلَه ويُرجع null — الدخولُ لا يتوقّف على الحضور
        $result = AttendanceGuard::checkInOnLogin($event->user);

        // يومٌ مقفَلٌ ولم يُستأنف: لا يُقال «أنت مسجّل حضور اليوم» فوق «يومك مكتمل»
        // — وسؤالُ الاستئناف (إن كان الانصرافُ قريباً) يظهر من الإشعار نفسِه
        if (! $result || (! $result['created'] && ! $result['resumed'] && $result['record']->check_out_at !== null)) {
            return;
        }

        try {
            // لارافل يكتشف هذا المستمعَ تلقائيّاً وقد يُسجَّل مرّةً أخرى يدويّاً،
            // فيعمل مرّتين في الدخول الواحد: الأولى تستأنف اليومَ والثانية تجد
            // السجلَّ مفتوحاً فتكتب «مسجَّلٌ حضور» فوق «استُؤنف». الأقوى يبقى.
            $current = session('attendance_flash');
            if (! $result['created'] && ! $result['resumed'] && is_array($current) && (($current['created'] ?? false) || ($current['resumed'] ?? false))) {
                return;
            }

            session()->put('attendance_flash', [
                'created' => $result['created'],
                'resumed' => $result['resumed'] ?? false,
                'at' => $result['record']->intervalStart()->timezone('Asia/Muscat')->format('h:i A'),
            ]);
        } catch (\Throwable) {
            // بلا جلسةٍ (طرفيّة/اختبار) لا إشعارَ — والسجلُّ كُتب
        }
    }
}
