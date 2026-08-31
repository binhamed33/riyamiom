<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * متى تُفرَج الرسالةُ المحجوزة بحدود الإيقاع.
 *
 * ═══ العطل الذي وقع ═══
 *
 * كان التأجيلُ يُعاد دفعَ مهمّةٍ جديدةٍ بمهلة. والمهلةُ لم تُحترم في
 * التشغيل الفعلي، فدارت المهمّةُ أربعاً وعشرين مرّةً في دقائق ثمّ
 * أُعلنت «فشل الإرسال» — ورسالةُ الموكّل ضاعت لأنّ الساعة كانت
 * الثالثة فجراً، وكان يكفي أن تنتظر الصباح.
 *
 * فالانتظارُ صار حقيقةً في الصفّ لا وعداً في الطابور: يُكتب موعدُ
 * الإفراج، وتبقى الرسالة «في الانتظار»، ويلتقطها أمرُ الاستدراك
 * المجدوَل حين يحين. ولا دورةَ يمكن أن تدور أصلاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('whatsapp_messages', 'hold_until')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->timestamp('hold_until')->nullable()->after('status')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('whatsapp_messages', 'hold_until')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->dropColumn('hold_until');
            });
        }
    }
};
