<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حالة الحضور ومصدره.
 *
 * status: «حاضر» أو «منتهٍ» — يُكتب عند الحضور والانصراف معاً فلا يقرأ
 * أحدٌ حالةً محسوبةً بطريقتين مختلفتين في صفحتين. والسجلات القائمة
 * تُملأ من واقعها: من له انصراف فهو منتهٍ، ومن لا فهو حاضر.
 *
 * source: هل سجّله الموظف بيده أم أنشأه الدخول؟ يفصل بين حضورٍ قصده
 * صاحبه وحضورٍ استنتجه النظام — وهو فرقٌ يهمّ عند المراجعة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_attendance')) {
            return;
        }

        Schema::table('hr_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_attendance', 'status')) {
                $table->string('status', 20)->default('present');
            }
            if (! Schema::hasColumn('hr_attendance', 'source')) {
                $table->string('source', 20)->default('manual');
            }
        });

        DB::table('hr_attendance')->whereNotNull('check_out_at')->update(['status' => 'completed']);
        DB::table('hr_attendance')->whereNull('check_out_at')->update(['status' => 'present']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_attendance')) {
            return;
        }

        Schema::table('hr_attendance', function (Blueprint $table) {
            foreach (['status', 'source'] as $col) {
                if (Schema::hasColumn('hr_attendance', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
