<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_sessions', function (Blueprint $table) {
            $table->string('location')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('court_sessions', function (Blueprint $table) {
            $table->string('location')->nullable(false)->change();
        });
    }
};
