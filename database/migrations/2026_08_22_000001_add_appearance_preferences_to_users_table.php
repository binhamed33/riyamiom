<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تفضيلات المظهر لكل مستخدم.
 *
 * إضافة بحتة: عمودان اختياريان (nullable) بلا قيمة افتراضية إلزامية،
 * ولا تمسّ أي عمود أو صف قائم. المستخدمون الحاليون يبقون على القيمة
 * الفارغة ويقرأها النظام كـ «مُداوَلة / نهاري» تماماً كما هو اليوم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'theme')) {
                $table->string('theme', 20)->nullable()->after('avatar');
            }
            if (!Schema::hasColumn('users', 'appearance')) {
                $table->string('appearance', 10)->nullable()->after('theme');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['theme', 'appearance'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
