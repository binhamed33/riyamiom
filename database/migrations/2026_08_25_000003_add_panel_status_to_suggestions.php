<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حالة الاقتراح كما قرّرها فريق التطوير في اللوحة.
 *
 * جدول المكتب يعرف حالتين فقط، فيظهر «مرفوض» و«مخطَّط له» كلاهما
 * «قيد الدراسة» — وهذا يُضلّل الموظّف. العمود الجديد يحمل الحالة
 * الدقيقة، ويبقى العمود القديم كما هو لا يتغيّر سلوكه.
 *
 * إضافة عمود اختياري فقط: لا حذف ولا تعديل لعمود قائم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('suggestions', 'panel_status')) {
            return;
        }

        Schema::table('suggestions', function (Blueprint $table) {
            $table->string('panel_status', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        // لا نُسقط العمود: تراجع لا يفقد بيانات
    }
};
