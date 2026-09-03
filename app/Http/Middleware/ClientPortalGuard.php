<?php

namespace App\Http\Middleware;

use App\Services\ClientPortal\ClientAuthenticator;
use App\Support\ClientPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حارس بوابة العملاء.
 *
 * يمنع دخول صفحات البوابة على من ليس عميلاً مسجَّل الدخول، ويغلقها
 * كلياً إن عطّلها المكتب — لا بإخفاء الروابط بل برفض الطلب نفسه.
 */
class ClientPortalGuard
{
    public function __construct(private ClientAuthenticator $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // ═══ يُنسى مفتاحُ البوابة، ولا تُفرَغ الجلسة ═══
        //
        // logout() ينادي session()->invalidate() وهو flush كامل —
        // يمحو login_web_* أيضاً. ومسارُ البوابة GET بلا مصادقةٍ ولا
        // رمز، وSameSite=lax يرسل الكوكي في التنقّل العلويّ: فرابطٌ
        // في بريدٍ إلى /client-access/home كان يُخرج المحاميَ من
        // حسابه ويضعه أمام شاشةِ دخولٍ — وهي بعينُها الحيلةُ التي
        // أُغلقت في POST /logout.
        if (!ClientPortal::enabled()) {
            $this->auth->forget($request);

            return redirect()->route('client.access')->with('portal_error', __('portal.login.disabled'));
        }

        if (!$this->auth->current($request)) {
            $this->auth->forget($request);

            return redirect()->route('client.access')->with('portal_error', __('portal.login.session_expired'));
        }

        return $next($request);
    }
}
