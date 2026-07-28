<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$users = User::orderBy('id')->get();
echo "Users: " . User::count() . "\n";
foreach ($users as $u) {
    echo "id={$u->id} name={$u->name} email={$u->email} role={$u->role} active={$u->is_active}\n";
}

echo "\n--- All distinct user IDs from other tables ---\n";
$tables = ['cases'=>'lawyer_id','sessions'=>'user_id','tasks'=>'user_id','documents'=>'user_id','finance_transactions'=>'user_id','finance_invoices'=>'user_id','finance_fees'=>'user_id','audit_logs'=>'user_id','clients'=>'user_id','notifications'=>'user_id','conversation_participants'=>'user_id','hr_employees'=>'id','hr_leaves'=>'employee_id','hr_performances'=>'employee_id','hr_bonuses'=>'employee_id','hr_penalties'=>'employee_id'];
foreach ($tables as $t=>$c) {
    try {
        $ids = DB::table($t)->whereNotNull($c)->distinct()->pluck($c)->toArray();
        if ($ids) echo "$t.$c: " . json_encode($ids) . "\n";
    } catch(\Exception $e) {}
}

echo "\n--- Audit logs for User model ---\n";
$logs = DB::table('audit_logs')
    ->where('model_type', 'App\\Models\\User')
    ->select('id','user_id','action','old_values','new_values')
    ->orderBy('id')
    ->get();
foreach ($logs as $l) {
    echo "log#{$l->id} user_id={$l->user_id} action={$l->action}\n";
    echo "  old: {$l->old_values}\n  new: {$l->new_values}\n";
}
