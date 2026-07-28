<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('company_name')->nullable()->change();
            $table->text('phone')->nullable()->change();
            $table->text('email')->nullable()->change();
            $table->text('national_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('company_name', 255)->nullable()->change();
            $table->string('phone', 255)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
            $table->string('national_id', 255)->nullable()->change();
        });
    }
};
