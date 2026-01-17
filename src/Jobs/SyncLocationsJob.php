<?php

namespace App\Jobs;

use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\WialonService;

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
        $users = $this->users->getUsersByIntegration('cempro');
        foreach ($users as $user) {
            echo "User: " . $user['wa_token'] . PHP_EOL;
            $units = $this->units->getUnitsByUser($user['id_usuario']);
            echo "Units: " . count($units) . PHP_EOL;
        }
        $units = $this->units->getActiveUnits();

        echo "Units loaded: " . count($units) . PHP_EOL;

        // Next step:
        // Send to Wialon → Cempro
    }
}
