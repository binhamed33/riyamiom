<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §13: محاسبة القضية امتداداً للقسم المالي القائم لا نظاماً موازياً.
 *
 * الرسوم مرتبطة بالقضية أصلاً (finance_fees.case_id)، والفاتورة كانت
 * مرتبطة بالموكّل وحده — فلا يُعرف عن أي قضية صدرت. والاثنان لا يصلان
 * الموكّل إطلاقاً.
 *
 * تُضاف حلقتان لا جدول جديد: ربط الفاتورة بالقضية، وحقل رؤية صريح على
 * كلٍّ منهما. الافتراضي «غير مرئي»: المال لا يُعرَض على الموكّل إلا
 * بقرارٍ من المكتب، لا بغفلة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_fees', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_fees', 'client_visible')) {
                $table->boolean('client_visible')->default(false)->after('status');
            }
        });

        Schema::table('finance_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_invoices', 'case_id')) {
                $table->foreignId('case_id')->nullable()->after('client_id')
                    ->constrained('cases')->nullOnDelete();
            }
            if (! Schema::hasColumn('finance_invoices', 'client_visible')) {
                $table->boolean('client_visible')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        // الحقول تُزال ولا تُمسّ أي صفٍّ مالي: النزول يعيد الشكل لا يحذف مالاً
        Schema::table('finance_fees', function (Blueprint $table) {
            if (Schema::hasColumn('finance_fees', 'client_visible')) {
                $table->dropColumn('client_visible');
            }
        });

        Schema::table('finance_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('finance_invoices', 'case_id')) {
                $table->dropConstrainedForeignId('case_id');
            }
            if (Schema::hasColumn('finance_invoices', 'client_visible')) {
                $table->dropColumn('client_visible');
            }
        });
    }
};
