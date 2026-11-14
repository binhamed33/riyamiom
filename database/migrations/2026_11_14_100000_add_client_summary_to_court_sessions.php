<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «ما جرى في الجلسة» بلسانٍ يُقال للموكّل — منفصلاً عن القرار الداخلي.
 *
 * كانت بوابةُ الموكّل تعرض الجلسةَ تاريخاً ومكاناً وحالةً فقط، و«القرار»
 * و«الملاحظات» داخليّان (خطّةٌ وتقديرٌ وما لا يُقال للموكّل). فلم يكن
 * للمحامي مكانٌ يكتب فيه ما يريد أن يعرفه الموكّل، فبدت البوابةُ فارغة.
 *
 * والجدولُ court_sessions لا sessions: ذاك جدولُ جلسات الدخول في Laravel،
 * وعمودٌ فيه لا يراه أحد.
 *
 * إضافةُ عمودٍ اختياريٍّ فقط: لا تمسّ صفّاً قائماً، وفارغُه يعني «لا شيء
 * يُعرض» كما كان.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('court_sessions', 'client_summary')) {
            Schema::table('court_sessions', function (Blueprint $table) {
                $table->text('client_summary')->nullable()->after('report');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('court_sessions', 'client_summary')) {
            Schema::table('court_sessions', function (Blueprint $table) {
                $table->dropColumn('client_summary');
            });
        }
    }
};
