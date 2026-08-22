<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * لغة العرض.
 *
 * الترتيب مقصود: اختيار المستخدم المحفوظ أولاً، ثم الجلسة، ثم لغة
 * النظام. كان الاختيار في الجلسة وحدها فيضيع بالخروج أو على جهاز آخر،
 * فيعود الموظّف إلى لغة لم يخترها.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = auth()->user()?->locale
            ?: session('locale', config('app.locale'));

        if (!in_array($locale, ['ar', 'en'], true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }
}
