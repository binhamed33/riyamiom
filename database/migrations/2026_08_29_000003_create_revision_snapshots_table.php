<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاريخ نسخ القوالب والأتمتة (§27) — لقطة كاملة قبل كل تعديل.
 *
 * إضافة خالصة: جدول جديد، ولا يُحذف منه شيء عند حذف صاحب اللقطة —
 * التاريخ يبقى شاهداً حتى لو رحل المستخدم الذي عدّل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('revision_snapshots')) {
            return;
        }

        Schema::create('revision_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('version');
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_snapshots');
    }
};
