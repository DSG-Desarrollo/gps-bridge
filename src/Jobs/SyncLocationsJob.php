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

            $units = $this->units->getUnitsByUser($user['id_usuario']);

            foreach ($units as $unit) {
                $position = $wialonService->call("core_search_items", $getParams);

                if (!$position) {
                    continue;
                }

                foreach ($position['items'] as $item) {
                    $payload = WialonMapper::mapToCempro($unit, $item);
                    print_r($payload);
                }
                //print_r($position);

                /*$payload = WialonMapper::mapToCempro($unit, $position);
                print_r($payload);*/

                //$result = json_decode($position, true);
                //print_r($position['items']);
            }
        }

        // Next step:
        // Send to Wialon → Cempro
    }
}
