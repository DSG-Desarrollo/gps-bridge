<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Load env + app config
require_once __DIR__ . '/../../config/app.php';

use App\Services\WialonService;
use Config\Wialon;

const TOKEN = "d3ef4fb2004591d7f70c86cd42706c497242312417BBEA364FB342BACFF2FFAF0B06FE0D";

$service = new WialonService();

$getParams = Wialon::searchItemsWithLocation();

$service->login(TOKEN);

$response = $service->call("core_search_items", $getParams);



$dir = __DIR__ . '/../../storage/wialon_responses2';

if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

file_put_contents(
    $dir . "/test.json",
    json_encode($response, JSON_PRETTY_PRINT)
);

$service->logout();
