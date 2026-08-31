<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الجلسةُ التي خرج التذكيرُ من أجلها.
 *
 * ═══ العطل الذي يمنعه هذا العمود ═══
 *
 * كان حارسُ تكرار التذكير زمنيّاً: «هل خرج من هذا الخيط تذكيرٌ بهذا
 * القالب خلال المدّة الفلانية؟». وهو يمنع التكرار فعلاً — لكنّه يمنع
 * معه ما ليس تكراراً: موكّلٌ له جلستان في يومٍ واحد بساعتين بينهما
 * ‏(وهذا يقع كثيراً: قضيّتان أمام نفس المحكمة في نفس الجلسة الصباحية)
 * يُذكَّر بالأولى، وتُبتلع الثانية بوصفها «تكراراً» فلا يعلم بها.
 *
 * وفواتُ جلسةٍ ليس إزعاجاً يُحتمل: غيابُ الموكّل قد يُسقط دفعاً أو
 * يُصدر حكماً غيابيّاً. فصار الحارسُ مربوطاً بالجلسة نفسها: تذكيرُ
 * الجلسة رقم كذا خرج أو لم يخرج — لا لبس.
 *
 * ويبقى نُلّاً للرسائل التي ليست تذكيرَ جلسة (وهي أكثرُها)، ويُفرَّغ
 * إن حُذفت الجلسة فلا يمنع حذفُ سجلٍّ قديمٍ رسالةً من البقاء.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_messages') && !Schema::hasColumn('whatsapp_messages', 'session_id')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->foreignId('session_id')
                    ->nullable()
                    ->after('template_name')
                    ->constrained('court_sessions')
                    ->nullOnDelete();

                // الحارسُ يسأل: «هذا الخيط، هذه الجلسة» — فهرسٌ على
                // الاثنين معاً، لأنّ السؤال يتكرّر لكلّ جلسةٍ في كلّ
                // تشغيلٍ ساعيّ ولا يجوز أن يمسح الجدول كلَّه
                $table->index(['conversation_id', 'session_id'], 'wa_msgs_conv_session_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('whatsapp_messages', 'session_id')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->dropIndex('wa_msgs_conv_session_idx');
                $table->dropConstrainedForeignId('session_id');
            });
        }
    }
};
