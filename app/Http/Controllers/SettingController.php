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

            // بوابة العملاء
            'client_portal_welcome' => 'nullable|string|max:300',

            // الموارد البشرية والصيانة: كان الأول يُقرأ في AttendanceGuard
            // والثاني في صفحة الصيانة، ولا واجهةَ تكتب أياً منهما — فبقي
            // الحضور التلقائي مفروضاً بلا مفتاح إطفاء، وملاحظةُ الصيانة
            // لا تظهر أبداً.
            'hr_auto_checkin'    => 'nullable',
            'hr_auto_close'      => 'nullable',
            // رقمٌ لا خانة: غيابُه «لم يُرسَل» لا «صفر». والحلقةُ
            // العامّة أدناه تكتبه متى جاء، فلا يُصفّره حفظٌ من نموذجٍ
            // لا يعرضه — وسقفُ صفرٍ يُقفل كلَّ سجلٍّ لحظةَ فتحه
            'hr_shift_cap_hours' => 'nullable|integer|min:1|max:24',
            'maintenance_note'   => 'nullable|string|max:300',
        ]);

        // مفاتيح البوابة الثنائية: خانة غير مؤشَّرة تعني «لا»، والغياب
        // الكامل للنموذج لا يُغيّر شيئاً (حماية من حفظ جزئي).
        $portalFlags = [
            \App\Support\ClientPortal::KEY_ENABLED,
            \App\Support\ClientPortal::KEY_SHOW_SESSIONS,
            \App\Support\ClientPortal::KEY_SHOW_TIMELINE,
            \App\Support\ClientPortal::KEY_SHOW_DOCUMENTS,
            \App\Support\ClientPortal::KEY_SHOW_OPPONENT,
            \App\Support\ClientPortal::KEY_SHOW_LAWYER,
            \App\Support\ClientPortal::KEY_SHOW_ACCOUNTING,
        ];

        if ($request->has('client_portal_section')) {
            foreach ($portalFlags as $flag) {
                Setting::set($flag, $request->boolean($flag) ? '1' : '0', 'client_portal');
            }
        }

        // الحضور التلقائي خانةٌ ثنائية مثلها: غيابُ التأشير «لا» لا «لم
        // يُرسَل». وبلا هذا يبقى مفروضاً على المكتب بلا مفتاح إطفاء.
        if ($request->has('hr_section')) {
            Setting::set('hr_auto_checkin', $request->boolean('hr_auto_checkin') ? '1' : '0', 'hr');
            Setting::set('hr_auto_close', $request->boolean('hr_auto_close') ? '1' : '0', 'hr');
        }

        // أنواع البريد: يقرّر المكتب ما يصل موكّليه. تُقرأ من التعداد
        // نفسه، فإضافة نوعٍ جديدٍ هناك تظهر هنا بلا تعديل.
        if ($request->has('mail_kinds_section')) {
            foreach (\App\Mail\MailKind::all() as $kind) {
                Setting::set(
                    $kind->settingKey(),
                    $request->boolean($kind->settingKey()) ? '1' : '0',
                    'notifications',
                );
            }
        }

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
            'client_portal_welcome' => 'client_portal',
            'hr_auto_checkin'    => 'hr',
            'hr_auto_close'      => 'hr',
            'hr_shift_cap_hours' => 'hr',
            'maintenance_note'   => 'system',
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
