<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cases MODIFY COLUMN case_type ENUM('مدني','تجاري','عمالي','أحوال شخصية','جزائي','تنفيذ مدني','تنفيذ جزائي','قضاء مستعجل','أوامر على العرائض','إفلاس وإعادة هيكلة','إيجارات','مرور','أحداث') DEFAULT 'مدني'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cases MODIFY COLUMN case_type ENUM('مدني','تجاري','عمالي','أحوال شخصية','جزائي','تنفيذ مدني','تنفيذ جزائي','قضاء مستعجل','أوامر على العرائض','إفلاس وإعادة هيكلة','إيجارات','مرور','أحداث') DEFAULT 'مدني'");
        }
    }
};
