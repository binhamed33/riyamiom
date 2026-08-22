<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهرس أعمى للبحث برقم الهوية.
 *
 * رقم الهوية مشفَّر في قاعدة البيانات بتشفير غير حتمي (متجه بدء عشوائي)،
 * فالنص نفسه يُنتج تشفيراً مختلفاً كل مرة — ومن ثمّ يستحيل البحث بمساواة
 * على العمود المشفَّر. ولهذا كانت الشيفرة القديمة تُحمّل كل العملاء إلى
 * الذاكرة وتفكّ تشفيرهم واحداً واحداً في كل محاولة دخول.
 *
 * البديل المعتاد لهذه الحالة: بصمة حتمية مفتاحها مفتاح التطبيق، تُخزَّن
 * في عمود مفهرس. البحث يصير فورياً، والنص الأصلي يبقى مشفَّراً كما هو.
 * ولأن المفتاح يخصّ هذا المكتب وحده، فبصمات مكتب لا تُطابق بصمات آخر.
 *
 * عمود جديد قابل للفراغ — لا يمسّ عميلاً قائماً ولا يغيّر بياناته.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'national_id_hash')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('national_id_hash', 64)->nullable()->index()->after('national_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'national_id_hash')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('national_id_hash');
            });
        }
    }
};
