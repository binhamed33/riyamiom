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
        if (!ClientPortal::enabled()) {
            $this->auth->logout($request);

            return redirect()->route('client.access')->with('portal_error', __('portal.login.disabled'));
        }

        if (!$this->auth->current($request)) {
            $this->auth->logout($request);

            return redirect()->route('client.access')->with('portal_error', __('portal.login.session_expired'));
        }

        return $next($request);
    }
}
