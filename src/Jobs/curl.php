<?php

$API_key = "3269_68af4001d049f";
$PW      = "123456";
$URL_BASE     = "https://staging.gps.gt/api/point";

$headers = [
    "Content-Type: application/json"
];


$params = array(
    "timestamp" => "2026-02-15T14:50:00.000Z",
    "id"        => "EQ5C137468",
    "lat"       => 13.716955,
    "lon"       => -89.096052,
    "kmph"      => 30.0,
    "heading"   => 17.5,
    "event"     => 1,
    "gps"       => true
);

$ch = curl_init();

var_dump(json_encode($params, JSON_PRESERVE_ZERO_FRACTION));

curl_setopt_array($ch, [
    CURLOPT_URL             => $URL_BASE,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
    CURLOPT_USERPWD        => $API_key . ":" . $PW,
    CURLOPT_POSTFIELDS     => json_encode($params, JSON_PRESERVE_ZERO_FRACTION),
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "Error cURL: " . curl_error($ch);
} else {
    echo "HTTP Code: " . $httpCode . PHP_EOL;
    echo "Response: " . $response . PHP_EOL;
}

curl_close($ch);
