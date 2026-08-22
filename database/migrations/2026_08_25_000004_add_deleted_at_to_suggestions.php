<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف ناعم للاقتراحات في المكتب.
 *
 * كان الحذف يمحو السجل من القاعدة نهائياً، وبلا رجعة. الآن يختفي من
 * القائمة ويبقى محفوظاً — فضغطة خطأ لا تُتلف ما كتبه موظّف.
 *
 * إضافة عمود اختياري فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('suggestions', 'deleted_at')) {
            return;
        }

        Schema::table('suggestions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // لا نُسقط العمود: تراجع لا يفقد بيانات
    }
};
