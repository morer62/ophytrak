<?php
namespace App\Services\Marketplace;

use App\Repositories\MarketplaceConnectorsRepository;

final class MarketplaceTokenRefreshService
{
    public function __construct(
        private ?MarketplaceConnectorsRepository $repository = null,
        private ?MarketplaceHttpClient $http = null
    ) {
        $this->repository ??= new MarketplaceConnectorsRepository();
        $this->http ??= new MarketplaceHttpClient();
    }

    public function refresh(int $ownerId, string $provider): array
    {
        $connector = $this->repository->getCredentialsForSync($ownerId, $provider);
        if (!$connector || trim((string)($connector->refresh_token ?? '')) === '') {
            throw new \RuntimeException('The marketplace must be authorized again because no refresh token is available.');
        }

        $token = match ($provider) {
            'mercadolibre' => $this->mercadoLibre((string)$connector->refresh_token),
            'shopee_br' => $this->shopee($connector),
            'tiktok_shop' => $this->tikTok((string)$connector->refresh_token),
            default => throw new \InvalidArgumentException('Unsupported marketplace provider.'),
        };

        if (!$this->repository->saveAuthorization($ownerId, $provider, $token)) {
            throw new \RuntimeException('The renewed marketplace token could not be saved.');
        }

        return $token;
    }

    private function mercadoLibre(string $refreshToken): array
    {
        $payload = http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => (string)($_ENV['MERCADOLIBRE_CLIENT_ID'] ?? ''),
            'client_secret' => (string)($_ENV['MERCADOLIBRE_CLIENT_SECRET'] ?? ''),
            'refresh_token' => $refreshToken,
        ]);
        $result = $this->rawRequest('POST', 'https://api.mercadolibre.com/oauth/token',
            ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'], $payload);
        return $result['data'];
    }

    private function shopee(object $connector): array
    {
        $partnerId = (int)($_ENV['SHOPEE_PARTNER_ID'] ?? 0);
        $partnerKey = (string)($_ENV['SHOPEE_PARTNER_KEY'] ?? '');
        $shopId = (int)($connector->external_shop_id ?? $connector->account_id ?? 0);
        if ($partnerId < 1 || $partnerKey === '' || $shopId < 1) {
            throw new \RuntimeException('Shopee partner credentials and shop ID are required.');
        }
        $path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $sign = hash_hmac('sha256', $partnerId . $path . $timestamp, $partnerKey);
        $base = rtrim((string)($_ENV['SHOPEE_API_BASE'] ?? 'https://partner.shopeemobile.com'), '/');
        $url = $base . $path . '?' . http_build_query([
            'partner_id' => $partnerId,
            'timestamp' => $timestamp,
            'sign' => $sign,
        ]);
        $result = $this->http->request('POST', $url, ['Content-Type: application/json'], [
            'refresh_token' => (string)$connector->refresh_token,
            'partner_id' => $partnerId,
            'shop_id' => $shopId,
        ]);
        return (array)($result['data']['response'] ?? $result['data']);
    }

    private function tikTok(string $refreshToken): array
    {
        $query = http_build_query([
            'app_key' => (string)($_ENV['TIKTOK_SHOP_APP_KEY'] ?? ''),
            'app_secret' => (string)($_ENV['TIKTOK_SHOP_APP_SECRET'] ?? ''),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        $result = $this->http->request('GET', 'https://auth.tiktok-shops.com/api/v2/token/refresh?' . $query);
        return (array)($result['data']['data'] ?? $result['data']);
    }

    private function rawRequest(string $method, string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 30]);
        $raw = curl_exec($ch); $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $json = json_decode((string)$raw, true);
        if ($raw === false || $status < 200 || $status >= 300 || !is_array($json)) {
            $message = is_array($json) ? (string)($json['message'] ?? $json['error_description'] ?? $json['error'] ?? '') : $error;
            throw new \RuntimeException('Marketplace token refresh failed' . ($message !== '' ? ': ' . $message : '.'));
        }
        return ['status' => $status, 'data' => $json['data'] ?? $json];
    }
}
