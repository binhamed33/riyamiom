<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محادثة المساعد كانت تعيش في الجلسة.
 *
 * فمن أغلق المتصفّح، أو دخل من حاسوب المكتب بعد هاتفه، أو انتهت
 * جلسته بعد ساعتين — وجد المحادثة قد ذهبت بما فيها. والمحامي يبني
 * سؤاله على ما قبله، فذهابُها ذهابُ العمل نفسه.
 *
 * إضافةٌ محضة: جدول جديد لا يمسّ شيئاً قائماً، ولا عمود يُعدَّل، ولا
 * صفٌّ يُحذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assistant_messages')) {
            return;
        }

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->timestamps();

            // القراءة دائماً «رسائل هذا الموظّف بترتيبها»
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
    }
};
