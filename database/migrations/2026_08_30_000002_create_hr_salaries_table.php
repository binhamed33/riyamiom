<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الرواتب — جدولان جديدان لا يمسّان شيئاً قائماً.
 *
 * hr_salaries: الراتب الجاري للموظف، صفٌّ واحد لكل موظف.
 * hr_payroll_adjustments: بدل أو خصم لفترةٍ بعينها (شهر) — لأن مكافأة
 * شهرٍ ليست راتباً دائماً، وخلطهما في عمود واحد يجعل كشف الشهر الماضي
 * يتغيّر كلما عُدّل الراتب اليوم.
 *
 * الحذف الخلفي: راتبٌ بلا موظف لا معنى له، فيمضي معه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_salaries')) {
            Schema::create('hr_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('basic_salary', 10, 2)->default(0);
                $table->decimal('allowances', 10, 2)->default(0);
                $table->date('effective_from')->nullable();
                $table->string('note', 255)->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                // راتبٌ واحد جارٍ لكل موظف: صفّان يعنيان كشفين متناقضين
                $table->unique('employee_id');
            });
        }

        if (! Schema::hasTable('hr_payroll_adjustments')) {
            Schema::create('hr_payroll_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->string('period', 7);            // YYYY-MM
                $table->enum('kind', ['allowance', 'deduction']);
                $table->decimal('amount', 10, 2);
                $table->string('reason', 255);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_id', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_adjustments');
        Schema::dropIfExists('hr_salaries');
    }
};
