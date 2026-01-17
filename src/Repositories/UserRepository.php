<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** 
     * Obtiene los usuarios de Wialon
    */
    public function getUsersByIntegration(string $integration): array
    {
        $sql = "
            SELECT u.id_usuario, u.wa_token, u.wa_cuenta
            FROM usuario u
            INNER JOIN integration_clients ic 
              ON ic.id_usuario = u.id_usuario
            WHERE ic.integration_code = ?
              AND ic.active = 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$integration]);

        return $stmt->fetchAll();
    }
}
