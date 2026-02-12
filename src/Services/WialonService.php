<?php

namespace App\Services;

use Config\Wialon\WialonError;

/**
 * Class WialonService
 *
 * Servicio encargado de comunicarse con la API de Wialon.
 *
 * Responsabilidades:
 * - Autenticación mediante token.
 * - Manejo de sesión (SID).
 * - Construcción dinámica de servicios (svc).
 * - Ejecución de peticiones HTTP POST.
 * - Registro de respuestas en almacenamiento local para auditoría/debug.
 *
 * Esta clase encapsula la lógica de comunicación con
 * el endpoint /wialon/ajax.html.
 *
 * @package App\Services
 */
class WialonService
{
    /**
     * Session ID (SID) retornado por Wialon tras autenticación.
     *
     * @var string
     */
    private string $sid = '';

    /**
     * URL base de la API Wialon.
     *
     * @var string
     */
    private string $baseApiUrl;

    /**
     * Parámetros por defecto adicionales que pueden enviarse en cada request.
     *
     * @var array<string, mixed>
     */
    private array $defaultParams = [];

    /**
     * Constructor.
     *
     * Construye la URL base del endpoint Wialon.
     *
     * @param string $host   Host de la API Wialon.
     * @param string $scheme Protocolo (http o https).
     *
     * @return void
     */
    public function __construct(
        string $host = 'hst-api.wialon.com',
        string $scheme = 'https'
    ) {
        $this->baseApiUrl = sprintf(
            '%s://%s/wialon/ajax.html?',
            $scheme,
            $host
        );
    }

    // --------------------
    // AUTH
    // --------------------

    /**
     * Inicia sesión en Wialon utilizando un token.
     *
     * Guarda internamente el SID retornado por la API.
     *
     * @param string $token Token de autenticación Wialon.
     *
     * @return bool True si el login fue exitoso.
     *
     * @throws \Exception Si ocurre error en autenticación
     *                    o no se recibe session ID.
     */
    public function login(string $token): bool
    {
        $payload = ['token' => $token];

        $response = $this->call('token/login', $payload);

        if (WialonError::hasError($response)) {
            $errorMsg = WialonError::getErrorFromResponse($response);
            throw new \Exception("Login failed: {$errorMsg}");
        }

        if (!isset($response['eid'])) {
            throw new \Exception(
                "Login failed: No session ID received"
            );
        }

        $this->sid = $response['eid'];

        return true;
    }

    /**
     * Cierra la sesión activa en Wialon.
     *
     * Si no existe sesión activa, no realiza ninguna acción.
     *
     * @return void
     */
    public function logout(): void
    {
        if (!$this->sid) {
            return;
        }

        $this->call('core/logout', []);

        $this->sid = '';
    }

    // --------------------
    // MAIN REQUEST
    // --------------------

    /**
     * Ejecuta una llamada a la API de Wialon.
     *
     * Flujo interno:
     * 1. Transforma el nombre de acción en formato "svc".
     * 2. Construye parámetros requeridos por Wialon.
     * 3. Genera query string manualmente.
     * 4. Ejecuta petición HTTP POST mediante cURL.
     * 5. Decodifica respuesta JSON.
     * 6. Guarda respuesta en storage para auditoría.
     *
     * Transformación del servicio (svc):
     * - Reemplaza el primer "_" por "/".
     * - Caso especial: acciones que inician con "unit_group".
     *
     * @param string $action Nombre lógico de la acción (ej: core_search_items).
     * @param array<string, mixed> $params Parámetros específicos del servicio.
     *
     * @return array<string, mixed> Respuesta decodificada.
     *         Si ocurre error cURL o JSON inválido, retorna estructura con:
     *         [
     *           'error' => int,
     *           'message' => string
     *         ]
     */
    public function call(string $action, array $params): array
    {
        // Construcción del servicio (svc)
        if (stripos($action, 'unit_group') === 0) {
            $svc = $action;
            $svc[strlen('unit_group')] = '/';
        } else {
            $svc = preg_replace('/_/', '/', $action, 1);
        }

        $allParams = array_replace($this->defaultParams, [
            'svc'    => $svc,
            'params' => json_encode($params),
            'sid'    => $this->sid
        ]);

        $queryString = '';
        foreach ($allParams as $k => $v) {
            if (strlen($queryString) > 0) {
                $queryString .= '&';
            }

            $encoded = is_object($v) || is_array($v)
                ? json_encode($v)
                : $v;

            $queryString .= $k . '=' . urlencode($encoded);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseApiUrl,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => $queryString
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);

            return [
                'error'   => -1,
                'message' => $error
            ];
        }

        curl_close($ch);

        $decoded = json_decode($result, true);

        // Guardar respuesta en storage para auditoría
        $dir = __DIR__ . '/../../storage/wialon_responses';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $safeAction = str_replace(['/', '\\'], '_', $action);

        file_put_contents(
            $dir . "/{$safeAction}_{$this->sid}_" . date('Ymd_His') . ".json",
            json_encode($decoded, JSON_PRETTY_PRINT)
        );

        if ($decoded === null) {
            return [
                'error'   => -1,
                'message' => 'Invalid JSON response'
            ];
        }

        return $decoded;
    }
}
