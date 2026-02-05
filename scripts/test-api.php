<?php

$tests = [
    ['user' => '3269_68af4001d049f', 'pass' => '123456', 'url' => 'https://staging.gps.gt/api/point'],

];

$payload = [
    'timestamp' => '2026-01-25T03:25:46Z',
    'id' => 'TEST-001',
    'lat' => 13.438535,
    'lon' => -88.9733933,
    'kmph' => 0,
    'heading' => 254,
    'event' => 1,
    'gps' => 1
];

foreach ($tests as $i => $test) {
    echo "\n=== Test " . ($i + 1) . " ===\n";
    echo "API Key: {$test['user']}\n";
    echo "Pass: {$test['pass']}\n";
    echo "Hostname: {$test['url']}\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $test['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $test['user'] . ':' . $test['pass'],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo ($httpCode === 200 ? "✅ SUCCESS!\n" : "❌ FAILED\n");
}