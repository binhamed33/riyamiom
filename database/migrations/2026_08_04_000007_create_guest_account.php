<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $guest = \App\Models\User::firstOrCreate(
            ['email' => 'guest@office.riyami.om'],
            [
                'name' => 'زائر',
                'role' => 'guest',
                'password' => Hash::make('guest123'),
                'is_active' => true,
            ]
        );

        if (!$guest->wasRecentlyCreated) {
            $guest->update([
                'name' => 'زائر',
                'role' => 'guest',
                'password' => Hash::make('guest123'),
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        \App\Models\User::where('email', 'guest@office.riyami.om')->delete();
    }
};
