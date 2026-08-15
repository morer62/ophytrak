<?php

namespace App\Services;

class RecaptchaEnterpriseService
{
    public function isConfigured(): bool
    {
        return $this->siteKey() !== '';
    }

    public function siteKey(): string
    {
        return trim((string)($_ENV['RECAPTCHA_ENTERPRISE_SITE_KEY'] ?? ''));
    }

    public function expectedAction(): string
    {
        return trim((string)($_ENV['RECAPTCHA_ENTERPRISE_EXPECTED_ACTION'] ?? 'SIGNUP')) ?: 'SIGNUP';
    }

    public function minScore(): float
    {
        $score = (float)($_ENV['RECAPTCHA_ENTERPRISE_MIN_SCORE'] ?? 0.5);
        return max(0.0, min(1.0, $score));
    }

    public function verifySignupToken(?string $token, ?string $remoteIp = null): array
    {
        return $this->verify($token, $this->expectedAction(), $remoteIp);
    }

    public function verify(?string $token, string $expectedAction, ?string $remoteIp = null): array
    {
        $token = trim((string)$token);
        $siteKey = $this->siteKey();

        if ($siteKey === '') {
            return ['success' => false, 'reason' => 'missing_site_key'];
        }

        if ($token === '') {
            return ['success' => false, 'reason' => 'missing_token'];
        }

        $enterprise = $this->verifyWithEnterpriseAssessment($token, $siteKey, $expectedAction, $remoteIp);
        if ($enterprise !== null) {
            return $enterprise;
        }

        return $this->verifyWithSiteVerify($token, $expectedAction, $remoteIp);
    }

    private function verifyWithEnterpriseAssessment(string $token, string $siteKey, string $expectedAction, ?string $remoteIp): ?array
    {
        $projectId = trim((string)($_ENV['RECAPTCHA_ENTERPRISE_PROJECT_ID'] ?? ''));
        $apiKey = trim((string)($_ENV['RECAPTCHA_ENTERPRISE_API_KEY'] ?? ''));

        if ($projectId === '' || $apiKey === '') {
            return null;
        }

        $payload = [
            'event' => array_filter([
                'token' => $token,
                'siteKey' => $siteKey,
                'expectedAction' => $expectedAction,
                'userIpAddress' => $remoteIp,
            ], static fn($value): bool => $value !== null && $value !== ''),
        ];

        $response = $this->postJson(
            'https://recaptchaenterprise.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/assessments?key=' . rawurlencode($apiKey),
            $payload
        );

        if (!$response['ok']) {
            error_log('RecaptchaEnterpriseService assessment failed: ' . ($response['error'] ?? 'unknown'));
            return ['success' => false, 'reason' => 'assessment_request_failed'];
        }

        $data = $response['data'];
        $tokenProperties = $data['tokenProperties'] ?? [];
        $riskAnalysis = $data['riskAnalysis'] ?? [];
        $valid = (bool)($tokenProperties['valid'] ?? false);
        $action = (string)($tokenProperties['action'] ?? '');
        $score = isset($riskAnalysis['score']) ? (float)$riskAnalysis['score'] : 0.0;

        if (!$valid) {
            return ['success' => false, 'reason' => 'invalid_token', 'score' => $score];
        }

        if ($action !== $expectedAction) {
            return ['success' => false, 'reason' => 'action_mismatch', 'score' => $score];
        }

        if ($score < $this->minScore()) {
            return ['success' => false, 'reason' => 'low_score', 'score' => $score];
        }

        return ['success' => true, 'reason' => 'verified', 'score' => $score];
    }

    private function verifyWithSiteVerify(string $token, string $expectedAction, ?string $remoteIp): array
    {
        $secret = trim((string)($_ENV['RECAPTCHA_SECRET_KEY'] ?? $_ENV['RECAPTCHA_ENTERPRISE_SECRET_KEY'] ?? ''));
        if ($secret === '') {
            return ['success' => false, 'reason' => 'missing_server_credentials'];
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];
        if ($remoteIp) {
            $payload['remoteip'] = $remoteIp;
        }

        $response = $this->postForm('https://www.google.com/recaptcha/api/siteverify', $payload);
        if (!$response['ok']) {
            error_log('RecaptchaEnterpriseService siteverify failed: ' . ($response['error'] ?? 'unknown'));
            return ['success' => false, 'reason' => 'siteverify_request_failed'];
        }

        $data = $response['data'];
        $success = (bool)($data['success'] ?? false);
        $action = (string)($data['action'] ?? $expectedAction);
        $score = isset($data['score']) ? (float)$data['score'] : 1.0;

        if (!$success) {
            return ['success' => false, 'reason' => 'invalid_token', 'score' => $score];
        }

        if ($action !== $expectedAction) {
            return ['success' => false, 'reason' => 'action_mismatch', 'score' => $score];
        }

        if ($score < $this->minScore()) {
            return ['success' => false, 'reason' => 'low_score', 'score' => $score];
        }

        return ['success' => true, 'reason' => 'verified', 'score' => $score];
    }

    private function postJson(string $url, array $payload): array
    {
        return $this->curlPost($url, json_encode($payload), [
            'Content-Type: application/json',
        ]);
    }

    private function postForm(string $url, array $payload): array
    {
        return $this->curlPost($url, http_build_query($payload), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    }

    private function curlPost(string $url, string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl_extension_missing'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => $error ?: ('http_' . $status)];
        }

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }

        return ['ok' => true, 'data' => $data];
    }
}
