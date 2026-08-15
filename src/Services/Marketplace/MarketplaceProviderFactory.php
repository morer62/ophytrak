<?php
namespace App\Services\Marketplace;

final class MarketplaceProviderFactory
{
    public static function make(string $provider, object $connector): MarketplaceProviderInterface
    {
        return match($provider) {
            'mercadolibre' => new MercadoLibreProvider($connector),
            'tiktok_shop' => new TikTokShopProvider($connector),
            'shopee_br' => new ShopeeProvider($connector),
            default => throw new \InvalidArgumentException('Unsupported marketplace provider.'),
        };
    }
}
