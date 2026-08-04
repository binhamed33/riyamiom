<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('content');
            $table->text('developer_reply')->nullable()->after('status');
            $table->timestamp('replied_at')->nullable()->after('developer_reply');
            $table->boolean('reply_read')->default(false)->after('replied_at');
        });
    }

    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropColumn(['status', 'developer_reply', 'replied_at', 'reply_read']);
        });
    }
};
