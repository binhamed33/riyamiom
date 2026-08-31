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

        // صلاحيةٌ إضافية تفتح الباب — إلا لحساب موكّل.
        //
        // ═══ الثغرة التي يسدّها هذا الشرط ═══
        //
        // كان يكفي أن يحمل الحسابُ الصلاحيةَ ليمرّ، أيّاً كان دورُه.
        // فصفٌّ واحد في user_permissions أُسند خطأً — أو حسابُ موظّفٍ
        // نُقل إلى دور «موكّل» وبقيت صلاحياتُه القديمة — يجعل حسابَ
        // موكّلٍ يدخل شاشات المكتب: القضايا، ومراسلاتِ واتساب مع سائر
        // الموكّلين. ودورُ الموكّل ليس درجةً أدنى في السلّم بل جهةٌ من
        // خارج المكتب، فلا صلاحيةَ إضافية ترفعه إلى داخله.
        if ($permission && !$user->isClient() && $user->hasPermission($permission)) {
            return $next($request);
        }

        abort(403, 'غير مصرح لك بالوصول');
    }
}
