<?php

namespace App\Http\Middleware;

use App\Support\OfficeEngines;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بابُ ميزةٍ يفتحه مديرُ المكتب من الإعدادات — لا من الميزة نفسِها.
 *
 * ═══ لماذا حارسٌ لا إخفاءُ رابط ═══
 *
 * الرابطُ المخفيُّ ليس باباً مغلقاً: من حفظ العنوان يصل، ومن نسخه
 * لزميلٍ فتح له ما أغلقه المدير. فالإغلاقُ هنا، والإخفاءُ في الشريط
 * الجانبيّ تهذيبٌ فوقه لا بديلٌ عنه.
 *
 * والمطوّرُ يمرّ: هو من يُصلح المكتبَ حين يُقفل صاحبُه بابَه على
 * نفسه.
 */
class EnsureEngineOn
{
    public function handle(Request $request, Closure $next, string $engine): Response
    {
        $on = match ($engine) {
            'templates' => OfficeEngines::templatesOn(),
            'automations' => OfficeEngines::automationOn(),
            default => true,
        };

        if (!$on && auth()->user()?->role !== 'developer') {
            return redirect()->route('dashboard')
                ->with('error', 'هذه الميزة مغلقة في مكتبك — يفتحها مدير المكتب من الإعدادات ← الأتمتة والقوالب.');
        }

        return $next($request);
    }
}
