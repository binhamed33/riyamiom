<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الخط الزمني للقضية: ربط سجلات التدقيق بالقضية مباشرة.
 * عمود nullable فقط — السجلات القديمة تبقى كما هي وتُعرض بطرق الربط القديمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('case_id')->nullable()->after('model_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('case_id');
        });
    }
};
