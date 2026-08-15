-- Store / Commerce operational integration for Team Members.
-- Safe to run more than once on MySQL / MariaDB.

ALTER TABLE store_orders
    MODIFY COLUMN status ENUM(
        'NEW',
        'CONFIRMED',
        'PROCESSING',
        'IN_PREPARATION',
        'READY',
        'READY_FOR_DELIVERY',
        'OUT_FOR_DELIVERY',
        'DELIVERED',
        'COMPLETED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'NEW';

SET @has_allow_team_close_delivery = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'store_order_workflow'
      AND COLUMN_NAME = 'allow_team_close_delivery'
);
SET @sql = IF(
    @has_allow_team_close_delivery = 0,
    'ALTER TABLE store_order_workflow ADD COLUMN allow_team_close_delivery TINYINT(1) NOT NULL DEFAULT 0 AFTER delivery_user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_allow_chat_with_client = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'store_order_workflow'
      AND COLUMN_NAME = 'allow_chat_with_client'
);
SET @sql = IF(
    @has_allow_chat_with_client = 0,
    'ALTER TABLE store_order_workflow ADD COLUMN allow_chat_with_client TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_team_close_delivery',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_delivery_closed_by = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'store_order_workflow'
      AND COLUMN_NAME = 'delivery_closed_by'
);
SET @sql = IF(
    @has_delivery_closed_by = 0,
    'ALTER TABLE store_order_workflow ADD COLUMN delivery_closed_by INT NULL AFTER delivery_location_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_completed_at = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'store_order_workflow'
      AND COLUMN_NAME = 'completed_at'
);
SET @sql = IF(
    @has_completed_at = 0,
    'ALTER TABLE store_order_workflow ADD COLUMN completed_at DATETIME NULL AFTER delivery_closed_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS store_order_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL,
    id_store_order INT NOT NULL,
    id_user INT NULL,
    task_type ENUM('PREPARATION','ASSISTANCE','DELIVERY','FULFILLMENT','CUSTOMER_SUPPORT','OTHER') NOT NULL DEFAULT 'OTHER',
    title VARCHAR(180) NOT NULL,
    instructions TEXT NULL,
    status ENUM('PENDING','IN_PROGRESS','WAITING_REVIEW','COMPLETED','CANCELED') NOT NULL DEFAULT 'PENDING',
    requires_location TINYINT(1) NOT NULL DEFAULT 0,
    allow_assignee_complete TINYINT(1) NOT NULL DEFAULT 1,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    completed_by INT NULL,
    notes TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_store_order_tasks_owner (id_owner),
    INDEX idx_store_order_tasks_order (id_store_order),
    INDEX idx_store_order_tasks_user (id_user),
    INDEX idx_store_order_tasks_status (status),
    INDEX idx_store_order_tasks_type (task_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_delivery_location_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL,
    id_store_order INT NOT NULL,
    id_store_order_task INT NULL,
    id_user INT NOT NULL,
    event_type ENUM('TASK_START','LOCATION_UPDATE','OUT_FOR_DELIVERY','ARRIVED','DELIVERED','CLOCK_IN','CLOCK_OUT') NOT NULL DEFAULT 'LOCATION_UPDATE',
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NULL,
    INDEX idx_store_delivery_logs_owner (id_owner),
    INDEX idx_store_delivery_logs_order (id_store_order),
    INDEX idx_store_delivery_logs_task (id_store_order_task),
    INDEX idx_store_delivery_logs_user (id_user),
    INDEX idx_store_delivery_logs_recorded (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
