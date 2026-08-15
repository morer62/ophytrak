<?php

namespace App\Services\Serp;

use Exception;

class DataForSEOSerpProvider implements SERPProviderInterface
{
    private string $baseUrl = 'https://api.dataforseo.com';
    private string $login;
    private string $password;
    private string $taskPostEndpoint = '/v3/serp/google/organic/task_post';

    public function __construct()
    {
        $this->login = trim((string)($_ENV['DATAFORSEO_LOGIN'] ?? ''));
        $this->password = trim((string)($_ENV['DATAFORSEO_PASSWORD'] ?? ''));

        if ($this->login === '' || $this->password === '') {
            throw new Exception('DataForSEO credentials are not configured.');
        }
    }

    public function createTask(array $input): array
    {
        $payload = [[
            'keyword' => (string)$input['keyword'],
            'location_name' => (string)$input['location_name'],
            'language_code' => (string)($input['language_code'] ?? 'en'),
            'device' => (string)($input['device'] ?? 'desktop'),
            'depth' => (int)($input['depth'] ?? 5),
        ]];

        return $this->request('POST', $this->taskPostEndpoint, $payload);
    }

    public function fetchTask(string $taskId): array
    {
        return $this->request('GET', '/v3/serp/google/organic/task_get/advanced/' . rawurlencode($taskId));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is required for DataForSEO SERP.');
        }

        $ch = curl_init($this->baseUrl . $path);
        $headers = ['Content-Type: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->login . ':' . $this->password,
            CURLOPT_TIMEOUT => 45,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('DataForSEO request failed: ' . $this->sanitize($error));
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            throw new Exception('DataForSEO returned invalid JSON with HTTP ' . $status . '.');
        }

        $apiStatusCode = (int)($decoded['status_code'] ?? 0);
        if ($status >= 400 || $apiStatusCode >= 40000) {
            $apiMessage = $this->sanitize((string)($decoded['status_message'] ?? 'HTTP ' . $status));
            if ($status === 401 || $apiStatusCode === 40100) {
                throw new Exception(
                    'DataForSEO credentials are present but not authorized. Verify API Access credentials and account verification in DataForSEO. '
                    . $this->safeDiagnostic($path, $status, $apiMessage)
                );
            }

            throw new Exception('DataForSEO error: ' . $apiMessage . ' ' . $this->safeDiagnostic($path, $status, $apiMessage));
        }

        return $decoded;
    }

    private function sanitize(string $message): string
    {
        $message = str_replace($this->password, '[redacted]', $message);
        $message = preg_replace('/Authorization:\\s*Basic\\s+[^\\s]+/i', 'Authorization: Basic [redacted]', $message);

        return trim((string)$message);
    }

    private function safeDiagnostic(string $path, int $status, string $message): string
    {
        return sprintf(
            'Diagnostic: login_present=%s; password_present=%s; endpoint=%s; status_code=%d; response_error_message=%s',
            $this->login !== '' ? 'true' : 'false',
            $this->password !== '' ? 'true' : 'false',
            $this->baseUrl . $path,
            $status,
            $this->sanitize($message)
        );
    }
}
