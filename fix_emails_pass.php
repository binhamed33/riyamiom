<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

foreach (User::all() as $u) {
    $local = explode('@', $u->email)[0];
    $oldEmail = $local . '@riyami.om';
    $newPassword = Hash::make($u->email); // password = username@23 (current email)
    DB::table('users')->where('id', $u->id)->update([
        'email' => $oldEmail,
        'password' => $newPassword,
    ]);
    echo "{$u->email} -> {$oldEmail}  |  password={$u->email}\n";
}
echo "Done.\n";
