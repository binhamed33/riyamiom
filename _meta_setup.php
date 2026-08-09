<?php

$env = file_get_contents(__DIR__ . '/.env');
preg_match('/WHATSAPP_META_TOKEN=(.+)/', $env, $m);
$token = trim($m[1] ?? '');

$ch = curl_init('https://graph.facebook.com/v21.0/1356686042711018?fields=id,name,status,rejected_reason,category');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);

for ($i = 0; $i < 10; $i++) {
    curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v21.0/1356686042711018?fields=id,name,status,rejected_reason,category');
    $out = curl_exec($ch);
    echo "poll " . ($i + 1) . ": $out\n";
    $j = json_decode($out, true);
    if (isset($j['status']) && $j['status'] === 'APPROVED') {
        echo "APPROVED\n";
        break;
    }
    sleep(20);
}