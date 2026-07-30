<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cases = DB::table('cases')
            ->whereNull('office_case_number')
            ->orWhere('office_case_number', '')
            ->orderBy('created_at')
            ->get();

        $max = DB::table('cases')->max(DB::raw('office_case_number + 0')) ?? 0;

        foreach ($cases as $case) {
            $max++;
            DB::table('cases')
                ->where('id', $case->id)
                ->update(['office_case_number' => (string) $max]);
        }
    }

    public function down(): void
    {
        // Can't reverse
    }
};
