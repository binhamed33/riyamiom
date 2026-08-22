<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Ai\AiManager;
use App\Support\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * إعدادات الذكاء الاصطناعي لهذا المكتب.
 * المسارات محمية بصلاحية إدارة الإعدادات — لا يصلها محامٍ ولا موظف.
 * المفتاح لا يُعاد إلى الواجهة في أي استجابة، ولا يُكتب في أي سجل.
 */
class AiSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $providers = array_keys(AiSettings::availableProviders());

        $validated = $request->validate([
            'ai_provider' => ['required', 'string', 'in:' . implode(',', $providers)],
            'ai_model' => ['nullable', 'string', 'max:80'],
            'ai_api_key' => ['nullable', 'string', 'min:16', 'max:400'],
        ], [
            'ai_provider.in' => 'المزوّد المحدد غير مدعوم في هذا الإصدار.',
            'ai_api_key.min' => 'المفتاح المُدخل قصير جداً — تأكد من نسخه كاملاً.',
        ]);

        $model = $validated['ai_model'] ?? null;
        $allowed = (array) config('ai.providers.' . $validated['ai_provider'] . '.models', []);
        if ($model !== null && $allowed !== [] && !in_array($model, $allowed, true)) {
            return back()->withErrors(['ai_model' => 'النموذج المحدد غير متاح لهذا المزوّد.']);
        }

        $keyChanged = filled($validated['ai_api_key'] ?? null);

        AiSettings::store($validated['ai_provider'], $validated['ai_api_key'] ?? null, $model);

        // نسجّل واقعة التغيير فقط — لا المفتاح ولا أي جزء منه
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ai_settings_updated',
            'model_type' => null,
            'model_id' => null,
            'old_values' => null,
            'new_values' => [
                'provider' => $validated['ai_provider'],
                'model' => $model,
                'key_changed' => $keyChanged,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', $keyChanged
            ? 'حُفظت إعدادات الذكاء الاصطناعي وحُدّث المفتاح.'
            : 'حُفظت إعدادات الذكاء الاصطناعي.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        AiSettings::forgetKey();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ai_key_removed',
            'model_type' => null,
            'model_id' => null,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'حُذف مفتاح الذكاء الاصطناعي من هذا المكتب.');
    }

    /** فحص اتصال حقيقي بالمزوّد — الاستجابة لا تحتوي المفتاح. */
    public function test(): JsonResponse
    {
        $provider = AiManager::tryProvider();

        if (!$provider) {
            return response()->json(['ok' => false, 'message' => 'مزوّد الذكاء الاصطناعي غير مدعوم.'], 200);
        }

        return response()->json($provider->testConnection(), 200);
    }
}
