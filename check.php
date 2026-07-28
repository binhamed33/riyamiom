<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "Current users:\n";
foreach (User::all() as $u) echo "  id={$u->id} name={$u->name} role={$u->role}\n";

// Get all created users from audit_logs
$creates = DB::table('audit_logs')
    ->where('model_type', 'App\\Models\\User')
    ->where('action', 'create')
    ->whereNotNull('new_values')
    ->get();

echo "\nRebuilding users from audit_logs...\n";
$pw = Hash::make('password123');

// First delete the wrong users (id 14, 15, 16)
User::whereIn('id', [14,15,16])->delete();

$deletedLogs = DB::table('audit_logs')
    ->where('model_type', 'App\\Models\\User')
    ->where('action', 'delete')
    ->whereNotNull('old_values')
    ->get();
$deletedIds = [];
foreach ($deletedLogs as $l) {
    $ov = json_decode($l->old_values, true);
    if (isset($ov['id'])) $deletedIds[] = $ov['id'];
}
echo "Previously deleted user IDs (skipping): " . json_encode($deletedIds) . "\n";

foreach ($creates as $log) {
    $data = json_decode($log->new_values, true);
    $uid = $data['id'] ?? null;
    if (!$uid) continue;
    if (in_array($uid, $deletedIds)) { echo "  SKIP id={$uid} (was deleted)\n"; continue; }
    
    // Check if latest version exists in an update log
    $updates = DB::table('audit_logs')
        ->where('model_type', 'App\\Models\\User')
        ->where('action', 'update')
        ->orderBy('id', 'desc')
        ->get();
    foreach ($updates as $u) {
        $udata = json_decode($u->new_values, true);
        if (isset($udata['id']) && $udata['id'] == $uid) {
            $data = array_merge($data, $udata);
            break;
        }
    }
    
    try {
        User::create([
            'id' => $uid,
            'name' => $data['name'] ?? 'Unknown',
            'email' => $data['email'] ?? "u{$uid}@local",
            'password' => $pw,
            'role' => $data['role'] ?? 'staff',
            'phone' => $data['phone'] ?? '',
            'is_active' => true,
        ]);
        echo "  Recovered id={$uid} name={$data['name']} email={$data['email']} role={$data['role']}\n";
    } catch (\Exception $e) {
        echo "  FAIL id={$uid}: {$e->getMessage()}\n";
    }
}

echo "\nFinal users:\n";
foreach (User::orderBy('id')->get() as $u) echo "  id={$u->id} name={$u->name} email={$u->email} role={$u->role}\n";
echo "\nAll passwords: password123\n";
