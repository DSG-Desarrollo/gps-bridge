<?php

namespace App;

/**
 * Clase de ejemplo para probar phpDocumentor
 *
 * Esta clase representa el "Hola Mundo" de la documentación.
 *
 * @package App
 */
class HelloWorld
{
    /**
     * Retorna un mensaje de saludo
     *
     * @return string Mensaje "Hola Mundo"
     */
    public function sayHello(): string
    {
        return 'Hola Mundo desde phpDocumentor';
    }
}
