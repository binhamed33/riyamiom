<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حدث القضية يبقى وإن لم يكن له صاحب.
 *
 * أمران في العمود user_id، كلاهما ظهر عند تسجيل أحداث المسار الزمني
 * تلقائياً:
 *
 * ١) NOT NULL — والحدث التلقائي لا صاحب له: جلسة تُنشئها الأتمتة أو
 *    أمرٌ مجدول أو استيراد. ومحرّك الأتمتة يرمي استثناءً صراحةً حين لا
 *    يجد مستخدماً، فيسقط الحدث كله. واختراع مستخدم يكذب في السجل.
 *
 * ٢) cascadeOnDelete — وهذه أخطر: حذف موظّف يمحو كل ما سجّله في مسارات
 *    القضايا. سجلٌّ تاريخي يُمحى لأن صاحبه غادر المكتب. الصواب أن يبقى
 *    الحدث ويصير بلا صاحب.
 *
 * لا يُحذف صفّ ولا يُغيَّر محتواه: القيد وحده هو ما يُبدَّل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('case_activities')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            // sqlite لا يُعدّل القيود في مكانها؛ والاختبارات تبني الجدول
            // من الهجرات فيكفيها تعديل الأصل أدناه عبر الأعمدة.
            Schema::table('case_activities', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });

            return;
        }

        Schema::table('case_activities', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('case_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('case_activities', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // لا رجوع: تضييق العمود إلى NOT NULL يتطلّب حذف الأحداث التي
        // لا صاحب لها — وهي بيانات حقيقية. الرجوع هنا خسارة لا تراجع.
    }
};
