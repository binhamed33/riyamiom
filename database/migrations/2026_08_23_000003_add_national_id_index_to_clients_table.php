<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهرس على رقم الهوية — إن كان العمود يقبل الفهرسة أصلاً.
 *
 * رقم الهوية مشفَّر، وعموده نوعه text. وMySQL يرفض فهرسة عمود text
 * بلا طول مفتاح: «BLOB/TEXT column used in key specification without a
 * key length». وSQLite يقبلها — فمرّت الهجرة في كل اختبار محلّي
 * وأسقطت كل تحديث على الخادم، ومحرّك النشر يستعيد النسخة ويقول
 * «migrate failed» بلا سبب مقروء.
 *
 * ولا حاجة إليها أصلاً: البحث يجري على national_id_hash — فهرس أعمى
 * من نوع string(64) تضيفه الهجرة التي تليها. وفهرسة نصٍّ مشفَّر لا
 * تُسرّع بحثاً لأن قيمته تختلف في كل صف حتى لو تطابق الأصل.
 *
 * فنُبقيها لعمود نصّي قصير إن وُجد، ونتخطّاها بصمت آمن على text.
 */
return new class extends Migration
{
    private const INDEX = 'clients_national_id_index';

    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'national_id')) {
            return;
        }

        // عمود نصّي طويل لا يُفهرَس بلا طول مفتاح — والبديل موجود
        if (in_array($this->columnType(), ['text', 'mediumtext', 'longtext', 'blob'], true)) {
            return;
        }

        if ($this->indexExists()) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->index('national_id', self::INDEX);
        });
    }

    public function down(): void
    {
        if ($this->indexExists()) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }
    }

    private function columnType(): string
    {
        try {
            return strtolower((string) Schema::getColumnType('clients', 'national_id'));
        } catch (\Throwable) {
            // تعذّر معرفة النوع: لا نُخاطر بفهرسة قد تُسقط التحديث
            return 'text';
        }
    }

    private function indexExists(): bool
    {
        try {
            return collect(Schema::getIndexes('clients'))->pluck('name')->contains(self::INDEX);
        } catch (\Throwable) {
            return false;
        }
    }
};
