<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_COOKIEJAR => __DIR__.'/cookies.txt',
    CURLOPT_COOKIEFILE => __DIR__.'/cookies.txt',
]);

// Login
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/login');
$resp = curl_exec($ch);
preg_match('/name="_token"\s+value="([^"]+)"/', $resp, $m);
$token = $m[1] ?? '';

curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['_token' => $token, 'email' => 'admin@riyami.om', 'password' => 'password']));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);

curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

// Test notifications
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/notifications');
$resp = curl_exec($ch);
$info = curl_getinfo($ch);
echo "GET /notifications => {$info['http_code']}\n";

// Test mark all read
preg_match('/name="_token"\s+value="([^"]+)"/', $resp, $m2);
$token2 = $m2[1] ?? '';
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/notifications/read-all');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['_token' => $token2]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$resp2 = curl_exec($ch);
$info2 = curl_getinfo($ch);
echo "POST /notifications/read-all => {$info2['http_code']}\n";
if ($info2['http_code'] >= 400) {
    echo "Response: " . substr($resp2, 0, 500) . "\n";
}

curl_close($ch);
@unlink(__DIR__.'/cookies.txt');
