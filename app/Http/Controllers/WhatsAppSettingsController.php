<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\WhatsAppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ربطُ رقم واتساب الخاص بهذا المكتب.
 *
 * المسارات خلف صلاحية إدارة الإعدادات — لا يصلها محامٍ ولا موظّف.
 * والرمزُ لا يعود إلى الواجهة في أيّ استجابة ولا يُكتب في أيّ سجلّ:
 * يُسجَّل أنّه «تغيّر» لا ما تغيّر إليه.
 */
class WhatsAppSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // الرمز الدائم من Meta طويل؛ الحدُّ الأدنى يمنع لصقَ قصاصةٍ ناقصة
            'wa_access_token' => ['nullable', 'string', 'min:40', 'max:600'],
            'wa_app_secret' => ['nullable', 'string', 'min:16', 'max:200'],
            'wa_phone_number_id' => ['nullable', 'string', 'max:40', 'regex:/^\d*$/'],
            'wa_business_account_id' => ['nullable', 'string', 'max:40', 'regex:/^\d*$/'],
            'wa_notify_sessions' => ['nullable'],
            'wa_notify_invoices' => ['nullable'],
            'wa_notify_case_updates' => ['nullable'],
            'wa_ai_reply' => ['nullable'],
            'wa_session_template' => ['nullable', 'string', 'max:120'],
            'wa_invoice_template' => ['nullable', 'string', 'max:120'],
            'wa_reminder_hours' => ['nullable', 'integer', 'min:1', 'max:72'],
        ], [
            'wa_access_token.min' => 'الرمز المُدخل قصير جداً — تأكد من نسخه كاملاً.',
            'wa_phone_number_id.regex' => 'معرّف الرقم أرقامٌ فقط كما يظهر في لوحة Meta.',
            'wa_business_account_id.regex' => 'معرّف حساب الأعمال أرقامٌ فقط كما يظهر في لوحة Meta.',
        ]);

        $tokenChanged = filled($validated['wa_access_token'] ?? null);

        WhatsAppSettings::store(
            $validated['wa_access_token'] ?? null,
            $validated['wa_phone_number_id'] ?? null,
            $validated['wa_business_account_id'] ?? null,
            $validated['wa_app_secret'] ?? null,
        );

        WhatsAppSettings::setFlag(WhatsAppSettings::KEY_NOTIFY_SESSIONS, $request->boolean('wa_notify_sessions'));
        WhatsAppSettings::setFlag(WhatsAppSettings::KEY_NOTIFY_INVOICES, $request->boolean('wa_notify_invoices'));
        WhatsAppSettings::setFlag(WhatsAppSettings::KEY_NOTIFY_CASE_UPDATES, $request->boolean('wa_notify_case_updates'));
        WhatsAppSettings::setFlag(WhatsAppSettings::KEY_AI_REPLY, $request->boolean('wa_ai_reply'));

        foreach ([
            WhatsAppSettings::KEY_SESSION_TEMPLATE => $validated['wa_session_template'] ?? null,
            WhatsAppSettings::KEY_INVOICE_TEMPLATE => $validated['wa_invoice_template'] ?? null,
            WhatsAppSettings::KEY_REMINDER_HOURS => $validated['wa_reminder_hours'] ?? null,
        ] as $key => $value) {
            if ($value !== null) {
                \App\Models\Setting::set($key, (string) $value, WhatsAppSettings::GROUP);
            }
        }

        $this->audit('whatsapp_settings_updated', ['token_changed' => $tokenChanged]);

        return back()->with('success', $tokenChanged
            ? 'حُفظت إعدادات واتساب وحُدّث الرمز.'
            : 'حُفظت إعدادات واتساب.');
    }

    /**
     * فصلُ الرقم — تُمحى الاعتمادات وتبقى المحادثات.
     *
     * محوُ المراسلات مع ضغطةِ «فصل» فقدانٌ لا رجعة فيه لسجلٍّ قد
     * يُحتاج في نزاع. الفصلُ يوقف الإرسال والاستقبال لا أكثر.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        WhatsAppSettings::disconnect();

        $this->audit('whatsapp_disconnected', []);

        return back()->with('success', 'فُصل رقم واتساب. المحادثات السابقة محفوظة كما هي.');
    }

    /** فحصُ اتصالٍ حقيقي — الاستجابة لا تحتوي الرمز. */
    public function test(): JsonResponse
    {
        $provider = WhatsAppManager::make();

        if (!$provider) {
            return response()->json(['ok' => false, 'message' => 'مزوّد واتساب غير مدعوم في هذا الإصدار.'], 200);
        }

        return response()->json($provider->testConnection(), 200);
    }

    /**
     * مزامنةُ القوالب من Meta.
     *
     * الحالةُ تُنسخ كما هي عندهم: قالبٌ نعرضه «معتمَداً» وهو قيد
     * المراجعة يُرسَل به فيُرفض عند أوّل استعمال بخطأٍ لا يفهمه أحد.
     */
    public function syncTemplates(): RedirectResponse
    {
        $provider = WhatsAppManager::provider();

        if (!$provider) {
            return back()->with('error', 'اربط رقم واتساب أولاً.');
        }

        $templates = $provider->fetchTemplates();

        if ($templates === []) {
            return back()->with('error', 'لم تصل قوالب من Meta — تأكّد من معرّف حساب الأعمال وصلاحيات الرمز.');
        }

        $seen = 0;

        foreach ($templates as $template) {
            $name = (string) ($template['name'] ?? '');
            $language = (string) ($template['language'] ?? 'ar');

            if ($name === '') {
                continue;
            }

            $body = '';
            $variables = [];

            foreach ((array) ($template['components'] ?? []) as $component) {
                if (strtoupper((string) ($component['type'] ?? '')) === 'BODY') {
                    $body = (string) ($component['text'] ?? '');

                    preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $matches);
                    $variables = array_values(array_unique($matches[1] ?? []));
                }
            }

            WhatsAppTemplate::updateOrCreate(
                ['name' => $name, 'language' => $language],
                [
                    'category' => (string) ($template['category'] ?? ''),
                    'status' => strtoupper((string) ($template['status'] ?? 'PENDING')),
                    'body' => $body,
                    'variables' => $variables,
                    'meta_id' => isset($template['id']) ? (string) $template['id'] : null,
                    'synced_at' => now(),
                ]
            );

            $seen++;
        }

        $this->audit('whatsapp_templates_synced', ['count' => $seen]);

        return back()->with('success', 'زُومنت ' . $seen . ' قالباً من Meta.');
    }

    private function audit(string $action, array $data): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => null,
                'model_id' => null,
                'old_values' => null,
                'new_values' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable) {
            // السجلّ خبرٌ عن الحدث لا الحدثُ نفسه
        }
    }
}
