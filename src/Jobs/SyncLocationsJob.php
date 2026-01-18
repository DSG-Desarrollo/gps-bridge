<?php

namespace App\Jobs;

use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\WialonService;
use Config\Wialon;

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

        print_r($getParams);

        foreach ($users as $user) {

            echo "Login Wialon: " . $user['wa_cuenta'] . PHP_EOL;

            $login = $wialonService->login($user['wa_token']);
            echo "Login result: " . $login . PHP_EOL;

            if (!$login) {
                echo "Login failed" . PHP_EOL;
                continue;
            }

            echo "User: " . $user['wa_token'] . PHP_EOL;
            $units = $this->units->getUnitsByUser($user['id_usuario']);
            echo "Units: " . count($units) . PHP_EOL;

            foreach ($units as $unit) {
                $position = $wialonService->call("core_search_items", $getParams);
                echo "Unit: " . $unit['wa_unit_id'] . PHP_EOL;


                if (!$position) {
                    continue;
                }

                print_r($position);
            }
        }
        //$units = $this->units->getActiveUnits();

        //echo "Units loaded: " . count($units) . PHP_EOL;

        // Next step:
        // Send to Wialon → Cempro
    }
}
