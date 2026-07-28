<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Find employee-only private conversation IDs
$employeeOnlyIds = DB::select(
    'SELECT c.id FROM conversations c WHERE c.type = ? AND c.id NOT IN (SELECT cp.conversation_id FROM conversation_participants cp JOIN users u ON cp.user_id = u.id WHERE u.role = ?)',
    ['private', 'client']
);

$ids = array_column($employeeOnlyIds, 'id');

if (empty($ids)) {
    echo "No employee-only private conversations found.\n";
    exit;
}

echo "Found " . count($ids) . " employee-only conversations.\n";

// Delete messages first (foreign key constraint)
$deletedMsgs = DB::table('messages')->whereIn('conversation_id', $ids)->delete();
echo "Deleted {$deletedMsgs} messages.\n";

// Delete participants
$deletedParts = DB::table('conversation_participants')->whereIn('conversation_id', $ids)->delete();
echo "Deleted {$deletedParts} participant records.\n";

// Delete conversations
$deletedConvs = DB::table('conversations')->whereIn('id', $ids)->delete();
echo "Deleted {$deletedConvs} conversations.\n";

echo "Done.\n";
