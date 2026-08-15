-- Ophyra legal consent and geo-pricing compliance foundation.
-- Manual review required before execution.
-- Non-destructive: creates legal document and user consent audit tables.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS legal_documents (
  id INT(11) NOT NULL AUTO_INCREMENT,
  document_type VARCHAR(80) NOT NULL,
  version VARCHAR(40) NOT NULL,
  title VARCHAR(255) NOT NULL,
  effective_date DATE NOT NULL,
  content_url VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY legal_documents_type_version_unique (document_type, version),
  KEY legal_documents_active_idx (document_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_legal_consents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user INT(11) NULL,
  email VARCHAR(190) NULL,
  document_type VARCHAR(80) NOT NULL,
  document_version VARCHAR(40) NOT NULL,
  accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(80) NULL,
  country_code VARCHAR(8) NULL,
  currency_code VARCHAR(8) NULL,
  user_agent TEXT NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'signup',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_legal_consents_user_idx (id_user),
  KEY user_legal_consents_email_idx (email),
  KEY user_legal_consents_document_idx (document_type, document_version),
  KEY user_legal_consents_country_currency_idx (country_code, currency_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO legal_documents
  (document_type, version, title, effective_date, content_url, is_active, created_at, updated_at)
VALUES
  ('terms_conditions', '2026-06-06', 'Ophyra Terms and Conditions', '2026-06-06', '/terms-and-conditions', 1, NOW(), NOW()),
  ('privacy_policy', '2026-06-06', 'Ophyra Privacy Policy', '2026-06-06', '/privacy-policy', 1, NOW(), NOW()),
  ('cookie_policy', '2026-06-06', 'Ophyra Cookie Policy', '2026-06-06', '/cookie-policy', 1, NOW(), NOW()),
  ('data_processing_notice', '2026-06-06', 'Ophyra Data Processing Notice', '2026-06-06', '/data-processing-notice', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  effective_date = VALUES(effective_date),
  content_url = VALUES(content_url),
  is_active = VALUES(is_active),
  updated_at = NOW();

COMMIT;
