<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نسبةُ المستند إلى شخصٍ مباشرةً — لا عبر قضيةٍ فقط.
 *
 * ═══ لماذا عمودٌ جديد ═══
 *
 * كان صاحبُ المستند يُستنتج من قضيّته: مستند ⇐ قضية ⇐ موكّل. وما
 * لا قضيةَ له فلا صاحبَ له، فيسقط في كومة «غير منسوبة» مهما عرف
 * الموظّفُ لمن هو — وكالةٌ قبل فتح القضية، هويةٌ، عقدٌ لموكّلٍ لم
 * يخاصم أحداً بعد.
 *
 * ولم يُغيَّر شيءٌ في القائم: الاستنتاجُ من القضية يبقى، والعمودُ
 * يعلوه حين يُملأ. فمستنداتُ اليوم تُقرأ كما كانت بلا تحويلٍ ولا
 * نقلِ صفّ.
 *
 * nullOnDelete لا cascade: حذفُ موكّلٍ لا يجرّ معه أوراقَ المكتب —
 * الورقةُ تبقى وتفقد نسبتَها، وهذا أهونُ من ملفٍّ يختفي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('case_id')
                ->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
