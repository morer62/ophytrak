<?php
namespace App\Services\Marketplace;

final class MarketplaceHttpClient
{
    public function request(string $method, string $url, array $headers = [], ?array $body = null): array
    {
        $ch = curl_init($url);
        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line); $parts = explode(':', $line, 2);
                if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                return $length;
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) throw new \RuntimeException('Marketplace network error: ' . $error);
        $json = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($json) ? (string)($json['message'] ?? $json['error_description'] ?? $json['error'] ?? '') : '';
            throw new \RuntimeException('Marketplace HTTP ' . $status . ($message !== '' ? ': ' . $message : ''));
        }
        if (!is_array($json)) throw new \RuntimeException('Marketplace returned invalid JSON.');
        return ['status' => $status, 'headers' => $responseHeaders, 'data' => $json];
    }
}
