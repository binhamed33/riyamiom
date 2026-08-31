<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * السجلّ يعرف فاعله الحقيقيّ: إنسانٌ أم نظام مُداوَلة.
 *
 * الأتمتة كانت تنسب ما تفعله إلى صاحب القاعدة، فتظهر مهمّةٌ أنشأتها
 * قاعدةٌ ليلاً باسم المدير — واشتكى مستخدمٌ يرى اسمه على ما لم يفعله.
 *
 * ولا يصلح جعلُ created_by فارغاً علامةً على النظام: العمود يُفرَّغ
 * أيضاً عند حذف مستخدمٍ قديم (nullOnDelete)، فيختلط الموظّفُ المحذوف
 * بالنظام. فالعمود الجديد يقول الصفة صراحةً، ويبقى created_by حافظاً
 * للمساءلة: قاعدةُ مَن فعلت هذا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('created_via', 20)->default('user');
        });

        Schema::table('case_activities', function (Blueprint $table) {
            $table->string('created_via', 20)->default('user');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $t) => $t->dropColumn('created_via'));
        Schema::table('case_activities', fn (Blueprint $t) => $t->dropColumn('created_via'));
    }
};
