<?php

namespace Config;

/**
 * Class Wialon
 *
 * Configuración centralizada para construir parámetros de consulta
 * hacia la API de Wialon (svc=core/search_items).
 *
 * Esta clase encapsula los flags y estructuras necesarias para
 * consultar unidades (avl_unit) y usuarios.
 *
 * Se utiliza para mantener consistencia y evitar "magic numbers"
 * dentro del código.
 *
 * @package Config
 */
class Wialon
{
    /**
     * Flag básico del item.
     * Incluye información general como id, nombre y clase.
     *
     * @var int
     */
    public const FLAG_BASIC = 1;

    /**
     * Flag de posición.
     * Incluye el objeto `pos` con latitud, longitud, velocidad, etc.
     *
     * @var int
     */
    public const FLAG_POSITION = 1024;

    /**
     * Flag de parámetros.
     * Incluye el objeto `prms` con parámetros del dispositivo
     * como ignición, batería, velocidad, señal GPS, etc.
     *
     * @var int
     */
    public const FLAG_PARAMS = 4096;

    /**
     * Construye los parámetros para consultar unidades (avl_unit)
     * incluyendo información básica, posición y parámetros del dispositivo.
     *
     * Flags utilizados:
     * - FLAG_BASIC
     * - FLAG_POSITION
     * - FLAG_PARAMS
     *
     * @return array<string, mixed>
     */
    public static function searchItemsWithLocation(): array
    {
        return [
            'spec' => [
                'itemsType'      => 'avl_unit',
                'propName'       => 'sys_id',
                'propValueMask'  => '*',
                'sortType'       => 'sys_id',
                'propType'       => 'list',
                'or_logic'       => '0',
            ],
            'force' => 1,
            'flags' => self::FLAG_BASIC 
                     | self::FLAG_POSITION 
                     | self::FLAG_PARAMS,
            'from'  => 0,
            'to'    => 0,
        ];
    }

    /**
     * Construye los parámetros para consultar usuarios.
     *
     * Solo utiliza FLAG_BASIC (1).
     *
     * @return array<string, mixed>
     */
    public function userList(): array
    {
        return [
            "spec" => [
                "itemsType" => "user",
                "propName" => "sys_name",
                "propValueMask" => "*",
                "sortType" => "sys_name",
                "propType" => "list",
                "or_logic" => "0"
            ],
            "force" => 1,
            "flags" => self::FLAG_BASIC,
            "from" => 0,
            "to" => 0
        ];
    }

    /**
     * Construye los parámetros para consultar un item específico por ID.
     *
     * Usa todos los flags disponibles para obtener la información completa
     * del objeto en Wialon.
     *
     * ⚠️ Debe usarse con precaución en producción debido al tamaño del payload.
     *
     * @param string $id ID del item en Wialon.
     * @return array<string, mixed>
     */
    public static function searchItemById(string $id): array
    {
        return [
            'id'    => $id,
            'flags' => 4611686018427387903,
        ];
    }
}
