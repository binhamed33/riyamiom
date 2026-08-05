<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending', 'overdue', 'closed', 'won', 'lost', 'adjudicated', 'fees_pending'])->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->enum('status', ['active', 'pending', 'overdue', 'closed', 'won', 'lost'])->default('active')->change();
        });
    }
};