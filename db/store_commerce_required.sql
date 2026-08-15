-- Store / Commerce owner isolation and paid Services module.
-- Safe to run more than once.

SET @store_products_owner_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_products ADD COLUMN id_owner INT NOT NULL DEFAULT 2 AFTER id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'store_products'
      AND COLUMN_NAME = 'id_owner'
);
PREPARE store_products_owner_stmt FROM @store_products_owner_sql;
EXECUTE store_products_owner_stmt;
DEALLOCATE PREPARE store_products_owner_stmt;

SET @store_products_owner_index_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_products ADD INDEX idx_store_products_owner (id_owner)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'store_products'
      AND INDEX_NAME = 'idx_store_products_owner'
);
PREPARE store_products_owner_index_stmt FROM @store_products_owner_index_sql;
EXECUTE store_products_owner_index_stmt;
DEALLOCATE PREPARE store_products_owner_index_stmt;

SET @store_products_owner_slug_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_products ADD UNIQUE INDEX uniq_store_products_owner_slug (id_owner, slug)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'store_products'
      AND INDEX_NAME = 'uniq_store_products_owner_slug'
);
PREPARE store_products_owner_slug_stmt FROM @store_products_owner_slug_sql;
EXECUTE store_products_owner_slug_stmt;
DEALLOCATE PREPARE store_products_owner_slug_stmt;

INSERT INTO modules (
    slug, name, description, is_base, monthly_price, status, sort_order, created_at, updated_at
) SELECT
    'services',
    'Service Operations',
    'Core service operations system for CRM, clients, service orders, contracts, team, communication and reports.',
    0,
    COALESCE((SELECT monthly_price FROM modules WHERE slug = 'service_operations' LIMIT 1), 24.00),
    'ACTIVE',
    25,
    NOW(),
    NOW()
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_base = VALUES(is_base),
    monthly_price = VALUES(monthly_price),
    status = VALUES(status),
    sort_order = VALUES(sort_order),
    updated_at = NOW();
