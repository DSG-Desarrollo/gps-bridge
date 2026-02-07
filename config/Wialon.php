<?php

namespace Config;

class Wialon
{
    // Flags constantes para legibilidad
    public const FLAG_BASIC = 1;
    public const FLAG_POSITION = 1024;

    //Busqueda de uno o muchos articulos de acuerdo a su propiedades
    public static function searchItemsWithLocation()
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
            // 1 (basic) + 1024 (position) = 1025
            'flags' => self::FLAG_BASIC | self::FLAG_POSITION,
            'from'  => 0,
            'to'    => 0,
        ];
    }

    public function userList()
    {
        $itemsType = "user";
        $propName = "sys_name";
        $propValueMask = "*";
        $sortType = "sys_name";
        $propType = "list";
        $or_logic = "0";
        $force = 1;
        $flags = 1;
        $from = 0;
        $to = 0;

        $params = array(
            "spec" => array(
                "itemsType" => $itemsType,
                "propName" => $propName,
                "propValueMask" => $propValueMask,
                "sortType" => $sortType,
                "propType" => $propType,
                "or_logic" => $or_logic
            ),
            "force" => $force,
            "flags" => $flags,
            "from" => $from,
            "to" => $to
        );
        return $params;
    }

    //Busqueda de articulo(items) por id
    public static function searchItemById(string $id)
    {
        return [
            'id'    => $id,
            'flags' => '4611686018427387903',
        ];
    }
}
