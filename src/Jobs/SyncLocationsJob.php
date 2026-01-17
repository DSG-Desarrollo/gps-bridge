<?php

namespace App\Jobs;

use App\Repositories\UnitRepository;

class SyncLocationsJob
{
    private UnitRepository $units;

    public function __construct()
    {
        $this->units = new UnitRepository();
    }

    public function handle()
    {
        $units = $this->units->getActiveUnits();

        echo "Units loaded: " . count($units) . PHP_EOL;

        // Next step:
        // Send to Wialon → Cempro
    }
}
