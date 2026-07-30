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
        Schema::table('cases', function (Blueprint $table) {
            $table->text('opponent_lawyer')->nullable()->change();
            $table->text('opponent_civil_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('opponent_lawyer')->nullable()->change();
            $table->string('opponent_civil_number')->nullable()->change();
        });
    }
};
