<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('email', 'admin@riyami.om')->update(['role' => 'developer']);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'admin@riyami.om')->update(['role' => 'admin']);
    }
};
