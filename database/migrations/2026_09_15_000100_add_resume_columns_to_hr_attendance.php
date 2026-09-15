<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الدوامُ يُستأنف بعد انصرافٍ مسجَّل — فترتان في يومٍ واحد.
 *
 * ═══ الشكوى ═══
 *
 * المكاتبُ في عُمان تعمل فترتين: صباحيّةً إلى الواحدة والنصف ومسائيّةً
 * من الرابعة والنصف. ومن سجّل انصرافه ظهراً — أو خرج من النظام فسُجّل
 * له انصرافٌ — عاد عصراً فوجد «يومك مكتمل ✓» ولا شيءَ يعيد فتحه:
 * الفترةُ المسائيّة كلُّها لا تُحسب، وكشفُ الشهر أقلُّ من الحقيقة.
 *
 * ═══ ما تحمله الأعمدة ═══
 *
 * resumed_at: بدايةُ الفترة المفتوحة الآن إن كان اليومُ قد استُؤنف — فيُحسب
 * الانصرافُ التالي من هنا لا من الحضور الأوّل، وإلّا حُسبت الاستراحةُ دواماً.
 * وminutes تبقى مجموعَ الفترات المقفَلة.
 *
 * intervals: كم فترةً في اليوم — ليقول الكشفُ «فترتان» بدل أن يُقرأ
 * «08:29 — 20:30» على أنّه اثنتا عشرة ساعةً متّصلة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_attendance')) {
            return;
        }

        Schema::table('hr_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_attendance', 'resumed_at')) {
                $table->dateTime('resumed_at')->nullable()->after('check_out_at');
            }
            if (! Schema::hasColumn('hr_attendance', 'intervals')) {
                $table->unsignedTinyInteger('intervals')->default(1)->after('minutes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_attendance')) {
            return;
        }

        Schema::table('hr_attendance', function (Blueprint $table) {
            foreach (['resumed_at', 'intervals'] as $col) {
                if (Schema::hasColumn('hr_attendance', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
