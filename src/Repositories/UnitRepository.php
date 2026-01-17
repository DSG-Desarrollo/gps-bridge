<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UnitRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getActiveUnits(): array
    {
        $sql = "SELECT id_unidad, wa_unit_id, wa_name FROM unidades";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
