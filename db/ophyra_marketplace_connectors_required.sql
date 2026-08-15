-- Ophyra Marketplace Connectors add-on
-- Manual review required before execution.
-- This file supports the separate $12/month module for Mercado Libre,
-- TikTok Business / Shop and Shopify connectors.

INSERT INTO modules
    (slug, name, description, monthly_price, status, sort_order, created_at, updated_at)
VALUES
    (
        'marketplace_connectors',
        'Marketplace Connectors',
        'Connect Mercado Libre, TikTok Business / Shop and Shopify tokens, run manual syncs and map external order statuses into Ophyra.',
        12.00,
        'ACTIVE',
        165,
        NOW(),
        NOW()
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    monthly_price = VALUES(monthly_price),
    status = VALUES(status),
    sort_order = VALUES(sort_order),
    updated_at = NOW();

CREATE TABLE IF NOT EXISTS marketplace_connectors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_owner BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    store_url VARCHAR(255) NULL,
    account_id VARCHAR(120) NULL,
    shop_domain VARCHAR(190) NULL,
    access_token_encrypted TEXT NULL,
    refresh_token_encrypted TEXT NULL,
    token_expires_at DATETIME NULL,
    status ENUM('ACTIVE', 'INACTIVE', 'ERROR') NOT NULL DEFAULT 'INACTIVE',
    sync_status ENUM('NEVER', 'RUNNING', 'SUCCESS', 'FAILED', 'MANUAL_REVIEW') NOT NULL DEFAULT 'NEVER',
    last_sync_at DATETIME NULL,
    last_error TEXT NULL,
    public_store_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY marketplace_connectors_owner_provider_unique (id_owner, provider),
    KEY marketplace_connectors_owner_status_idx (id_owner, status),
    KEY marketplace_connectors_provider_idx (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_sync_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_owner BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    status ENUM('RUNNING', 'SUCCESS', 'FAILED', 'MANUAL_REVIEW') NOT NULL DEFAULT 'RUNNING',
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    summary TEXT NULL,
    raw_response LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY marketplace_sync_runs_owner_provider_idx (id_owner, provider),
    KEY marketplace_sync_runs_status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_order_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_owner BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    external_source VARCHAR(60) NOT NULL,
    external_order_id VARCHAR(120) NOT NULL,
    external_pack_id VARCHAR(120) NULL,
    external_shipment_id VARCHAR(120) NULL,
    id_store_order BIGINT UNSIGNED NULL,
    external_status VARCHAR(80) NULL,
    internal_status ENUM(
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
        'refunded'
    ) NOT NULL DEFAULT 'received',
    marketplace_payment_status VARCHAR(80) NULL,
    raw_payload LONGTEXT NULL,
    sync_status ENUM('NEW', 'MAPPED', 'IMPORTED', 'FAILED', 'IGNORED') NOT NULL DEFAULT 'NEW',
    imported_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY external_order_mappings_owner_source_order_unique (id_owner, external_source, external_order_id),
    KEY external_order_mappings_store_order_idx (id_store_order),
    KEY external_order_mappings_provider_status_idx (provider, internal_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
