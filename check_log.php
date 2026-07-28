<?php
$log = file(__DIR__ . '/storage/logs/laravel.log');
$errors = [];
foreach (array_reverse($log) as $line) {
    if (str_contains($line, 'ERROR') || str_contains($line, 'profile') || str_contains($line, 'password')) {
        preg_match('/^\[([^\]]+)\] production\.ERROR: (.+)/', $line, $m);
        if ($m) {
            $msg = explode(' in ', explode(' {"exception":', $m[2])[0])[0];
            $errors[] = '['.$m[1].'] '.$msg;
        }
    }
}
echo implode("\n", array_slice($errors, 0, 10)) . "\n";
