<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أنواع الإجازات صارت بيانات لا ثوابت في الكود.
 *
 * كان النوع enum مغلقاً في الجدول: مكتبٌ يريد «إجازة حج» أو يريد أن
 * تكون المرضية مدفوعة عنده وغير مدفوعة عند غيره لا يملك ذلك. الأنواع
 * الستّة القديمة تُزرع هنا بأسمائها نفسها فتبقى كل إجازة قائمة مقروءة،
 * وعمود type القديم يبقى كما هو — لا نُسقط ما تقرأه نسخةٌ أقدم من الكود.
 */
return new class extends Migration
{
    /** الأنواع الستّة التي كان الـenum يحصرها، وحكمها في الراتب. */
    private const SEED = [
        ['code' => 'annual',    'name' => 'إجازة سنوية',   'affects_salary' => false, 'sort' => 1],
        ['code' => 'sick',      'name' => 'إجازة مرضية',   'affects_salary' => false, 'sort' => 2],
        ['code' => 'emergency', 'name' => 'إجازة طارئة',   'affects_salary' => false, 'sort' => 3],
        ['code' => 'maternity', 'name' => 'إجازة أمومة',   'affects_salary' => false, 'sort' => 4],
        ['code' => 'unpaid',    'name' => 'إجازة بلا أجر', 'affects_salary' => true,  'sort' => 5],
        ['code' => 'other',     'name' => 'أخرى',          'affects_salary' => false, 'sort' => 6],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('hr_leave_types')) {
            Schema::create('hr_leave_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->boolean('affects_salary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        // الزرع لا يمسّ صفاً موجوداً: مكتبٌ عدّل «المرضية» لتخصم يبقى تعديله
        foreach (self::SEED as $row) {
            $exists = DB::table('hr_leave_types')->where('code', $row['code'])->exists();
            if (! $exists) {
                DB::table('hr_leave_types')->insert($row + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('hr_leaves', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_leaves', 'leave_type_id')) {
                $table->foreignId('leave_type_id')->nullable()->constrained('hr_leave_types')->nullOnDelete();
            }
            if (! Schema::hasColumn('hr_leaves', 'days')) {
                $table->unsignedSmallInteger('days')->nullable();
            }
            if (! Schema::hasColumn('hr_leaves', 'deduction_amount')) {
                $table->decimal('deduction_amount', 10, 2)->nullable();
            }
        });

        // ربط الإجازات القائمة بنوعها الجديد عبر الرمز — بلا حذف ولا تعديل
        // لأي حقل آخر، والصف الذي لا يُطابَق يبقى بمرجعٍ فارغ لا مكسوراً.
        foreach (DB::table('hr_leave_types')->get() as $type) {
            DB::table('hr_leaves')
                ->whereNull('leave_type_id')
                ->where('type', $type->code)
                ->update(['leave_type_id' => $type->id]);
        }
    }

    /**
     * التراجع يُسقط ما أضافته هذه الهجرة وحده.
     *
     * أما عمود type وصفوف hr_leaves فتبقى: هي بيانات المكتب لا بيانات
     * هذه الهجرة، والتراجع عن ترقية لا يجوز أن يكلّف إجازةً واحدة.
     */
    public function down(): void
    {
        Schema::table('hr_leaves', function (Blueprint $table) {
            foreach (['leave_type_id', 'days', 'deduction_amount'] as $col) {
                if (Schema::hasColumn('hr_leaves', $col)) {
                    if ($col === 'leave_type_id') {
                        try { $table->dropForeign(['leave_type_id']); } catch (\Throwable) {}
                    }
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('hr_leave_types');
    }
};
