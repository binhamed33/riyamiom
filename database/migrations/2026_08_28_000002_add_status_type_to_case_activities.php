<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع أنواع أحداث القضية بنوع «تغيّر الحالة».
 *
 * المسار الزمني للموكّل مطلوبٌ أن يُظهر تحديث حالة القضية، والعمود
 * enum مغلق على ثمانية أنواع ليس فيها هذا.
 *
 * توسيع enum إضافةٌ محضة: القيم القائمة تبقى صالحة وصفوفها كما هي،
 * ولا يُحذف نوع ولا يُعاد تسميته. وعلى sqlite العمود نصّ أصلاً فلا
 * شيء يُنفَّذ.
 */
return new class extends Migration
{
    private const TYPES = ['note', 'call', 'document', 'task', 'session', 'payment', 'appointment', 'status', 'other'];

    public function up(): void
    {
        if (!Schema::hasTable('case_activities') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $list = implode(',', array_map(fn ($t) => "'" . $t . "'", self::TYPES));

        DB::statement("ALTER TABLE `case_activities` MODIFY `type` ENUM({$list}) NOT NULL DEFAULT 'note'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('case_activities') || DB::getDriverName() !== 'mysql') {
            return;
        }

        // لا يُضيَّق العمود وفيه صفوف من النوع الجديد — تضييقه يمسحها
        if (DB::table('case_activities')->where('type', 'status')->exists()) {
            return;
        }

        $old = array_values(array_diff(self::TYPES, ['status']));
        $list = implode(',', array_map(fn ($t) => "'" . $t . "'", $old));

        DB::statement("ALTER TABLE `case_activities` MODIFY `type` ENUM({$list}) NOT NULL DEFAULT 'note'");
    }
};
