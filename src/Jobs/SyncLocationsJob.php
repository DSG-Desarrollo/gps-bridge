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

                print_r($position);
            }
        }

        // Next step:
        // Send to Wialon → Cempro
    }
}
