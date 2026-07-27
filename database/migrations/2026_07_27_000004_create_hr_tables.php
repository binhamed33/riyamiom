<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('review_date');
            $table->tinyInteger('rating')->comment('1-5');
            $table->text('notes')->nullable();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('reason');
            $table->date('date');
            $table->foreignId('given_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('reason');
            $table->date('date');
            $table->foreignId('given_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_penalties');
        Schema::dropIfExists('hr_bonuses');
        Schema::dropIfExists('hr_performance');
    }
};
