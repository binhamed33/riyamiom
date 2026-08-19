<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return $next($request);
        }

        if ($request->routeIs('subscription.expired')) {
            return $next($request);
        }

        $user = auth()->user();

        if (app(SubscriptionService::class)->isAllowed($user)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'انتهت صلاحية اشتراك النظام، يرجى التواصل مع الإدارة.',
            ], 403);
        }

        return redirect()->route('subscription.expired');
    }
}