<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('office_case_number')->nullable()->after('case_number');
            $table->enum('case_type', ['مدني', 'تجاري', 'عمالي', 'أحوال شخصية', 'استثمار', 'تنفيذ'])->default('مدني')->after('office_case_number');
            $table->string('opponent_phone')->nullable()->after('opponent');
            $table->text('opponent_address')->nullable()->after('opponent_phone');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['office_case_number', 'case_type', 'opponent_phone', 'opponent_address']);
        });
    }
};
