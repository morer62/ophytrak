ALTER TABLE `store_order_tasks`
    ADD COLUMN IF NOT EXISTS `assigned_by` INT(10) UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS `assigned_at` DATETIME NULL;

-- users.id is INT(10) UNSIGNED. Normalize installations where the column
-- may already have been created as a signed INT before adding a foreign key.
ALTER TABLE `store_order_tasks`
    MODIFY COLUMN `assigned_by` INT(10) UNSIGNED NULL;

UPDATE `store_order_tasks`
SET `assigned_at` = COALESCE(`assigned_at`, `created_at`)
WHERE `assigned_at` IS NULL;
