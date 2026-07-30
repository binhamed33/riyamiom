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
        DB::statement("ALTER TABLE cases MODIFY COLUMN case_type ENUM('مدني','تجاري','عمالي','أحوال شخصية','استثمار','تنفيذ','جزائي') DEFAULT 'مدني'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cases MODIFY COLUMN case_type ENUM('مدني','تجاري','عمالي','أحوال شخصية','استثمار','تنفيذ') DEFAULT 'مدني'");
    }
};
