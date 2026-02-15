<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Load env + app config
require_once __DIR__ . '/../../config/app.php';

use App\Services\CemproService;

$service = new CemproService();

$payload = array(
    "timestamp" => "2026-02-15T14:30:00.000Z",
    "id"        => "EQ6102985",
    "lat"       => 40.7136376,
    "lon"       => -74.0132078,
    "kmph"      => 40.0,
    "heading"   => 10.0,
    "event"     => 1,
    "gps"       => true,
);

$service->sendLocation($payload);
