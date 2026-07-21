<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function ($table) {
            $table->string('model_type')->nullable()->change();
            $table->integer('model_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function ($table) {
            $table->string('model_type')->nullable(false)->change();
            $table->integer('model_id')->nullable(false)->change();
        });
    }
};
