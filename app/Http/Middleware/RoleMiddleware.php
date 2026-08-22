<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$params): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->is_active) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'تم تعطيل حسابك');
        }

        $user = auth()->user();

        $roles = [];
        $permission = null;

        foreach ($params as $p) {
            if (str_starts_with($p, 'permission:')) {
                $permission = substr($p, 11);
            } else {
                $roles[] = $p;
            }
        }

        // المطوّر يمرّ دائماً — هو مالك المنصة لا مستخدماً في المكتب
        if ($user->isDeveloper()) {
            return $next($request);
        }

        // مدير المكتب يمرّ على المسارات العامة، لكن ليس على مسار يستثنيه
        // صراحةً (مثل مسارات المطوّر وحده). كان يمرّ عليها جميعاً فيصل
        // إلى لوحة المطوّر وإعدادات الاشتراك وأدوات الصيانة.
        if ($user->isAdmin() && ($roles === [] || in_array('admin', $roles, true))) {
            return $next($request);
        }

        if (!empty($roles) && in_array($user->role, $roles)) {
            return $next($request);
        }

        if ($permission && $user->hasPermission($permission)) {
            return $next($request);
        }

        abort(403, 'غير مصرح لك بالوصول');
    }
}
