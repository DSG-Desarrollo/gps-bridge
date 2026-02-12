<?php

namespace App\Jobs;

use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\WialonService;
use Config\Wialon;
use App\Services\WialonMapper;
use App\Services\CemproService;

/**
 * Class SyncLocationsJob
 *
 * Job encargado de sincronizar las posiciones de las unidades desde Wialon
 * hacia el servicio externo Cempro.
 *
 * Flujo general:
 * 1. Obtiene los usuarios con integración activa hacia Cempro.
 * 2. Inicia sesión en Wialon utilizando el token de cada usuario.
 * 3. Consulta las unidades con posición actual.
 * 4. Filtra únicamente las unidades registradas en el sistema local.
 * 5. Mapea la estructura de datos Wialon → formato requerido por Cempro.
 * 6. Envía el payload al servicio Cempro.
 * 7. Cierra sesión en Wialon.
 *
 * Este Job está diseñado para ejecutarse mediante cron.
 *
 * @package App\Jobs
 */
class SyncLocationsJob
{
    /**
     * Repositorio de unidades locales
     *
     * @var UnitRepository
     */
    private UnitRepository $units;

    /**
     * Repositorio de usuarios con integración configurada
     *
     * @var UserRepository
     */
    private UserRepository $users;

    /**
     * Constructor.
     *
     * Inicializa los repositorios necesarios para la sincronización.
     */
    public function __construct()
    {
        $this->units = new UnitRepository();
        $this->users = new UserRepository();
    }

    /**
     * Ejecuta el proceso de sincronización de ubicaciones.
     *
     * Este método:
     * - Autentica contra Wialon
     * - Obtiene las posiciones actuales
     * - Filtra las unidades registradas
     * - Mapea los datos al formato Cempro
     * - Envía cada posición al servicio externo
     *
     * El método está diseñado para ser tolerante a fallos:
     * - Si falla el login de un usuario, continúa con el siguiente.
     * - Si falla el envío de una unidad, continúa con las demás.
     *
     * @return void
     */
    public function handle(): void
    {
        $wialonService = new WialonService();
        $cemproService = new CemproService();
        $getParams = Wialon::searchItemsWithLocation();

        $users = $this->users->getUsersByIntegration('cempro');

        foreach ($users as $user) {

            try {
                $wialonService->login($user['wa_token']);
            } catch (\Throwable $e) {
                continue;
            }

            $response = $wialonService->call("core_search_items", $getParams);

            if (
                !isset($response['items']) ||
                empty($response['items'])
            ) {
                $wialonService->logout();
                continue;
            }

            /**
             * Indexación rápida por ID de unidad Wialon
             * para evitar búsquedas anidadas innecesarias.
             *
             * @var array<int, array<string, mixed>> $wialonIndex
             */
            $wialonIndex = [];

            foreach ($response['items'] as $item) {
                $wialonIndex[$item['id']] = $item;
            }

            $units = $this->units->getUnitsByUser($user['id_usuario']);

            foreach ($units as $unit) {

                $waUnitId = $unit['wa_unit_id'];

                if (
                    !isset($wialonIndex[$waUnitId]) ||
                    !isset($wialonIndex[$waUnitId]['pos'])
                ) {
                    continue;
                }

                $item = $wialonIndex[$waUnitId];

                /**
                 * Mapea la estructura de datos de Wialon
                 * al formato requerido por Cempro.
                 *
                 * @var array<string, mixed> $payload
                 */
                $payload = WialonMapper::mapToCempro($unit, $item);

                try {
                    $cemproService->sendLocation($payload);
                } catch (\Throwable $e) {
                    continue;
                }
            }

            $wialonService->logout();
        }
    }
}
