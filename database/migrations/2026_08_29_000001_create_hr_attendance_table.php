<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الحضور والانصراف — سجلٌّ واحد لكل موظف في اليوم.
 *
 * إضافة خالصة: جدول جديد لا يمسّ شيئاً قائماً، والحذف الخلفي يحذف
 * سجلّ الحضور مع صاحبه لأن سجلّ حضور بلا موظف لا معنى له —
 * بخلاف سجلّ القضية الذي يبقى شاهداً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_attendance')) {
            return;
        }

        Schema::create('hr_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            // يومٌ واحد لكل موظف: النقر المزدوج لا يصنع سجلّين
            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance');
    }
};
