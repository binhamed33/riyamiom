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
}
