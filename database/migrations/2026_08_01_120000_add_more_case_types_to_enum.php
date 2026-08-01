<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cases MODIFY COLUMN case_type ENUM('مدني','تجاري','عمالي','أحوال شخصية','جزائي','تنفيذ مدني','تنفيذ جزائي','قضاء مستعجل','أوامر على العرائض','إفلاس وإعادة هيكلة','إيجارات','مرور','أحداث','اداري','استثمار','استشكال','تظلمات') DEFAULT 'مدني'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cases MODIFY COLUMN case_type ENUM('مدني','تجاري','عمالي','أحوال شخصية','جزائي','تنفيذ مدني','تنفيذ جزائي','قضاء مستعجل','أوامر على العرائض','إفلاس وإعادة هيكلة','إيجارات','مرور','أحداث') DEFAULT 'مدني'");
    }
};
