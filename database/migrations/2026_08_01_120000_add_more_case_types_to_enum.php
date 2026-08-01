<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $allowed = ['مدني', 'تجاري', 'عمالي', 'أحوال شخصية', 'جزائي', 'تنفيذ مدني', 'تنفيذ جزائي', 'قضاء مستعجل', 'أوامر على العرائض', 'إفلاس وإعادة هيكلة', 'إيجارات', 'مرور', 'أحداث', 'اداري', 'استثمار', 'استشكال', 'تظلمات'];

        $existing = DB::table('cases')->distinct()->pluck('case_type')->filter()->all();

        $values = collect($allowed)
            ->merge($existing)
            ->unique()
            ->map(fn ($v) => DB::getPdo()->quote($v))
            ->implode(', ');

        DB::statement("ALTER TABLE cases MODIFY COLUMN case_type ENUM($values) DEFAULT 'مدني'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cases MODIFY COLUMN case_type ENUM('مدني','تجاري','عمالي','أحوال شخصية','جزائي','تنفيذ مدني','تنفيذ جزائي','قضاء مستعجل','أوامر على العرائض','إفلاس وإعادة هيكلة','إيجارات','مرور','أحداث') DEFAULT 'مدني'");
    }
};
