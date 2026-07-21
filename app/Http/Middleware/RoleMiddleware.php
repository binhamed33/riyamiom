<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->is_active) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'تم تعطيل حسابك');
        }

        if (auth()->user()->isDeveloper()) {
            return $next($request);
        }

        if (!in_array(auth()->user()->role, $roles)) {
            abort(403, 'غير مصرح لك بالوصول');
        }

        return $next($request);
    }
}
