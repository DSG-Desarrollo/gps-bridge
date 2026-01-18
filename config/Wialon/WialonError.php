<?php
namespace Config\Wialon;

/**
 * Clase para manejo de códigos y mensajes de error de Wialon RemoteAPI
 * 
 * @package App\Config\Wialon
 * @author StartrackGPS
 * @license MIT
 */
class WialonError
{
    /**
     * Códigos de error oficiales de Wialon RemoteAPI
     * 
     * @var array<int, string>
     */
    public const ERRORS = [
        1 => 'Invalid session',
        2 => 'Invalid service',
        3 => 'Invalid result',
        4 => 'Invalid input',
        5 => 'Error performing request',
        6 => 'Unknown error',
        7 => 'Access denied',
        8 => 'Invalid user name or password',
        9 => 'Authorization server is unavailable, please try again later',
        1001 => 'No message for selected interval',
        1002 => 'Item with such unique property already exists',
        1003 => 'Only one request of given time is allowed at the moment'
    ];

    /**
     * Obtiene el mensaje de error para un código dado
     * 
     * @param int $code Código de error de Wialon
     * @return string|null Mensaje de error o null si no existe
     */
    public static function getMessage(int $code): ?string
    {
        return self::ERRORS[$code] ?? null;
    }

    /**
     * Formatea un error completo con código y mensaje
     * 
     * @param int $code Código de error
     * @param string $additionalText Texto adicional opcional
     * @return string Error formateado
     */
    public static function format(int $code, string $additionalText = ''): string
    {
        $message = self::getMessage($code) ?? 'Unknown error';
        
        if (!empty($additionalText)) {
            $message .= ' - ' . $additionalText;
        }
        
        return sprintf('WialonError(%d: %s)', $code, $message);
    }

    /**
     * Verifica si un código de error existe
     * 
     * @param int $code Código a verificar
     * @return bool
     */
    public static function exists(int $code): bool
    {
        return isset(self::ERRORS[$code]);
    }

    /**
     * Verifica si una respuesta contiene un error
     * 
     * @param array $response Respuesta de la API
     * @return bool
     */
    public static function hasError(array $response): bool
    {
        return isset($response['error']) && $response['error'] !== 0;
    }

    /**
     * Extrae el código de error de una respuesta
     * 
     * @param array $response Respuesta de la API
     * @return int|null Código de error o null si no hay error
     */
    public static function getCode(array $response): ?int
    {
        if (self::hasError($response)) {
            return (int) $response['error'];
        }
        return null;
    }

    /**
     * Obtiene el mensaje completo de error desde una respuesta
     * 
     * @param array $response Respuesta de la API
     * @return string|null
     */
    public static function getErrorFromResponse(array $response): ?string
    {
        $code = self::getCode($response);
        
        if ($code === null) {
            return null;
        }

        $additionalText = $response['message'] ?? $response['reason'] ?? '';
        
        return self::format($code, $additionalText);
    }

    /**
     * Alias del método format para compatibilidad con código legacy
     * 
     * @deprecated Use format() instead
     */
    public static function error(int $code = 0, string $text = ''): string
    {
        return self::format($code, $text);
    }
}