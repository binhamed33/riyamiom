<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مجلدٌ داخل مجلد: عمودُ الأب على مجلدات القضية.
 *
 * ═══ لماذا ═══
 *
 * «يضيف ملفاً أو مجلداً، وإذا أضاف مجلداً يدخله ويضيف فيه» — تنظيمُ
 * القضايا الحقيقيُّ شجرةٌ لا صفّاً واحداً: «مذكرات» وفيها «ابتدائي»
 * و«استئناف»، و«سندات» وفيها سنةٌ بعد سنة.
 *
 * عمودٌ واحدٌ اختياريّ، ولا يُمسّ صفٌّ قائم: كلُّ المجلدات الحالية
 * جذريّةٌ كما كانت (parent_id فارغ)، والحذفُ عند القاعدة يُرجع
 * الأبناء جذوراً لا يمسحهم — والمتحكّمُ فوقها يرقّيهم إلى جدّهم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('case_folders') || Schema::hasColumn('case_folders', 'parent_id')) {
            return;
        }

        Schema::table('case_folders', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('case_id')
                ->constrained('case_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('case_folders', 'parent_id')) {
            return;
        }

        Schema::table('case_folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
