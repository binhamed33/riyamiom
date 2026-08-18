<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_requests', function (Blueprint $table) {
            $table->id();
            $table->string('office_name');
            $table->string('contact_name');
            $table->string('phone');
            $table->string('email');
            $table->unsignedTinyInteger('lawyers_count')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_requests');
    }
};
