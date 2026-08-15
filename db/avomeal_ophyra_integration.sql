-- Avomeal / VNV Gourmet integration support for Ophyra Store.
-- Safe to run more than once.
--
-- This script does not migrate historical Avomeal data. It only makes sure the
-- central Ophyra database has the Store + Nutrition + Meal Prep structures needed
-- to operate Avomeal as a separate business operation/owner.

CREATE TABLE IF NOT EXISTS store_products_nutrition (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NULL,
    id_product INT NOT NULL,
    calories INT NULL,
    protein DECIMAL(6,2) NULL,
    carbohydrates DECIMAL(6,2) NULL,
    fat DECIMAL(6,2) NULL,
    fiber DECIMAL(6,2) NULL,
    sugar DECIMAL(6,2) NULL,
    sodium DECIMAL(6,2) NULL,
    serving_size VARCHAR(100) NULL,
    ingredients TEXT NULL,
    allergens TEXT NULL,
    diet_tags TEXT NULL,
    portion_size VARCHAR(100) NULL,
    meal_type VARCHAR(80) NULL,
    spice_level VARCHAR(40) NULL,
    heating_instructions TEXT NULL,
    storage_instructions TEXT NULL,
    shelf_life VARCHAR(120) NULL,
    nutrition_notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_store_product_nutrition (id_product),
    INDEX idx_store_products_nutrition_owner (id_owner),
    INDEX idx_store_products_nutrition_product (id_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN allergens TEXT NULL AFTER ingredients', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'allergens'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN diet_tags TEXT NULL AFTER allergens', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'diet_tags'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN portion_size VARCHAR(100) NULL AFTER diet_tags', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'portion_size'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN meal_type VARCHAR(80) NULL AFTER portion_size', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'meal_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN spice_level VARCHAR(40) NULL AFTER meal_type', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'spice_level'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN heating_instructions TEXT NULL AFTER spice_level', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'heating_instructions'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN storage_instructions TEXT NULL AFTER heating_instructions', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'storage_instructions'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN shelf_life VARCHAR(120) NULL AFTER storage_instructions', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'shelf_life'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE store_products_nutrition ADD COLUMN nutrition_notes TEXT NULL AFTER shelf_life', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products_nutrition' AND COLUMN_NAME = 'nutrition_notes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS store_products_audiences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL DEFAULT 2,
    id_product INT NOT NULL,
    audience_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_store_product_audience (id_product, audience_type),
    INDEX idx_store_product_audiences_owner (id_owner),
    INDEX idx_store_product_audiences_product (id_product),
    INDEX idx_store_product_audiences_type (audience_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_products_meal_styles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL DEFAULT 2,
    id_product INT NOT NULL,
    meal_style VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_store_product_meal_style (id_product, meal_style),
    INDEX idx_store_product_meal_styles_owner (id_owner),
    INDEX idx_store_product_meal_styles_product (id_product),
    INDEX idx_store_product_meal_styles_type (meal_style)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL,
    id_user INT NULL,
    id_store_order INT NULL,
    archive TINYINT(1) NOT NULL DEFAULT 0,
    coupon_code VARCHAR(80) NULL,
    id_coupon INT NULL,
    email VARCHAR(190) NOT NULL,
    full_name VARCHAR(150) NULL,
    phone VARCHAR(60) NULL,
    city VARCHAR(120) NULL,
    frequency ENUM('WEEKLY') NOT NULL DEFAULT 'WEEKLY',
    status ENUM('ACTIVE','PAUSED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
    price_per_meal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    minimum_meals INT NOT NULL DEFAULT 5,
    meals_count INT NOT NULL DEFAULT 0,
    next_charge_date DATE NULL,
    last_charge_date DATE NULL,
    external_subscription_id VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_store_subscriptions_owner (id_owner),
    INDEX idx_store_subscriptions_user (id_user),
    INDEX idx_store_subscriptions_email (email),
    INDEX idx_store_subscriptions_status (status),
    INDEX idx_store_subscriptions_next_charge (next_charge_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_subscription_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL,
    id_subscription INT NOT NULL,
    id_product INT NOT NULL,
    product_name_snapshot VARCHAR(200) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_store_subscription_items_owner (id_owner),
    INDEX idx_store_subscription_items_subscription (id_subscription),
    INDEX idx_store_subscription_items_product (id_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL,
    id_user INT NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'general',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY unique_store_user_role (id_owner, id_user),
    INDEX idx_store_user_roles_owner (id_owner),
    INDEX idx_store_user_roles_user (id_user),
    INDEX idx_store_user_roles_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_delivery_zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL,
    zone_name VARCHAR(150) NOT NULL,
    zip_code VARCHAR(20) NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(80) NULL,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    minimum_order_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_days VARCHAR(255) NULL,
    pickup_available TINYINT(1) NOT NULL DEFAULT 0,
    delivery_available TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    INDEX idx_store_delivery_zones_owner (id_owner),
    INDEX idx_store_delivery_zones_zip (zip_code),
    INDEX idx_store_delivery_zones_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_weekly_menus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL,
    menu_name VARCHAR(150) NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    status ENUM('DRAFT','ACTIVE','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    INDEX idx_store_weekly_menus_owner (id_owner),
    INDEX idx_store_weekly_menus_week (week_start, week_end),
    INDEX idx_store_weekly_menus_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_weekly_menu_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_owner INT NOT NULL,
    id_weekly_menu INT NOT NULL,
    id_product INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_store_weekly_menu_product (id_weekly_menu, id_product),
    INDEX idx_store_weekly_menu_products_owner (id_owner),
    INDEX idx_store_weekly_menu_products_menu (id_weekly_menu),
    INDEX idx_store_weekly_menu_products_product (id_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
