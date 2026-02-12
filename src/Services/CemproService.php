<?php

namespace App\Services;

/**
 * Class CemproService
 *
 * Servicio encargado de enviar posiciones GPS al sistema externo Cempro.
 *
 * Responsabilidades:
 * - Construir la petición HTTP hacia el endpoint `/point`.
 * - Autenticarse mediante Basic Authentication.
 * - Enviar el payload en formato JSON.
 * - Registrar logs de request y response.
 * - Validar código HTTP.
 * - Decodificar la respuesta JSON.
 *
 * Variables de entorno requeridas:
 * - HOSTNAME_API
 * - API_KEY
 * - PASSWORD_API
 *
 * @package App\Services
 */
class CemproService
{
    /**
     * URL base del servicio Cempro.
     *
     * @var string
     */
    private string $host;

    /**
     * API Key utilizada para autenticación Basic Auth.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Password utilizada para autenticación Basic Auth.
     *
     * @var string
     */
    private string $password;

    /**
     * Constructor.
     *
     * Inicializa las credenciales y el host desde variables de entorno.
     *
     * @return void
     */
    public function __construct()
    {
        $this->host     = rtrim($_ENV['HOSTNAME_API'], '/');
        $this->apiKey   = $_ENV['API_KEY'];
        $this->password = $_ENV['PASSWORD_API'];
    }

    /**
     * Registra un mensaje en el archivo de logs del servicio.
     *
     * Si el directorio no existe, se crea automáticamente.
     *
     * @param string $message Mensaje a registrar.
     *
     * @return void
     */
    private function log(string $message): void
    {
        $logDir  = __DIR__ . '/../../storage/logs';
        $logFile = $logDir . '/cempro.log';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $date = date('Y-m-d H:i:s');

        file_put_contents(
            $logFile,
            "[$date] $message" . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Envía una ubicación GPS al servicio Cempro.
     *
     * Flujo del método:
     * 1. Construye el endpoint `/point`.
     * 2. Codifica el payload a JSON.
     * 3. Registra logs de request.
     * 4. Realiza petición HTTP POST con autenticación Basic.
     * 5. Valida errores cURL.
     * 6. Valida código HTTP (debe ser 200).
     * 7. Decodifica la respuesta JSON.
     *
     * @param array<string, mixed> $payload Datos de ubicación
     *        en el formato requerido por Cempro.
     *
     * @return array<string, mixed> Respuesta decodificada del servicio.
     *
     * @throws \RuntimeException Si falla la codificación o decodificación JSON.
     * @throws \Exception Si ocurre error en cURL o el HTTP code no es 200.
     */
    public function sendLocation(array $payload)
    {
        $hostname = $this->host . '/point';

        // Usar JSON_PRESERVE_ZERO_FRACTION para mantener .0 en números
        $jsonPayload = json_encode(
            $payload,
            JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES
        );

        if ($jsonPayload === false) {
            throw new \RuntimeException(
                'JSON encoding failed: ' . json_last_error_msg()
            );
        }

        $this->log('REQUEST URL: ' . $hostname);
        $this->log('REQUEST PAYLOAD: ' . $jsonPayload);

        $json_params = json_encode($payload);

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Content-Length: ' . strlen($jsonPayload)
];

        echo "Sending payload: " . $json_params . PHP_EOL;

        $ch = curl_init($hostname);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ':' . $this->password);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            $this->log('CURL ERROR: ' . $error);

            throw new \Exception(
                'Cempro CURL error: ' . $error
            );
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->log('HTTP CODE: ' . $httpCode);
        $this->log('RESPONSE RAW: ' . $response);

        if ($httpCode !== 200) {
            throw new \Exception(
                'Cempro HTTP error ' . $httpCode . ' => ' . $response
            );
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log(
                'JSON DECODE ERROR: ' . json_last_error_msg()
            );

            throw new \RuntimeException(
                'Failed to decode response: ' . json_last_error_msg()
            );
        }

        return $decoded;
    }
}
