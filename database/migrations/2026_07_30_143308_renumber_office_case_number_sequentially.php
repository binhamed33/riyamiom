<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cases = DB::table('cases')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $num = 1;
        foreach ($cases as $case) {
            DB::table('cases')
                ->where('id', $case->id)
                ->update(['office_case_number' => (string) $num]);
            $num++;
        }
    }

    public function down(): void
    {
    }
};
