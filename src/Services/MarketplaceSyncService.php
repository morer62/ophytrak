<?php

namespace App\Services;

use App\Repositories\Connection;
use App\Repositories\MarketplaceConnectorsRepository;
use PDOException;
use App\Services\Marketplace\MarketplaceImportService;
use App\Services\Marketplace\MarketplaceProviderFactory;

class MarketplaceSyncService
{
    public const PROVIDERS = [
        'shopee_br' => 'Shopee Brasil',
        'mercadolibre' => 'Mercado Libre',
        'tiktok_shop' => 'TikTok Shop',
    ];

    public const INTERNAL_STATUSES = [
        'pending_payment',
        'paid',
        'received',
        'preparing',
        'packed',
        'ready_to_ship',
        'shipped',
        'out_for_delivery',
        'delivered',
        'completed',
        'cancelled',
        'refunded',
    ];

    private Connection $db;
    private MarketplaceConnectorsRepository $connectorsRepository;

    public function __construct(?MarketplaceConnectorsRepository $connectorsRepository = null)
    {
        $this->db = new Connection();
        $this->connectorsRepository = $connectorsRepository ?? new MarketplaceConnectorsRepository();
    }

    public function sync(int $ownerId, string $provider, ?array $manualPayload = null): array
    {
        if (!isset(self::PROVIDERS[$provider])) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'message' => 'Invalid marketplace provider.',
            ];
        }

        if (!$this->connectorsRepository->isReady()
            || !$this->tableExists('marketplace_sync_runs')
            || !$this->tableExists('external_order_mappings')
        ) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'message' => 'Marketplace connector SQL is not installed yet.',
            ];
        }

        $connector = $this->connectorsRepository->getCredentialsForSync($ownerId, $provider);
        if (!$connector || strtoupper((string)$connector->status) !== 'ACTIVE') {
            return [
                'success' => false,
                'status' => 'FAILED',
                'message' => 'Activate and save this connector before syncing.',
            ];
        }

        if (trim((string)($connector->access_token ?? '')) === '') {
            return [
                'success' => false,
                'status' => 'FAILED',
                'message' => 'Access token is required before syncing.',
            ];
        }

        $mappingId = null;
        $summary = 'Manual sync shell recorded. External API adapters can now be connected without changing module access or pricing.';
        $status = 'MANUAL_REVIEW';

        if ($manualPayload && !empty($manualPayload)) {
            $mapping = $this->upsertExternalOrderMapping($ownerId, $provider, $manualPayload);

            if (!$mapping['success']) {
                $runId = $this->logRun($ownerId, $provider, 'FAILED', $mapping['message'], $manualPayload);
                $this->connectorsRepository->markSync($ownerId, $provider, 'FAILED', $mapping['message']);

                return [
                    'success' => false,
                    'status' => 'FAILED',
                    'run_id' => $runId,
                    'message' => $mapping['message'],
                ];
            }

            $mappingId = $mapping['mapping_id'];
            $status = 'SUCCESS';
            $summary = 'Manual marketplace payload mapped into Ophyra status "' . $mapping['internal_status'] . '". Store import can be reviewed from the mapping record.';
        } else {
            try {
                $adapter = MarketplaceProviderFactory::make($provider, $connector);
                $adapter->testConnection();
                $importer = new MarketplaceImportService();
                $products = []; $productCursor = null;
                for ($page = 0; $page < 100; $page++) {
                    $productResult = $adapter->fetchProducts($productCursor);
                    $products = array_merge($products, (array)($productResult['items'] ?? []));
                    $next = $productResult['next_cursor'] ?? null;
                    if ($next === null || $next === '' || $next === $productCursor) break;
                    $productCursor = (string)$next;
                }
                $orderResult = $adapter->fetchOrders();
                $orders = (array)($orderResult['items'] ?? []);
                $productsWritten = $importer->importProducts($ownerId, $connector, $provider, $products);
                $ordersWritten = $importer->importOrders($ownerId, $connector, $provider, $orders);
                $status = 'SUCCESS';
                $summary = sprintf('Marketplace synchronized: %d product(s), %d order(s).', $productsWritten, $ordersWritten);
            } catch (\Throwable $e) {
                $summary = $e->getMessage();
                $runId = $this->logRun($ownerId, $provider, 'FAILED', $summary);
                $this->connectorsRepository->markSync($ownerId, $provider, 'FAILED', $summary);
                return ['success'=>false,'status'=>'FAILED','run_id'=>$runId,'message'=>$summary];
            }
        }

        $runId = $this->logRun($ownerId, $provider, $status, $summary, $manualPayload);
        $this->connectorsRepository->markSync($ownerId, $provider, $status, null);

        return [
            'success' => true,
            'status' => $status,
            'run_id' => $runId,
            'mapping_id' => $mappingId,
            'message' => $summary,
        ];
    }

    public function getRecentRuns(int $ownerId, int $limit = 20): array
    {
        if (!$this->tableExists('marketplace_sync_runs')) {
            return [];
        }

        try {
            $this->db->query("
                SELECT *
                FROM marketplace_sync_runs
                WHERE id_owner = :owner_id
                ORDER BY id DESC
                LIMIT :limit
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':limit', $limit, \PDO::PARAM_INT);

            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('MarketplaceSyncService::getRecentRuns(): ' . $e->getMessage());
            return [];
        }
    }

    public function getRecentMappings(int $ownerId, int $limit = 20): array
    {
        if (!$this->tableExists('external_order_mappings')) {
            return [];
        }

        try {
            $this->db->query("
                SELECT *
                FROM external_order_mappings
                WHERE id_owner = :owner_id
                ORDER BY id DESC
                LIMIT :limit
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':limit', $limit, \PDO::PARAM_INT);

            return $this->db->fetchAll();
        } catch (PDOException $e) {
            error_log('MarketplaceSyncService::getRecentMappings(): ' . $e->getMessage());
            return [];
        }
    }

    public function normalizeExternalStatus(string $provider, ?string $externalStatus, ?string $paymentStatus = null): string
    {
        $status = strtolower(trim((string)$externalStatus));
        $paymentStatus = strtolower(trim((string)$paymentStatus));

        if (in_array($status, ['cancelled', 'canceled', 'cancel', 'voided'], true)) {
            return 'cancelled';
        }

        if (in_array($status, ['refunded', 'refund', 'charged_back', 'chargeback'], true)) {
            return 'refunded';
        }

        if (in_array($status, ['delivered', 'delivery_completed'], true)) {
            return 'delivered';
        }

        if (in_array($status, ['completed', 'closed', 'fulfilled'], true)) {
            return 'completed';
        }

        if (in_array($status, ['out_for_delivery'], true)) {
            return 'out_for_delivery';
        }

        if (in_array($status, ['shipped', 'in_transit', 'posted'], true)) {
            return 'shipped';
        }

        if (in_array($status, ['ready_to_ship', 'ready'], true)) {
            return 'ready_to_ship';
        }

        if (in_array($status, ['packed'], true)) {
            return 'packed';
        }

        if (in_array($status, ['preparing', 'processing'], true)) {
            return 'preparing';
        }

        if (in_array($paymentStatus, ['paid', 'approved', 'authorized', 'captured'], true)) {
            return 'paid';
        }

        if (in_array($status, ['paid', 'approved'], true)) {
            return 'paid';
        }

        if (in_array($paymentStatus, ['pending', 'in_process', 'waiting_payment'], true)) {
            return 'pending_payment';
        }

        return 'received';
    }

    private function upsertExternalOrderMapping(int $ownerId, string $provider, array $payload): array
    {
        $externalOrderId = trim((string)($payload['external_order_id'] ?? $payload['order_id'] ?? $payload['id'] ?? ''));
        if ($externalOrderId === '') {
            return [
                'success' => false,
                'message' => 'Manual payload must include external_order_id, order_id or id.',
            ];
        }

        $externalSource = trim((string)($payload['external_source'] ?? $provider));
        $externalStatus = trim((string)($payload['external_status'] ?? $payload['status'] ?? ''));
        $paymentStatus = trim((string)($payload['payment_status'] ?? $payload['marketplace_payment_status'] ?? ''));
        $internalStatus = $this->normalizeExternalStatus($provider, $externalStatus, $paymentStatus);
        $storeOrderId = isset($payload['id_store_order']) && (int)$payload['id_store_order'] > 0
            ? (int)$payload['id_store_order']
            : null;

        try {
            $this->db->query("
                INSERT INTO external_order_mappings
                    (id_owner, provider, external_source, external_order_id, external_pack_id, external_shipment_id,
                     id_store_order, external_status, internal_status, marketplace_payment_status, raw_payload,
                     sync_status, imported_at, created_at, updated_at)
                VALUES
                    (:owner_id, :provider, :external_source, :external_order_id, :external_pack_id, :external_shipment_id,
                     :id_store_order, :external_status, :internal_status, :payment_status, :raw_payload,
                     :sync_status, :imported_at, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    external_pack_id = VALUES(external_pack_id),
                    external_shipment_id = VALUES(external_shipment_id),
                    id_store_order = VALUES(id_store_order),
                    external_status = VALUES(external_status),
                    internal_status = VALUES(internal_status),
                    marketplace_payment_status = VALUES(marketplace_payment_status),
                    raw_payload = VALUES(raw_payload),
                    sync_status = VALUES(sync_status),
                    imported_at = VALUES(imported_at),
                    updated_at = NOW()
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':provider', $provider);
            $this->db->bind(':external_source', $externalSource);
            $this->db->bind(':external_order_id', $externalOrderId);
            $this->db->bind(':external_pack_id', trim((string)($payload['external_pack_id'] ?? $payload['pack_id'] ?? '')) ?: null);
            $this->db->bind(':external_shipment_id', trim((string)($payload['external_shipment_id'] ?? $payload['shipment_id'] ?? '')) ?: null);
            $this->db->bind(':id_store_order', $storeOrderId);
            $this->db->bind(':external_status', $externalStatus ?: null);
            $this->db->bind(':internal_status', $internalStatus);
            $this->db->bind(':payment_status', $paymentStatus ?: null);
            $this->db->bind(':raw_payload', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->db->bind(':sync_status', $storeOrderId ? 'MAPPED' : 'NEW');
            $this->db->bind(':imported_at', $storeOrderId ? date('Y-m-d H:i:s') : null);
            $this->db->execute();

            $this->db->query("
                SELECT id
                FROM external_order_mappings
                WHERE id_owner = :owner_id
                  AND external_source = :external_source
                  AND external_order_id = :external_order_id
                LIMIT 1
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':external_source', $externalSource);
            $this->db->bind(':external_order_id', $externalOrderId);
            $row = $this->db->fetchOne();

            return [
                'success' => true,
                'mapping_id' => $row->id ?? null,
                'internal_status' => $internalStatus,
                'message' => 'External order mapping saved.',
            ];
        } catch (PDOException $e) {
            error_log('MarketplaceSyncService::upsertExternalOrderMapping(): ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Could not save external order mapping.',
            ];
        }
    }

    private function logRun(int $ownerId, string $provider, string $status, string $summary, ?array $rawPayload = null): ?int
    {
        try {
            $this->db->query("
                INSERT INTO marketplace_sync_runs
                    (id_owner, provider, status, started_at, finished_at, summary, raw_response, created_at, updated_at)
                VALUES
                    (:owner_id, :provider, :status, NOW(), NOW(), :summary, :raw_response, NOW(), NOW())
            ");
            $this->db->bind(':owner_id', $ownerId);
            $this->db->bind(':provider', $provider);
            $this->db->bind(':status', $status);
            $this->db->bind(':summary', $summary);
            $this->db->bind(':raw_response', $rawPayload ? json_encode($rawPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null);
            $this->db->execute();

            return (int)$this->db->lastId();
        } catch (PDOException $e) {
            error_log('MarketplaceSyncService::logRun(): ' . $e->getMessage());
            return null;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->db->query('SHOW TABLES LIKE :table');
            $this->db->bind(':table', $table);
            return (bool)$this->db->fetchOne();
        } catch (PDOException $e) {
            return false;
        }
    }
}
