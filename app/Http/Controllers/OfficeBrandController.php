<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\OfficeBrand;
use App\Traits\AuditLoggable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * هوية المكتب: رفع شعار المكتب وتغييره وحذفه، وعرضه.
 * الشعار ملك المكتب وحده — يُخزَّن في تخزين نسخته ويُقرأ من إعداداتها فقط.
 */
class OfficeBrandController extends Controller
{
    use AuditLoggable;

    /** عرض الشعار — عام لأنه يظهر في شاشة الدخول قبل المصادقة */
    public function show(): Response
    {
        $path = OfficeBrand::storedPath();
        abort_if($path === null, 404);

        $disk = Storage::disk('local');
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return response($disk->get($path), 200, [
            'Content-Type' => OfficeBrand::mimeFor($ext),
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="office-logo.' . $ext . '"',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => [
                'required', 'file',
                'mimes:' . implode(',', OfficeBrand::ALLOWED),
                'max:' . OfficeBrand::MAX_KB,
            ],
        ], [
            'logo.required' => 'اختر ملف الشعار أولاً',
            'logo.mimes' => 'الصيغ المسموحة: PNG، JPG، WEBP، SVG',
            'logo.max' => 'حجم الشعار يجب ألا يتجاوز ١ ميجابايت',
        ]);

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, OfficeBrand::ALLOWED, true)) {
            return back()->withErrors(['logo' => 'صيغة غير مدعومة.']);
        }

        $disk = Storage::disk('local');

        // احذف أي شعار سابق (بأي صيغة) حتى لا تتراكم الملفات
        foreach (OfficeBrand::ALLOWED as $old) {
            $disk->delete(OfficeBrand::DIR . '/logo.' . $old);
        }

        $path = OfficeBrand::DIR . '/logo.' . $ext;
        $disk->put($path, file_get_contents($file->getRealPath()));

        Setting::set(OfficeBrand::KEY_PATH, $path, 'general');
        Setting::set(OfficeBrand::KEY_VERSION, (string) now()->timestamp, 'general');

        $this->logAudit(AuditLog::ACTION_UPDATE, Setting::class, null, null, ['office_logo' => $path]);

        return back()->with('success', 'تم تحديث شعار المكتب.');
    }

    public function destroy(): RedirectResponse
    {
        $disk = Storage::disk('local');
        foreach (OfficeBrand::ALLOWED as $ext) {
            $disk->delete(OfficeBrand::DIR . '/logo.' . $ext);
        }

        Setting::set(OfficeBrand::KEY_PATH, '', 'general');
        Setting::set(OfficeBrand::KEY_VERSION, (string) now()->timestamp, 'general');

        $this->logAudit(AuditLog::ACTION_DELETE, Setting::class, null, ['office_logo' => 'removed'], null);

        return back()->with('success', 'حُذف شعار المكتب — عاد النظام لهوية مُداوَلة الافتراضية.');
    }
}
