<?php

namespace App\Services;

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

    public function login(string $token): bool
    {
        $payload = [
            'token' => $token
        ];

        $response = $this->call('token/login', $payload);

        if (!isset($response['eid'])) {
            return false;
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

    private function call(string $action, array $params): array
    {
        $svc = str_replace('_', '/', $action);

        $payload = array_merge($this->defaultParams, [
            'svc' => $svc,
            'params' => json_encode($params),
            'sid' => $this->sid
        ]);

        $query = http_build_query($payload);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseApiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $query,
            CURLOPT_TIMEOUT => 30
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            throw new \Exception('Wialon CURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        $decoded = json_decode($result, true);

        if (!$decoded) {
            throw new \Exception('Invalid Wialon JSON response');
        }

        return $decoded;
    }

    // --------------------
    // BUSINESS METHODS
    // --------------------

    /**
     * Get unit last position
     */
    public function getUnitLocation(int $unitId): ?array
    {
        $params = [
            'spec' => [
                'itemsType' => 'avl_unit',
                'propName' => 'sys_id',
                'propValueMask' => $unitId,
                'sortType' => 'sys_id'
            ],
            'force' => 1,
            'flags' => 1025,
            'from' => 0,
            'to' => 0
        ];

        $response = $this->call('core/search_items', $params);

        if (
            !isset($response['items'][0]['pos'])
        ) {
            return null;
        }

        return $response['items'][0]['pos'];
    }
}
