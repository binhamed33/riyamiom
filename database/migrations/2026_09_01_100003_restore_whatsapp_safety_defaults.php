<?php

use App\Services\WhatsApp\SendingGuard;
use App\Support\WhatsAppSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * إعادةُ حدود الأمان إلى وضعها الصحيح على المكاتب القائمة.
 *
 * ═══ العطل الذي تصلحه ═══
 *
 * حين أُضيفت الحدودُ لم تكن مقفلة، فحفظةُ إعداداتٍ واحدةٌ من مدير
 * المكتب كتبت `wa_guard_enabled = 0` — لأنّ الخانة لم تُرسَل في ذلك
 * النموذج. ثمّ قُفلت الإعدادات على المطوّر، فصار الرقمُ يعمل **بلا
 * حدود** ولا أحدَ في المكتب يستطيع إعادتها.
 *
 * أسوأُ حالتين مجتمعتين: حمايةٌ مطفأة وبابٌ مقفلٌ دون إعادتها.
 *
 * ═══ ما تفعله ═══
 *
 * تُثبّت القيمَ الآمنة مرّةً: الحدودُ مفعَّلة، والمراسلةُ للموكّلين
 * وحدهم، وصندوقُ الوارد مخفيّ. والمطوّرُ يبدّلها بعدها متى شاء —
 * فهذه أرضيّةٌ لا سقف.
 *
 * ولا تُلمس بياناتُ مكتبٍ ولا رسالةٌ ولا محادثة: ثلاثةُ صفوفٍ في
 * جدول الإعدادات.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        try {
            \App\Models\Setting::set(SendingGuard::KEY_ENABLED, '1', WhatsAppSettings::GROUP);
            \App\Models\Setting::set(SendingGuard::KEY_CLIENTS_ONLY, '1', WhatsAppSettings::GROUP);
            \App\Models\Setting::set(WhatsAppSettings::KEY_INBOX_VISIBLE, '0', WhatsAppSettings::GROUP);
        } catch (\Throwable) {
            // مكتبٌ بجدول إعداداتٍ مختلف: تُقرأ الافتراضاتُ من الكود،
            // وهي نفسُها — فلا تُسقط الهجرة
        }
    }

    public function down(): void
    {
        // لا رجوع: إعادةُ حمايةٍ إلى وضعها الصحيح ليست تغييراً يُنقض
    }
};
