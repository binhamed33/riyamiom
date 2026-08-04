<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('discord_message_id', 64)->nullable()->index()->after('edited_at');
            $table->timestamp('discord_replied_at')->nullable()->after('discord_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['discord_message_id', 'discord_replied_at']);
        });
    }
};
