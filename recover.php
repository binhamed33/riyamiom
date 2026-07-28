<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$count = User::count();
echo "Users: $count\n";
if ($count > 0) { echo "OK\n"; exit; }

// Get user IDs from all tables
$ids = [];
foreach (['cases'=>'lawyer_id','sessions'=>'user_id','tasks'=>'user_id','documents'=>'user_id','finance_transactions'=>'user_id','finance_invoices'=>'user_id','finance_fees'=>'user_id','audit_logs'=>'user_id','clients'=>'user_id','notifications'=>'user_id'] as $t=>$c) {
    try { $ids = array_merge($ids, DB::table($t)->whereNotNull($c)->distinct()->pluck($c)->toArray()); } catch(\Exception $e) {}
}
$ids = array_unique($ids);
sort($ids);
echo "Found user IDs: " . json_encode($ids) . "\n";

// Try to get name/email from audit_logs where record was modified
$info = DB::table('audit_logs')
    ->where('model_type', 'App\\Models\\User')
    ->whereNotNull('old_values')
    ->select('user_id', 'old_values')
    ->orderBy('created_at', 'desc')
    ->get()
    ->groupBy('user_id')
    ->map(function($items) {
        foreach ($items as $item) {
            $ov = json_decode($item->old_values, true);
            if (isset($ov['name']) || isset($ov['email']) || isset($ov['role'])) return $ov;
        }
        return [];
    });

echo "User info from audit_logs:\n";
foreach ($info as $uid => $data) {
    echo "  id=$uid name=".($data['name']??'?')." email=".($data['email']??'?')." role=".($data['role']??'?')."\n";
}

// Recreate with available info
$pw = Hash::make('password123');
foreach ($ids as $uid) {
    $data = $info->get($uid, []);
    try {
        User::create([
            'id' => $uid,
            'name' => $data['name'] ?? "User#$uid",
            'email' => $data['email'] ?? "u$uid@local",
            'password' => $pw,
            'role' => $data['role'] ?? 'staff',
            'is_active' => true,
        ]);
        echo "Created id=$uid\n";
    } catch(\Exception $e) {
        echo "FAIL id=$uid: {$e->getMessage()}\n";
    }
}

echo "Done. Password: password123\n";
