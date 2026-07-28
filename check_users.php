<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::orderBy('id')->get();
echo "Total users: " . User::count() . "\n";
foreach ($users as $u) {
    echo "id={$u->id} name={$u->name} role={$u->role} email={$u->email}\n";
}
