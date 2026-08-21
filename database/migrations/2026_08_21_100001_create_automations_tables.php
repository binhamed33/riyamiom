<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مركز الأتمتة: قواعد يبنيها المدير (متى/إذا/نفّذ) + سجل تنفيذ كامل.
 * إضافة جداول جديدة فقط — لا تمس أي بيانات قائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger'); // session_approaching, session_completed, case_stale, ...
            // صفوف الشروط: [{field, operator, value}]
            $table->json('conditions')->nullable();
            // صفوف الإجراءات: [{type, params...}]
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'trigger']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('automation_id')->nullable(); // null = عملية نظام (تذكير قالب مثلاً)
            $table->string('trigger');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->string('status'); // success | failed | skipped
            $table->string('summary')->nullable();
            $table->text('error')->nullable();
            // مفتاح منع التكرار: نفس القاعدة + نفس الموضوع لا تنفَّذ مرتين
            $table->string('dedupe_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['automation_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automations');
    }
};
