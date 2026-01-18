<?php

namespace App\Services;

use Config\Wialon\WialonError;

class WialonService
{
    private string $sid = '';
    private string $baseApiUrl;
    private array $defaultParams = [];

    public function __construct(
        string $host = 'hst-api.wialon.com',
        string $scheme = 'https'
    ) {
        $this->baseApiUrl = sprintf('%s://%s/wialon/ajax.html?', $scheme, $host);
    }

    // --------------------
    // AUTH
    // --------------------

    /**
     * Inicia sesión con un token de Wialon
     * 
     * @param string $token Token de autenticación
     * @return bool True si el login fue exitoso
     * @throws \Exception Si hay error en el login
     */
    public function login(string $token): bool
    {
        $payload = ['token' => $token];

        $response = $this->call('token/login', $payload);

        // Verificar si hay error
        if (WialonError::hasError($response)) {
            $errorMsg = WialonError::getErrorFromResponse($response);
            throw new \Exception("Login failed: {$errorMsg}");
        }

        if (!isset($response['eid'])) {
            throw new \Exception("Login failed: No session ID received");
        }

        $this->sid = $response['eid'];

        return true;
    }

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

    public function call(string $action, array $params): array
    {
        // Lógica EXACTA del código original para construir el servicio
        if (stripos($action, 'unit_group') === 0) {
            $svc = $action;
            $svc[strlen('unit_group')] = '/';
        } else {
            // Solo reemplaza el PRIMER underscore
            $svc = preg_replace('/_/', '/', $action, 1);
        }

        // Preparar parámetros
        $allParams = array_replace($this->defaultParams, [
            'svc' => $svc,
            'params' => json_encode($params),
            'sid' => $this->sid
        ]);

        // Construir query string (igual que el original)
        $queryString = '';
        foreach ($allParams as $k => $v) {
            if (strlen($queryString) > 0) {
                $queryString .= '&';
            }
            $encoded = is_object($v) || is_array($v) ? json_encode($v) : $v;
            $queryString .= $k . '=' . urlencode($encoded);
        }

        // cURL request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseApiUrl,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $queryString
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => -1, 'message' => $error];
        }

        curl_close($ch);

        $decoded = json_decode($result, true);

        if ($decoded === null) {
            return ['error' => -1, 'message' => 'Invalid JSON response'];
        }

        return $decoded;
    }
}
