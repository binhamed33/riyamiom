<?php

use App\Services\WhatsApp\SendingGuard;
use App\Support\WhatsAppSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * تثبيتُ حدود الإيقاع على القيم المعتمَدة.
 *
 * ═══ لماذا هجرةٌ لا افتراضٌ في الكود ═══
 *
 * لأنّ الافتراضَ لا يُقرأ متى كانت في الجدول قيمةٌ مخزَّنة — وهي
 * مخزَّنةٌ على المكاتب التي حُفظت إعداداتُها مرّة. فرفعُ السقف في
 * الكود وحده يترك تلك المكاتب على القديم، وهي مقفلةٌ الآن فلا يبدّلها
 * مديرُها.
 *
 * فتُكتب القيمُ صراحةً مرّةً واحدة. والمطوّرُ يبدّلها بعدها من الشاشة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        try {
            foreach ([
                SendingGuard::KEY_PER_DAY => '100',
                SendingGuard::KEY_PER_HOUR => '15',
                SendingGuard::KEY_MIN_GAP => '15',
                SendingGuard::KEY_QUIET_FROM => '21',
                SendingGuard::KEY_QUIET_TO => '8',
            ] as $key => $value) {
                \App\Models\Setting::set($key, $value, WhatsAppSettings::GROUP);
            }
        } catch (\Throwable) {
            // جدولُ إعداداتٍ مختلف: تُقرأ الافتراضاتُ من الكود
        }
    }

    public function down(): void
    {
        // لا رجوع: قيمُ تشغيلٍ يضبطها المطوّر من الشاشة
    }
};
