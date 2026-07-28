<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Last 5 ERROR lines from log
$log = file(__DIR__ . '/storage/logs/laravel.log');
$errors = [];
foreach ($log as $line) {
    if (str_contains($line, 'ERROR')) $errors[] = $line;
}
$last5 = array_slice($errors, -5);
echo "=== Last 5 errors ===\n";
foreach ($last5 as $e) {
    preg_match('/^\[[^\]]+\] production\.ERROR: ([^\{]+)/', $e, $m);
    if ($m) echo trim($m[1]) . "\n";
}

echo "\n=== Test REGEXP query ===\n";
$result = DB::table('cases')
    ->whereRaw('case_number REGEXP "^[0-9]+$"')
    ->max(DB::raw('CAST(case_number AS UNSIGNED)'));
echo "Max case number: " . ($result ?: 'none') . "\n";

echo "\n=== All cases ===\n";
DB::table('cases')->select('id', 'case_number')->orderBy('id')->chunk(100, function($chunk) {
    foreach ($chunk as $c) echo "  id={$c->id} case_number='{$c->case_number}'\n";
});
