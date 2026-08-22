<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الدخول إلى البوابة يبحث برقم الهوية في كل محاولة. فهرس فقط —
 * لا قيد تفرّد: مكاتب قائمة قد تحمل تكراراً أو فراغاً، وإضافة قيد
 * تفرّد الآن قد تُفشل الهجرة على بيانات حقيقية.
 */
return new class extends Migration
{
    private const INDEX = 'clients_national_id_index';

    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'national_id')) {
            return;
        }

        $existing = collect(Schema::getIndexes('clients'))->pluck('name');

        if (!$existing->contains(self::INDEX)) {
            Schema::table('clients', function (Blueprint $table) {
                $table->index('national_id', self::INDEX);
            });
        }
    }

    public function down(): void
    {
        $existing = collect(Schema::getIndexes('clients'))->pluck('name');

        if ($existing->contains(self::INDEX)) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};
