<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل طلبات الذكاء الاصطناعي — أرقام لا نصوص.
 *
 * يُسجَّل من ومتى وأي نموذج وكم استغرق وهل نجح — ولا يُسجَّل نصّ
 * السؤال ولا الإجابة: أسئلة المكتب القانونية ليست مادةً للسجلات،
 * والمفاتيح لا تقترب من هنا أصلاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_requests')) {
            return;
        }

        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('model', 80)->nullable();
            $table->string('status', 10); // ok | error
            $table->string('error_type', 60)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
