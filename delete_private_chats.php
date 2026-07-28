<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Conversation;

Conversation::where('type', 'private')->chunk(50, function ($conversations) {
    foreach ($conversations as $c) {
        $c->messages()->delete();
        $c->participants()->delete();
        $c->delete();
        echo "Deleted conversation {$c->id}\n";
    }
});

echo "Done.\n";
