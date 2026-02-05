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
        $this->apiKey  = $_ENV['API_KEY'];
        $this->password = $_ENV['PASSWORD_API'];
    }

    private function log(string $message): void
    {
        $logFile = __DIR__ . '/../../storage/logs/cempro.log';

        $date = date('Y-m-d H:i:s');
        file_put_contents(
            $logFile,
            "[$date] $message" . PHP_EOL,
            FILE_APPEND
        );
    }

    public function sendLocation(array $payload): array
    {
        $url = $this->host . '/point';
        echo "URL: " . $url . PHP_EOL;
        echo "API Key: " . $this->apiKey . PHP_EOL;
        echo "Password: " . str_repeat('*', strlen($this->password)) . PHP_EOL;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],

            // BASIC AUTH: api_key:password
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->apiKey . ':' . $this->password,

            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 20,
            // CURLOPT_VERBOSE => true,

        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new \Exception(
                'Cempro CURL error: ' . curl_error($ch)
            );
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpCode !== 200) {
            throw new \Exception(
                'Cempro HTTP error ' . $httpCode . ' => ' . $response
            );
        }

if ($response === false) {
    $this->log('CURL ERROR: ' . curl_error($ch));
    throw new \Exception(
        'Cempro CURL error: ' . curl_error($ch)
    );
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$this->log('REQUEST URL: ' . $url);
$this->log('REQUEST PAYLOAD: ' . json_encode($payload));
$this->log('HTTP CODE: ' . $httpCode);
$this->log('RESPONSE RAW: ' . $response);

        return $decoded;
    }
}
