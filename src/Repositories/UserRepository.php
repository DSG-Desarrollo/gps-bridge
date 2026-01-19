<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Class UserRepository
 *
 * Repositorio responsable de acceder la información de los usuarios.
 * Lista blanca de usuarios con acceso a Wialon.
 *
 * Implementa el patrón Repository para desacoplar la lógica
 * de acceso a datos del resto de la aplicación.
 *
 * @package App\Repositories
 */
class UserRepository
{
    /**
     * Conexión PDO a la base de datos.
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * UserRepository constructor.
     *
     * Inicializa la conexión a la base de datos utilizando
     * el singleton Database.
     */
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** 
     * Obtiene los usuarios de Wialon que cuenta con alguna integración activa.
     *
     * Este método retorna una lista básica de usuarios
     * 
     * @param string $integration Código de integración de Wialon
     * 
     * @return array<int, array<string, mixed>> Lista de usuarios de Wialon
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
