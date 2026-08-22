<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أنواع المستندات — قائمة يديرها المكتب لا ثابتة في الشيفرة.
 *
 * ملاحظة توافق مقصودة: المستندات تبقى تحمل نوعها نصّاً في doc_type كما
 * هي اليوم، وهذا الجدول يغذّي القائمة ويُنظّم الفلترة. فلا مستند قائم
 * يتغيّر، ولا قيمة قديمة تُفقد لأنها ليست ضمن القائمة.
 *
 * جدول جديد فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_types')) {
            Schema::create('document_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort')->default(100);
                // «مدمج» يعني أنه جاء مع النظام — يُعطَّل ولا يُحذف
                $table->boolean('is_builtin')->default(false);
                $table->timestamps();

                $table->index(['is_active', 'sort']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
