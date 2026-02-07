<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Class UnitRepository
 *
 * Repositorio responsable de acceder a los datos relacionados
 * con las unidades del sistema.
 *
 * Implementa el patrón Repository para desacoplar la lógica
 * de acceso a datos del resto de la aplicación.
 *
 * @package App\Repositories
 */
class UnitRepository
{
    /**
     * Conexión PDO a la base de datos.
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * UnitRepository constructor.
     *
     * Inicializa la conexión a la base de datos utilizando
     * el singleton Database.
     */
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Obtiene todas las unidades activas del sistema.
     *
     * Este método retorna una lista básica de unidades
     * sin aplicar filtros por usuario.
     *
     * @return array<int, array<string, mixed>> Lista de unidades activas
     */
    public function getActiveUnits(): array
    {
        $sql = "SELECT id_unidad, wa_unit_id, wa_name FROM unidades";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Obtiene las unidades activas asociadas a un usuario específico.
     *
     * Filtra las unidades por el identificador del usuario y
     * por estado activo (`estado_unidad = 'A'`).
     *
     * @param int $userId Identificador único del usuario
     *
     * @return array<int, array<string, mixed>> Lista de unidades del usuario
     */
    public function getUnitsByUser(int $userId): array
    {
        $sql = "
            SELECT id_unidad, wa_unit_id, remote_id
            FROM unidades
            WHERE id_usuario = ? AND estado_unidad = 'A'
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }
}
