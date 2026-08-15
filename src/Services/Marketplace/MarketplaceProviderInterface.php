<?php
namespace App\Services\Marketplace;

interface MarketplaceProviderInterface
{
    public function provider(): string;
    public function testConnection(): array;
    public function fetchProducts(?string $cursor = null): array;
    public function fetchOrders(?string $cursor = null): array;
}
