-- Store + Logistics mobile location and communication context extensions
-- Manual SQL only. Safe to run more than once on MySQL/MariaDB.
-- Goal: reuse existing Store, Payroll and Chat tables. Do not create parallel delivery tables.

-- ---------------------------------------------------------------------
-- 1) Delivery GPS metadata on existing store_delivery_location_logs
-- ---------------------------------------------------------------------

SET @schema_name := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_delivery_location_logs ADD COLUMN accuracy DECIMAL(10,2) NULL AFTER longitude',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'store_delivery_location_logs'
      AND COLUMN_NAME = 'accuracy'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_delivery_location_logs ADD COLUMN platform VARCHAR(40) NULL AFTER accuracy',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'store_delivery_location_logs'
      AND COLUMN_NAME = 'platform'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_delivery_location_logs ADD COLUMN source VARCHAR(60) NULL AFTER platform',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'store_delivery_location_logs'
      AND COLUMN_NAME = 'source'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_delivery_location_logs ADD COLUMN permission_status VARCHAR(40) NULL AFTER source',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'store_delivery_location_logs'
      AND COLUMN_NAME = 'permission_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_delivery_location_logs ADD COLUMN device_id VARCHAR(120) NULL AFTER permission_status',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'store_delivery_location_logs'
      AND COLUMN_NAME = 'device_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_delivery_location_logs ADD COLUMN context VARCHAR(60) NULL AFTER device_id',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'store_delivery_location_logs'
      AND COLUMN_NAME = 'context'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE store_delivery_location_logs ADD INDEX idx_store_delivery_logs_context (context)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'store_delivery_location_logs'
      AND INDEX_NAME = 'idx_store_delivery_logs_context'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2) Payroll mobile location metadata on existing payroll_time_logs
-- ---------------------------------------------------------------------

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_time_logs ADD COLUMN location_lat DECIMAL(10,7) NULL AFTER end_time',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'payroll_time_logs'
      AND COLUMN_NAME = 'location_lat'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_time_logs ADD COLUMN location_long DECIMAL(10,7) NULL AFTER location_lat',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'payroll_time_logs'
      AND COLUMN_NAME = 'location_long'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_time_logs ADD COLUMN location_accuracy DECIMAL(10,2) NULL AFTER location_long',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'payroll_time_logs'
      AND COLUMN_NAME = 'location_accuracy'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_time_logs ADD COLUMN location_source VARCHAR(60) NULL AFTER location_accuracy',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'payroll_time_logs'
      AND COLUMN_NAME = 'location_source'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_time_logs ADD COLUMN location_permission_status VARCHAR(40) NULL AFTER location_source',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'payroll_time_logs'
      AND COLUMN_NAME = 'location_permission_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3) Optional Store order chat context on existing chat_threads
-- ---------------------------------------------------------------------

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE chat_threads ADD COLUMN id_owner INT NULL AFTER id_user_2',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'chat_threads'
      AND COLUMN_NAME = 'id_owner'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE chat_threads ADD COLUMN id_store_order INT NULL AFTER id_owner',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'chat_threads'
      AND COLUMN_NAME = 'id_store_order'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE chat_threads ADD COLUMN id_store_order_task INT NULL AFTER id_store_order',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'chat_threads'
      AND COLUMN_NAME = 'id_store_order_task'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE chat_threads ADD COLUMN context VARCHAR(60) NULL AFTER id_store_order_task',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'chat_threads'
      AND COLUMN_NAME = 'context'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE chat_threads ADD COLUMN visibility VARCHAR(40) NULL AFTER context',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'chat_threads'
      AND COLUMN_NAME = 'visibility'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE chat_threads ADD INDEX idx_chat_threads_store_order (id_store_order)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'chat_threads'
      AND INDEX_NAME = 'idx_chat_threads_store_order'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE chat_threads ADD INDEX idx_chat_threads_context (context)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'chat_threads'
      AND INDEX_NAME = 'idx_chat_threads_context'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Intentional non-changes
-- ---------------------------------------------------------------------
-- No delivery_routes table.
-- No delivery_route_stops table.
-- No delivery_assignments table.
-- No delivery_attempts table.
-- No delivery_proofs table.
-- No user_location_pings table.
-- Preparation remains generic through store_orders.status = IN_PREPARATION
-- and store_order_tasks.task_type = PREPARATION. Existing kitchen_user_id
-- remains as a backward-compatible assignment column.
