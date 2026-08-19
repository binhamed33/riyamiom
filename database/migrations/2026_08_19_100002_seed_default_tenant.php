<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = DB::table('tenants')->orderBy('id')->value('id');

        if (!$tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name'       => Setting::get('office_name') ?: 'المكتب القانوني',
                'owner_name' => Setting::get('office_manager_name') ?: null,
                'email'      => Setting::get('office_email') ?: 'admin@riyami.om',
                'phone'      => Setting::get('office_phone') ?: null,
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')
            ->whereNull('tenant_id')
            ->where('role', '!=', 'developer')
            ->update(['tenant_id' => $tenantId]);
    }

    public function down(): void
    {
        DB::table('users')->update(['tenant_id' => null]);
        DB::table('tenants')->delete();
    }
};