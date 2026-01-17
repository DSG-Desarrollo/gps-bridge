<?php
class Params
{
    //Busqueda de uno o muchos articulos de acuerdo a su propiedades
    public function search_item_by_property()
    {
        $itemsType = "avl_unit";
        $propName = "sys_id";
        $propValueMask = "*";
        $sortType = "sys_id";
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

    public function user_list()
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
    public function search_item_by_id($args)
    {
        $arguments = explode('}{', $args);

        $id = $arguments['0'];
        //$flags = $arguments['1'];
        $flags = '4611686018427387903';

        $params = array(
            'id' => $id,
            'flags' => $flags
        );
        return $params;
    }

    public function search_items_with_location()
    {
        $itemsType = "avl_unit";
        $propName = "sys_id";
        $propValueMask = "*";
        $sortType = "sys_id";
        $propType = "list";
        $or_logic = "0";
        $force = 1;
        // Flag 1 (datos básicos) + Flag 1024 (posición/última mensaje)
        $flags = 1025; // 1 + 1024
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
}
