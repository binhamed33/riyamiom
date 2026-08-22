<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حالة تسليم الاقتراح إلى لوحة مُداوَلة.
 *
 * الاقتراح يُحفظ عند الموظف أولاً ثم يُسلَّم. فإن تعذّر التسليم لا
 * يضيع: يبقى «معلّقاً» ويُعاد إرساله. وبلا هذه الأعمدة كان الإخفاق
 * صامتاً — لا الموظف يعلم ولا المكتب.
 *
 * أعمدة جديدة قابلة للفراغ — لا تمسّ اقتراحاً قائماً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            if (!Schema::hasColumn('suggestions', 'delivery_state')) {
                // pending | sent | failed | skipped (مكتب غير مربوط)
                $table->string('delivery_state', 20)->default('pending')->after('context');
            }
            if (!Schema::hasColumn('suggestions', 'delivery_attempts')) {
                $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('delivery_state');
            }
            if (!Schema::hasColumn('suggestions', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('delivery_attempts');
            }
            if (!Schema::hasColumn('suggestions', 'delivery_error')) {
                $table->string('delivery_error', 300)->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            foreach (['delivery_state', 'delivery_attempts', 'delivered_at', 'delivery_error'] as $column) {
                if (Schema::hasColumn('suggestions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
