<?php

namespace App\Services;

use App\Entity\User;
use App\Repositories\Connection;
use App\Repositories\InstitutionProfileRepository;

class CentralOperationsContextService
{
    private const SESSION_KEY = 'level1_central_operation_key';
    private const SESSION_OWNER_KEY = 'level1_central_operation_owner_id';

    public function getContext(?User $user = null): array
    {
        $user = $user ?: LoginService::getSession();
        $defaultOwnerId = $user ? (int)($user->getOwner() ?: $user->getId()) : 0;
        $operations = $this->getOperations($defaultOwnerId);

        $requestedKey = strtolower(trim((string)($_GET['operation'] ?? '')));
        $requestedOwnerId = (int)($_GET['operation_owner_id'] ?? 0);
        $selectedKey = $_SESSION[self::SESSION_KEY] ?? 'vnv_events';
        $selectedOwnerId = (int)($_SESSION[self::SESSION_OWNER_KEY] ?? 0);

        if ($requestedOwnerId > 0) {
            $selectedKey = 'custom';
            $selectedOwnerId = $requestedOwnerId;
        } elseif ($requestedKey !== '' && isset($operations[$requestedKey])) {
            $selectedKey = $requestedKey;
            $selectedOwnerId = (int)($operations[$requestedKey]['owner_id'] ?? 0);
        } elseif (!$selectedOwnerId || !$this->operationOwnerIsKnown($operations, $selectedOwnerId)) {
            $selectedKey = 'vnv_events';
            $selectedOwnerId = (int)($operations['vnv_events']['owner_id'] ?? 0);
        }

        $_SESSION[self::SESSION_KEY] = $selectedKey;
        $_SESSION[self::SESSION_OWNER_KEY] = $selectedOwnerId;

        $current = $operations[$selectedKey] ?? [
            'key' => $selectedKey,
            'label' => 'Custom Operation',
            'short_label' => 'Custom',
            'owner_id' => $selectedOwnerId,
            'status' => $selectedOwnerId > 0 ? 'ready' : 'setup_needed',
            'description' => 'Custom platform operation context.',
            'query' => 'operation_owner_id=' . $selectedOwnerId,
            'profile' => null,
        ];

        return [
            'operations' => array_values($operations),
            'current' => $current,
            'key' => $current['key'],
            'owner_id' => (int)($current['owner_id'] ?? 0),
            'query' => $current['key'] === 'custom'
                ? 'operation_owner_id=' . (int)$selectedOwnerId
                : 'operation=' . rawurlencode((string)$current['key']),
        ];
    }

    public function getOwnerId(?User $user = null): int
    {
        $context = $this->getContext($user);
        return (int)($context['owner_id'] ?? 0);
    }

    public function getOperations(int $defaultOwnerId): array
    {
        $profileRepo = new InstitutionProfileRepository();
        $vnvProfile = $this->findVnvProfile();
        $vnvOwnerId = (int)($_ENV['VNV_EVENTS_OWNER_ID'] ?? $_ENV['STORE_OWNER_ID'] ?? $vnvProfile->id_owner ?? $defaultOwnerId);
        $vnvProfile = $vnvOwnerId > 0 ? $profileRepo->getByOwner($vnvOwnerId) : null;

        $avomealProfile = $this->findAvomealProfile();
        $avomealSite = $this->findBrandSite('avomeal');
        $avomealOwnerId = (int)($_ENV['AVOMEAL_OWNER_ID'] ?? $avomealProfile->id_owner ?? $avomealSite->id_user_business ?? 0);
        if (!$avomealProfile && $avomealOwnerId > 0) {
            $avomealProfile = $profileRepo->getByOwner($avomealOwnerId);
        }
        if (!$avomealProfile && $avomealSite) {
            $avomealProfile = (object)[
                'id' => null,
                'id_owner' => $avomealOwnerId,
                'company_name' => $avomealSite->site_name ?? 'Avomeal',
                'site_key' => $avomealSite->site_key ?? 'avomeal',
            ];
        }

        return [
            'vnv_events' => [
                'key' => 'vnv_events',
                'label' => 'VNV Events Operations',
                'short_label' => 'VNV Events',
                'owner_id' => $vnvOwnerId,
                'status' => $vnvOwnerId > 0 ? 'ready' : 'setup_needed',
                'description' => 'Services, events, CRM, orders, contracts, team, payroll and operational reports.',
                'query' => 'operation=vnv_events',
                'site_key' => null,
                'profile' => $vnvProfile,
            ],
            'avomeal' => [
                'key' => 'avomeal',
                'label' => 'Avomeal Operations',
                'short_label' => 'Avomeal',
                'owner_id' => $avomealOwnerId,
                'status' => $avomealOwnerId > 0 ? 'ready' : 'setup_needed',
                'description' => 'Meals, nutrition, weekly menus, subscriptions, delivery zones, store orders and Avomeal reports.',
                'query' => 'operation=avomeal',
                'site_key' => $avomealSite->site_key ?? 'avomeal',
                'profile' => $avomealProfile,
            ],
        ];
    }

    private function findVnvProfile(): ?object
    {
        $db = new Connection();
        $db->query("
            SELECT *
            FROM institution_profile
            WHERE LOWER(COALESCE(company_name, '')) LIKE '%vnv events%'
               OR LOWER(COALESCE(company_name, '')) = 'vnv'
            ORDER BY
                CASE
                    WHEN LOWER(COALESCE(company_name, '')) LIKE '%vnv events%' THEN 0
                    ELSE 1
                END,
                id ASC
            LIMIT 1
        ");

        $row = $db->fetchOne();
        return $row ?: null;
    }

    private function findAvomealProfile(): ?object
    {
        $db = new Connection();
        $db->query("
            SELECT *
            FROM institution_profile
            WHERE LOWER(COALESCE(company_name, '')) LIKE '%avomeal%'
               OR LOWER(COALESCE(company_name, '')) LIKE '%vnv gourmet%'
               OR LOWER(COALESCE(business_nature, '')) LIKE '%meal%'
               OR LOWER(COALESCE(business_operation_type, '')) LIKE '%meal%'
               OR LOWER(COALESCE(business_operation_type, '')) LIKE '%nutrition%'
            ORDER BY id DESC
            LIMIT 1
        ");

        $row = $db->fetchOne();
        return $row ?: null;
    }

    private function findBrandSite(string $siteKey): ?object
    {
        $db = new Connection();
        $db->query("
            SELECT *
            FROM brand_sites
            WHERE site_key = :site_key
              AND status = 'ACTIVE'
            ORDER BY id ASC
            LIMIT 1
        ");
        $db->bind(':site_key', $siteKey);

        $row = $db->fetchOne();
        return $row ?: null;
    }

    private function operationOwnerIsKnown(array $operations, int $ownerId): bool
    {
        if ($ownerId <= 0) {
            return false;
        }

        foreach ($operations as $operation) {
            if ((int)($operation['owner_id'] ?? 0) === $ownerId) {
                return true;
            }
        }

        return false;
    }
}
