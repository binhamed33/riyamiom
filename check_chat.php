<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$convs = DB::table('conversations')->where('type', 'private')->get();
echo "Private conversations found: " . $convs->count() . "\n\n";

foreach ($convs as $conv) {
    $participants = DB::select(
        'SELECT u.id, u.name, u.role FROM conversation_participants cp JOIN users u ON cp.user_id = u.id WHERE cp.conversation_id = ?',
        [$conv->id]
    );

    $roles = array_column($participants, 'role');
    $names = array_column($participants, 'name');
    $allEmployees = !in_array('client', $roles);

    $msgCount = DB::table('messages')->where('conversation_id', $conv->id)->count();

    echo "Conv #{$conv->id} ({$conv->created_at}) - {$msgCount} msgs - " . implode(', ', $names) . " [roles: " . implode(',', $roles) . "] " . ($allEmployees ? '✅ EMPLOYEES' : '❌ HAS CLIENT') . "\n";
}

echo "\n---\n";

// Find private conversations where none of the participants have role=client
$employeeOnlyIds = DB::select(
    'SELECT c.id FROM conversations c WHERE c.type = ? AND c.id NOT IN (SELECT cp.conversation_id FROM conversation_participants cp JOIN users u ON cp.user_id = u.id WHERE u.role = ?)',
    ['private', 'client']
);

$ids = array_column($employeeOnlyIds, 'id');
echo "Employee-only private conversations to delete: " . count($ids) . "\n";
echo "IDs: " . implode(',', $ids) . "\n";

if (!empty($ids)) {
    $msgCount = DB::table('messages')->whereIn('conversation_id', $ids)->count();
    echo "Messages to delete: {$msgCount}\n";
}
