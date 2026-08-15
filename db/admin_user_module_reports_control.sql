CREATE TABLE IF NOT EXISTS admin_account_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL,
    id_user INT NOT NULL,
    action_type VARCHAR(80) NOT NULL,
    note TEXT NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_account_actions_user (id_user),
    INDEX idx_admin_account_actions_admin (id_admin),
    INDEX idx_admin_account_actions_type (action_type),
    INDEX idx_admin_account_actions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
