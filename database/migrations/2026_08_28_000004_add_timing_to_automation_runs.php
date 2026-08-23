<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توقيت التنفيذ ومحاولاته.
 *
 * سجلّ التشغيل يقول ماذا جرى ولا يقول متى بدأ ولا كم استغرق ولا كم
 * مرّة أُعيد. وحين تتباطأ قاعدة أو تعلق، لا شيء في السجل يدلّ عليها —
 * يبقى «نجح» بلا زمن، فلا يُعرف أنّ ما كان يأخذ ثانيةً صار يأخذ دقيقة.
 *
 * إضافة محضة: أربعة أعمدة اختيارية على جدول سجلّات لا على بيانات
 * المكتب. والصفوف القديمة تبقى بلا توقيت، وهو صحيح — لم يُقَس وقتها.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('automation_runs') || Schema::hasColumn('automation_runs', 'started_at')) {
            return;
        }

        Schema::table('automation_runs', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->unsignedInteger('duration_ms')->nullable()->after('finished_at');
            $table->unsignedSmallInteger('attempts')->default(1)->after('duration_ms');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('automation_runs') || !Schema::hasColumn('automation_runs', 'started_at')) {
            return;
        }

        Schema::table('automation_runs', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'finished_at', 'duration_ms', 'attempts']);
        });
    }
};
