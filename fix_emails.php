<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$users = User::all();
foreach ($users as $u) {
    $newEmail = explode('@', $u->email)[0] . '@23';
    DB::table('users')->where('id', $u->id)->update(['email' => $newEmail]);
    echo "{$u->email} -> {$newEmail}\n";
}
echo "Done.\n";
