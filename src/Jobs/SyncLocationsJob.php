<?php

namespace App\Jobs;

use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\WialonService;
use Config\Wialon;
use App\Services\WialonMapper;
use App\Services\CemproService;

class SyncLocationsJob
{
    private UnitRepository $units;
    private UserRepository $users;

    public function __construct()
    {
        $this->units = new UnitRepository();
        $this->users = new UserRepository();
    }

    public function handle()
    {
        $wialonService = new WialonService();
        $cemproService = new CemproService();
        $getParams = Wialon::searchItemsWithLocation();
        $users = $this->users->getUsersByIntegration('cempro');

        foreach ($users as $user) {
            $login = $wialonService->login($user['wa_token']);

            if (!$login) {
                echo "Login failed" . PHP_EOL;
                continue;
            }

            // Get all units from wialon
            $response = $wialonService->call("core_search_items", $getParams);

            if (
                !isset($response['items']) ||
                empty($response['items'])
            ) {
                echo "No positions returned" . PHP_EOL;
                continue;
            }

            $wialonIndex = [];

            foreach ($response['items'] as $item) {
                $wialonIndex[$item['id']] = $item;
            }

            $units = $this->units->getUnitsByUser($user['id_usuario']);

            foreach ($units as $unit) {

                $waUnitId = $unit['wa_unit_id'];

                if (!isset($wialonIndex[$waUnitId])) {
                    continue;
                }

                $item = $wialonIndex[$waUnitId];

                if (!isset($item['pos'])) {
                    continue;
                }

                $payload = WialonMapper::mapToCempro($unit, $item);

                echo "=== PAYLOAD DEBUG ===" . PHP_EOL;
                var_dump($payload);
                echo PHP_EOL;

                foreach ($payload as $key => $value) {
                    echo "$key => " . gettype($value) . " => " . var_export($value, true) . PHP_EOL;
                }
                echo "===================" . PHP_EOL;

                $timestamp = date('Ymd_His');
                $unitId = $payload['id'] ?? 'unknown';

                $dir = __DIR__ . '/../../storage/cempro_payloads';

                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $filePath = $dir . "/payload_{$unitId}_{$timestamp}.json";

                file_put_contents(
                    $filePath,
                    json_encode($payload)
                );

                echo "Payload guardado en: {$filePath}" . PHP_EOL;

                print_r(json_encode($payload));

                $payload2 = array(
                    "timestamp" => "2026-02-11T21:54:41.000Z",
                    "id"        => "EQ8109074",
                    "lat" => 89.735367,
                    "lon" => -13.697198,
                    "kmph" => 0,
                    "heading" => 113,
                    "event" => 1,
                    "gps" => true
                );

                $response = $cemproService->sendLocation($payload2);

                // NEXT:
                // $cempro->sendLocation($payload);
                // https://staging.gps.gt//index.php
            }

            $wialonService->logout();
        }

        // Next step:
        // Send to Wialon → Cempro
    }
}
