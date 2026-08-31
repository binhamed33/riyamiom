<?php

namespace App\Http\Middleware;

use App\Support\WhatsAppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * صندوقُ الوارد لا يُفتح إلا إن أظهره المكتب.
 *
 * ═══ لماذا حارسٌ لا إخفاءُ رابط ═══
 *
 * لأنّ إخفاءَ الرابط لا يمنع من يعرف العنوان — ولا من حفظه متصفّحُه،
 * ولا من فتح صفحةً قديمة. والمقصودُ هنا منعُ الإرسال اليدوي الحرّ،
 * وهو أخطرُ ما على سلامة الرقم. فالمنعُ عند الباب لا في القائمة.
 *
 * والمطوّرُ يمرّ: هو من يشخّص العطل، وحجبُه عنه يعميه عن نظامٍ
 * مسؤولٌ عنه.
 */
class WhatsAppInboxGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (WhatsAppSettings::inboxVisible() || auth()->user()?->isDeveloper()) {
            return $next($request);
        }

        return redirect()->route('dashboard')
            ->with('error', 'صندوق وارد واتساب مخفيٌّ في هذا المكتب. الإشعارات الآلية تعمل كما هي.');
    }
}
