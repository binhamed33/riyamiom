<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "Before: " . User::count() . " users\n";

// Clear incorrectly recovered users
DB::table('users')->where('id', '>=', 14)->delete();

$pw = Hash::make('password123');

// Get the latest data for each user from audit_logs (create + update)
$userData = [];

// 1. Get all create logs
$creates = DB::table('audit_logs')
    ->where('model_type', 'App\\Models\\User')
    ->where('action', 'create')
    ->whereNotNull('new_values')
    ->get();
foreach ($creates as $log) {
    $data = json_decode($log->new_values, true);
    if (isset($data['id'])) {
        $userData[$data['id']] = $data;
    }
}

// 2. Apply all updates in order (latest wins)
$updates = DB::table('audit_logs')
    ->where('model_type', 'App\\Models\\User')
    ->where('action', 'update')
    ->whereNotNull('new_values')
    ->orderBy('id')
    ->get();
foreach ($updates as $log) {
    $data = json_decode($log->new_values, true);
    if (isset($data['id'])) {
        if (!isset($userData[$data['id']])) $userData[$data['id']] = [];
        $userData[$data['id']] = array_merge($userData[$data['id']] ?? [], $data);
    }
}

// 3. Remove previously deleted users
$deletes = DB::table('audit_logs')
    ->where('model_type', 'App\\Models\\User')
    ->where('action', 'delete')
    ->whereNotNull('old_values')
    ->get();
foreach ($deletes as $log) {
    $data = json_decode($log->old_values, true);
    if (isset($data['id'])) {
        unset($userData[$data['id']]);
        echo "Skipping deleted user id={$data['id']}\n";
    }
}

echo "Rebuilding " . count($userData) . " users...\n";

$inserted = 0;
foreach ($userData as $uid => $data) {
    try {
        DB::table('users')->insert([
            'id' => $uid,
            'name' => $data['name'] ?? "User#$uid",
            'email' => $data['email'] ?? "u$uid@local",
            'password' => $pw,
            'role' => $data['role'] ?? 'staff',
            'phone' => $data['phone'] ?? '',
            'is_active' => true,
            'avatar' => null,
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'created_at' => $data['created_at'] ?? now(),
            'updated_at' => $data['updated_at'] ?? now(),
        ]);
        echo "  id=$uid name={$data['name']} email={$data['email']} role={$data['role']}\n";
        $inserted++;
    } catch (\Exception $e) {
        echo "  FAIL id=$uid: {$e->getMessage()}\n";
    }
}

echo "\nAfter: " . User::count() . " users\n";
echo "Passwords: password123\n";
echo "\nLogin with: admin@riyami.om / password123\n";
