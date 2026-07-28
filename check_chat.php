<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Get all private conversations with their participants
$convs = DB::table('conversations')->where('type', 'private')->get();
echo "Private conversations found: " . $convs->count() . "\n\n";

foreach ($convs as $conv) {
    $participants = DB::table('conversation_participants cp')
        ->join('users', 'cp.user_id', '=', 'users.id')
        ->where('cp.conversation_id', $conv->id)
        ->select('users.id', 'users.name', 'users.role')
        ->get();
    
    $roles = $participants->pluck('role')->toArray();
    $names = $participants->pluck('name')->toArray();
    $allEmployees = !in_array('client', $roles);
    
    $msgCount = DB::table('messages')->where('conversation_id', $conv->id)->count();
    
    echo "Conv #{$conv->id} ({$conv->created_at}) - {$msgCount} msgs - " . implode(', ', $names) . " [roles: " . implode(',', $roles) . "] " . ($allEmployees ? '✅ EMPLOYEES' : '❌ HAS CLIENT') . "\n";
}

echo "\n---\n";
// Count totals
$privateCount = DB::table('conversations')->where('type', 'private')->count();
$employeePrivateConvIds = DB::table('conversations AS c')
    ->where('c.type', 'private')
    ->whereNotExists(function ($q) {
        $q->select(DB::raw(1))
            ->from('conversation_participants AS cp')
            ->join('users', 'cp.user_id', '=', 'users.id')
            ->whereColumn('cp.conversation_id', 'c.id')
            ->where('users.role', 'client');
    })
    ->pluck('c.id');

echo "\nEmployee-only private conversations to delete: " . $employeePrivateConvIds->count() . "\n";
echo "IDs: " . $employeePrivateConvIds->implode(',') . "\n";

// Count the messages that would be deleted
$msgCount = DB::table('messages')->whereIn('conversation_id', $employeePrivateConvIds)->count();
echo "Messages to delete: {$msgCount}\n";
