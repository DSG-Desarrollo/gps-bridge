<?php

echo "[CRON TEST] Ejecutado: " . date('Y-m-d H:i:s') . PHP_EOL;

require_once __DIR__ . '/../vendor/autoload.php';

// Load env + app config
require_once __DIR__ . '/../config/app.php';

use App\Jobs\SyncLocationsJob;

$job = new SyncLocationsJob();
$job->handle();
