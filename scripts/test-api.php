<?php
$hostname = 'https://staging.gps.gt/api/point';
$user = '3269_68af4001d049f';
$pass = '123456';

$payload = array(
    "timestamp" => "2026-02-11T21:54:41.000Z",
    "id"        => "EQ8109074",
    "lat" => 89.735367,
    "lon" => -13.697198,
    "kmph" => 49.0,
    "heading" => 27.0,
    "event" => 1,
    "gps" => true
);

$json_params = json_encode($payload, JSON_PRESERVE_ZERO_FRACTION);

$headers = array(
    'Content-Type: application/json',
    'Accept: application/json'
);

echo "➡️ URL: {$hostname}\n";
echo "➡️ User: {$user}\n";
echo "➡️ Payload: {$json_params}\n\n";

$verbose = fopen('php://temp', 'w+');

$ch = curl_init($hostname);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_params);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, $verbose);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
$info = curl_getinfo($ch);
curl_close($ch);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

echo "📡 HTTP Code: {$info['http_code']}\n";
echo "⏱️ Total Time: {$info['total_time']}s\n";

if ($curlErrno) {
    echo "❌ cURL Error ($curlErrno): $curlError\n";
}

echo "\n📨 Response:\n$response\n";
echo "\n🧠 cURL Verbose Log:\n$verboseLog\n";
echo "\n" . ($info['http_code'] === 200 ? "✅ SUCCESS\n" : "❌ FAILED\n");
