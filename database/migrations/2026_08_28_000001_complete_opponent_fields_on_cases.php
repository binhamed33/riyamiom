<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إكمال بيانات الخصم.
 *
 * الموجود اليوم: الاسم والهاتف والعنوان والمحامي والرقم المدني.
 * والناقص يُسأل عنه في الواقع: بريده، وصفته في الدعوى (مدّعى عليه /
 * مدّعٍ / متدخّل)، ونوعه (فرد أو شركة أو جهة حكومية)، وملاحظة حرّة.
 *
 * إضافة محضة: خمسة أعمدة اختيارية، لا حذف ولا إعادة تسمية ولا نقل.
 * ما كُتب في الحقول القائمة يبقى كما هو حرفاً بحرف.
 *
 * ولم يُصنع جدول Opponent مستقل عمداً: البيانات اليوم أعمدة على
 * cases، ونقلها يعني ترحيل صفوف حيّة في كل مكتب — وهو ما لا يُفعل
 * لإرضاء شكل معماري. الحاجة المعلنة إكمال البيانات لا إعادة نمذجتها.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const COLUMNS = [
        'opponent_email' => 'after:opponent_phone',
        'opponent_role' => 'after:opponent_email',
        'opponent_type' => 'after:opponent_role',
        'opponent_notes' => 'after:opponent_type',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('cases')) {
            return;
        }

        Schema::table('cases', function (Blueprint $table) {
            // المشفَّر يُخزَّن text لا string: قيمة «enc:» تتجاوز مئتي
            // حرف، وعمودٌ ضيّق يبتر أو يرفض في الوضع الصارم. سبق أن
            // وقع هذا حرفياً في هذا الجدول — انظر هجرة
            // increase_encrypted_columns_length_in_cases_table.
            if (!Schema::hasColumn('cases', 'opponent_email')) {
                $table->text('opponent_email')->nullable()->after('opponent_phone');
            }
            if (!Schema::hasColumn('cases', 'opponent_notes')) {
                $table->text('opponent_notes')->nullable()->after('opponent_civil_number');
            }

            // التصنيفيّان لا يُشفَّران: عليهما يقع الترشيح والتجميع،
            // والمشفَّر لا يُرشَّح عليه ولا يُجمَّع.
            if (!Schema::hasColumn('cases', 'opponent_role')) {
                // صفته في الدعوى: مدّعى عليه / مدّعٍ / متدخّل
                $table->string('opponent_role', 40)->nullable()->after('opponent_notes');
            }
            if (!Schema::hasColumn('cases', 'opponent_type')) {
                // فرد / شركة / جهة حكومية
                $table->string('opponent_type', 40)->nullable()->after('opponent_role');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cases')) {
            return;
        }

        Schema::table('cases', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $column) {
                if (Schema::hasColumn('cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
