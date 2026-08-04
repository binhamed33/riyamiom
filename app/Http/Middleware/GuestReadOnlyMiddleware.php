<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestReadOnlyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->isGuest() && !$request->isMethod('GET')) {
            abort(403, 'حساب الضيف للتصفح فقط — لا يمكن إجراء التعديلات');
        }

        return $next($request);
    }
}
