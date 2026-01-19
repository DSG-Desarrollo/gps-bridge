<?php

namespace App\Jobs;

use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\WialonService;
use Config\Wialon;
use App\Services\WialonMapper;

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

                print_r($payload);

                // NEXT:
                // $cempro->sendLocation($payload);
            }

            $wialonService->logout();
        }

        // Next step:
        // Send to Wialon → Cempro
    }
}
