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

        if ($request->routeIs('subscription.expired') || $request->routeIs('maintenance.page')) {
            return $next($request);
        }

        $user = auth()->user();

        if (app(SubscriptionService::class)->isAllowed($user)) {
            return $next($request);
        }

        // الصيانة حالة مستقلة عن انتهاء الاشتراك، ولها صفحتها ورسالتها
        $inMaintenance = app(SubscriptionService::class)->status() === SubscriptionService::STATUS_MAINTENANCE;

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => $inMaintenance
                    ? 'النظام تحت الصيانة حالياً، سنعود قريباً.'
                    : 'انتهت صلاحية اشتراك النظام، يرجى التواصل مع المطور لتفعيل النظام من جديد.',
            ], 503);
        }

        return redirect()->route($inMaintenance ? 'maintenance.page' : 'subscription.expired');
    }
}