<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * رقم النسخة فريد لكل عنصر.
 *
 * capture() تقرأ أكبر رقم ثم تكتب الذي يليه — وتعديلان متزامنان يقرآن
 * الرقم نفسه فيكتبان نسختين برقم واحد، ثم تستعيد restore() أيّهما صادفت.
 * الجدول جديد (أُنشئ في هذه الدفعة) فلا صفوف قديمة تصطدم بالقيد، ومع
 * ذلك نُزيل أي تكرار محتمل قبل إضافته حتى لا تفشل الهجرة على مكتب
 * سبقتنا إليه نسخةٌ أقدم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('revision_snapshots')) {
            return;
        }

        // إزالة التكرار إن وُجد: يُبقى الأحدث (أكبر id) ويُحذف ما سواه
        $duplicates = DB::table('revision_snapshots')
            ->select('subject_type', 'subject_id', 'version')
            ->groupBy('subject_type', 'subject_id', 'version')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('revision_snapshots')
                ->where('subject_type', $dup->subject_type)
                ->where('subject_id', $dup->subject_id)
                ->where('version', $dup->version)
                ->orderByDesc('id')
                ->pluck('id')
                ->slice(1);

            if ($ids->isNotEmpty()) {
                DB::table('revision_snapshots')->whereIn('id', $ids)->delete();
            }
        }

        Schema::table('revision_snapshots', function (Blueprint $table) {
            $table->unique(['subject_type', 'subject_id', 'version'], 'revision_subject_version_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('revision_snapshots')) {
            return;
        }

        Schema::table('revision_snapshots', function (Blueprint $table) {
            $table->dropUnique('revision_subject_version_unique');
        });
    }
};
