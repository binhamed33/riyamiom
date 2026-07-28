<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$count = User::count();
echo "Current users count: $count\n";

if ($count > 0) {
    echo "Users still exist. No recovery needed.\n";
    foreach (User::all() as $u) {
        echo "  id={$u->id} name={$u->name} role={$u->role}\n";
    }
    exit;
}

echo "All users deleted! Attempting to recover from audit_logs...\n";

// Get distinct user info from audit_logs
$logs = DB::table('audit_logs')
    ->select('user_id', DB::raw("MAX(JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.name'))) as name"), DB::raw("MAX(JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.email'))) as email"), DB::raw("MAX(JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.role'))) as role"))
    ->whereNotNull('user_id')
    ->where('auditable_type', 'App\\Models\\User')
    ->groupBy('user_id')
    ->get();

echo "Found " . count($logs) . " users from audit_logs\n";

$created = 0;
$defaultPassword = Hash::make('password123');

foreach ($logs as $log) {
    $name = $log->name ?: "User #{$log->user_id}";
    $email = $log->email ?: "user{$log->user_id}@recovered.local";
    $role = $log->role ?: 'staff';
    
    try {
        User::create([
            'id' => $log->user_id,
            'name' => $name,
            'email' => $email,
            'password' => $defaultPassword,
            'role' => $role,
            'is_active' => true,
        ]);
        echo "  Recovered id={$log->user_id} name=$name role=$role\n";
        $created++;
    } catch (\Exception $e) {
        echo "  Failed id={$log->user_id}: {$e->getMessage()}\n";
    }
}

// Also check other tables for user IDs not in audit_logs
$extraIds = DB::table('cases')->whereNotNull('lawyer_id')->distinct()->pluck('lawyer_id')->toArray();
$extraIds = array_merge($extraIds, DB::table('clients')->whereNotNull('user_id')->distinct()->pluck('user_id')->toArray());
$extraIds = array_unique($extraIds);

$existingIds = User::pluck('id')->toArray();
foreach ($extraIds as $uid) {
    if (!in_array($uid, $existingIds)) {
        try {
            User::create([
                'id' => $uid,
                'name' => "User #$uid",
                'email' => "user$uid@recovered.local",
                'password' => $defaultPassword,
                'role' => 'staff',
                'is_active' => true,
            ]);
            echo "  Recovered (extra) id=$uid\n";
            $created++;
            $existingIds[] = $uid;
        } catch (\Exception $e) {}
    }
}

echo "\nDone. $created users recovered.\n";
echo "All recovered users have password: password123\n";
echo "Please change the admin email and password after login.\n";
