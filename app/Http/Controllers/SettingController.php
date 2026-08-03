<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $settings = [];
        foreach (Setting::all() as $setting) {
            $settings[$setting->key] = $setting->value;
        }

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'office_name'        => 'nullable|string|max:255',
            'office_email'       => 'nullable|email|max:255',
            'office_phone'       => 'nullable|string|max:255',
            'office_address'     => 'nullable|string|max:255',
            'email_notifications'=> 'nullable',
            'task_reminders'     => 'nullable',
            'deadline_alerts'    => 'nullable',
            'date_format'        => 'nullable|string',
            'items_per_page'     => 'nullable|integer|min:5|max:100',
        ]);

        $groupMap = [
            'office_name'        => 'general',
            'office_email'       => 'general',
            'office_phone'       => 'general',
            'office_address'     => 'general',
            'email_notifications'=> 'notifications',
            'task_reminders'     => 'notifications',
            'deadline_alerts'    => 'notifications',
            'date_format'        => 'system',
            'items_per_page'     => 'system',
        ];

        foreach ($validated as $key => $value) {
            if (in_array($key, ['email_notifications', 'task_reminders', 'deadline_alerts'])) {
                $value = $request->has($key) ? '1' : '0';
            }
            Setting::set($key, $value, $groupMap[$key] ?? 'general');
        }

        return redirect()->route('settings.index')
            ->with('success', 'تم حفظ الإعدادات بنجاح');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'office_logo' => 'required|image|mimes:jpeg,jpg,png,svg|max:5120',
        ]);

        $file = $validated['office_logo'];
        $ext = strtolower($file->getClientOriginalExtension());

        $imgDir = public_path('img');
        if (!is_dir($imgDir)) {
            mkdir($imgDir, 0755, true);
        }

        foreach (glob($imgDir . '/office-logo.*') as $old) {
            @unlink($old);
        }

        $file->move($imgDir, 'office-logo.' . $ext);
        @chmod($imgDir . '/office-logo.' . $ext, 0644);

        return redirect()->route('settings.index')
            ->with('success', 'تم رفع شعار المكتب بنجاح');
    }
}
