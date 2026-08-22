<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سياق الاقتراح: عنوان + لقطة عن صاحبه ومكتبه وقت الإرسال.
 *
 * إضافة بحتة: عمودان اختياريان، ولا تمسّ أي اقتراح قائم — يبقى بلا
 * عنوان وبلا سياق ويُعرض كما هو.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            if (!Schema::hasColumn('suggestions', 'title')) {
                $table->string('title', 160)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('suggestions', 'context')) {
                $table->json('context')->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            foreach (['title', 'context'] as $column) {
                if (Schema::hasColumn('suggestions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
