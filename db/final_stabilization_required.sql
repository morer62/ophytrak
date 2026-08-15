-- Ophyra final stabilization support tables.
-- Safe to run more than once.
--
-- These tables are referenced by existing repositories or legacy reports but were
-- not present in the reviewed full dump `vnv-venue (6).sql`.
-- They are intentionally conservative and do not remove or modify existing data.

CREATE TABLE IF NOT EXISTS membership_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('monthly','annual') NOT NULL,
    stripe_payment_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_membership_payments_user (id_user),
    INDEX idx_membership_payments_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_agent_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    token_limit INT NOT NULL DEFAULT 50000,
    tokens_used INT NOT NULL DEFAULT 0,
    reset_at DATE NULL,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_agent_tokens_user (id_user),
    INDEX idx_user_agent_tokens_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Legacy repositories exist for these tables, but no active migration or strong
-- runtime dependency was found during the final stabilization audit:
--
-- orders_closure_payment_receipts
-- orders_closure_service_proofs
-- orders_services_steps
--
-- Do not create them blindly until the intended closure/steps schema is
-- confirmed from the corresponding UI flow.
