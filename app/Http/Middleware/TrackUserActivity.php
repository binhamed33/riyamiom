<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'client') {
            $key = 'staff_active_' . $user->id;
            $last = Cache::get($key);

            if (!$last || $last->lt(now()->subMinute())) {
                Cache::put($key, now(), now()->addMinutes(8));
            }
        }

        return $next($request);
    }
}