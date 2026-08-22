<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محاولات الدخول إلى بوابة العملاء.
 *
 * لا يُخزَّن رقم الهوية نفسه: بصمة غير قابلة للعكس تكفي لربط المحاولات
 * ببعضها ولكشف التخمين، ولا تكشف هوية أحد لو تسرّب الجدول.
 *
 * جدول جديد فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_portal_attempts')) {
            Schema::create('client_portal_attempts', function (Blueprint $table) {
                $table->id();
                $table->string('identifier_hash', 64)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('step', 20);          // lookup | verify
                $table->boolean('succeeded')->default(false);
                $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->index(['identifier_hash', 'created_at']);
                $table->index(['ip', 'created_at']);
                $table->index(['succeeded', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_attempts');
    }
};
