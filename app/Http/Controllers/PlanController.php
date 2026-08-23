<?php

namespace App\Http\Controllers;

use App\Services\PanelReporter;
use App\Support\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * باقة المكتب واستهلاكه، وطلب الترقية.
 *
 * المكتب لا يملك أن يرفع باقته بنفسه — يطلب، فيصل الطلب لوحة مُداوَلة
 * ويُعالَج هناك. هذه الشاشة تقول له أين هو من حدوده قبل أن يصطدم بها.
 */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('plan.index', [
            'report' => PlanLimits::report(),
            'planName' => PlanLimits::planName(),
            'linked' => PanelReporter::configured(),
        ]);
    }

    public function requestUpgrade(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        $sent = PanelReporter::requestUpgrade(
            $user->name,
            $user->email,
            $validated['reason'] ?: 'طلب ترقية من ' . ($user->name ?? 'إدارة المكتب'),
        );

        // لا نقول «وصل» لطلبٍ لم يُرسَل: مكتبٌ غير مربوط بالجسر يُقال
        // له الحقيقة ويُعطى البريد، بدل أن ينتظر رداً لن يأتي.
        return $sent
            ? back()->with('success', 'وصل طلبك إلى فريق مُداوَلة، وسنتواصل معك قريباً.')
            : back()->withErrors([
                'upgrade' => 'تعذّر إرسال الطلب آلياً. راسلنا مباشرة على '
                    . config('mail.from.address', 'binhamed333@gmail.com') . ' وسنرفع باقتك.',
            ]);
    }
}
