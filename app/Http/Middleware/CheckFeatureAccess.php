<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $disabled = Setting::get('feature_' . $feature, '0');

        if ($disabled === '1' && !in_array(auth()->user()?->role, ['developer'])) {
            return redirect()->route('dashboard')
                ->with('error', 'هذه الصفحة قيد التطوير، يرجى التواصل مع المطور أو الانتظار لحين الانتهاء من أعمال الصيانة');
        }

        return $next($request);
    }
}
