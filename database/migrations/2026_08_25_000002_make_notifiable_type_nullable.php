<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إشعار النظام لا موضوع له.
 *
 * notifiable_type كان NOT NULL، وإشعارات انتهاء الاشتراك تُنشأ بلا
 * موضوع (ليست عن قضية ولا مهمة) — فكانت تُرمى ولا تُحفظ، ولا يصل
 * مدير المكتب تنبيهٌ بأن اشتراكه ينتهي غداً.
 *
 * غير مدمِّرة: تخفيف قيد فقط. لا صفّ يُمسّ ولا عمود يُحذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notifiable_type')->nullable()->change();
            $table->unsignedBigInteger('notifiable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // لا تراجع: إعادة القيد تكسر إشعارات النظام من جديد
    }
};
