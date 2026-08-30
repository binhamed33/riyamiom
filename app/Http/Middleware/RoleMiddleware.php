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

        // The developer is the vendor-level account and passes every check.
        if ($user->isDeveloper()) {
            return $next($request);
        }

        // Admin used to pass unconditionally too, which handed the customer's own
        // administrator the developer panel — subscription activation, migrations,
        // feature toggles — even though those routes ask for 'developer' alone.
        // Admin now has to be named by the route, which every admin-facing route
        // already does.
        $roles = [];
        $permission = null;

        foreach ($params as $p) {
            if (str_starts_with($p, 'permission:')) {
                $permission = substr($p, 11);
            } else {
                $roles[] = $p;
            }
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
