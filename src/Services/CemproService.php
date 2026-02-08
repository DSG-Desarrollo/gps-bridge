<?php

namespace App\Services;

class CemproService
{
    private string $host;
    private string $apiKey;
    private string $password;

    public function __construct()
    {
        $this->host     = rtrim($_ENV['HOSTNAME_API'], '/');
        $this->apiKey   = $_ENV['API_KEY'];
        $this->password = $_ENV['PASSWORD_API'];
    }

    private function log(string $message)
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
     * ✅ Validar payload antes de enviar
     */
    private function validatePayload(array $payload): void
    {
        $required = ['timestamp', 'id', 'lat', 'lon', 'kmph', 'heading', 'event', 'gps'];
        
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }

        // Validar tipos
        if (!is_string($payload['timestamp'])) {
            throw new \InvalidArgumentException("timestamp must be string");
        }
        if (!is_string($payload['id'])) {
            throw new \InvalidArgumentException("id must be string");
        }
        if (!is_numeric($payload['lat']) || !is_numeric($payload['lon'])) {
            throw new \InvalidArgumentException("lat/lon must be numeric");
        }
        if (!is_numeric($payload['kmph']) || !is_numeric($payload['heading'])) {
            throw new \InvalidArgumentException("kmph/heading must be numeric");
        }
        if (!is_int($payload['event'])) {
            throw new \InvalidArgumentException("event must be integer");
        }
        if (!is_bool($payload['gps'])) {
            throw new \InvalidArgumentException("gps must be boolean");
        }

        // Validar formato timestamp ISO 8601
        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $payload['timestamp']);
        if (!$dt) {
            throw new \InvalidArgumentException("timestamp must be ISO 8601 format");
        }
    }

    public function sendLocation(array $payload)
    {
        // ✅ Validar antes de enviar
        $this->validatePayload($payload);

        $url = $this->host . '/point';
        
        // ✅ Usar JSON_PRESERVE_ZERO_FRACTION para mantener .0 en números
        $jsonPayload = json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES);
        
        if ($jsonPayload === false) {
            throw new \RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }

        $this->log('REQUEST URL: ' . $url);
        $this->log('REQUEST PAYLOAD: ' . $jsonPayload);
        
        echo "Sending payload: " . $jsonPayload . PHP_EOL;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json', // ✅ Agregar charset
            ],
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->apiKey . ':' . $this->password,
            CURLOPT_POSTFIELDS => $jsonPayload, // ✅ Usar string JSON directamente
            CURLOPT_TIMEOUT => 20,
            CURLOPT_ENCODING => '', // ✅ Permitir compresión
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->log('CURL ERROR: ' . $error);
            throw new \Exception('Cempro CURL error: ' . $error);
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
            $this->log('JSON DECODE ERROR: ' . json_last_error_msg());
            throw new \RuntimeException('Failed to decode response: ' . json_last_error_msg());
        }

        return $decoded;
    }
}