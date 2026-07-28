<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

foreach (User::orderBy('id')->get() as $u) {
    $works = Hash::check('password123', $u->password) ? 'OK' : 'FAIL';
    echo "email={$u->email} role={$u->role} password_status={$works}\n";
}
