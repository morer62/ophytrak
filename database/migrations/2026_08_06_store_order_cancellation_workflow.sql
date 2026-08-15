-- Store order cancellation is a commercial decision separate from logistics status.
-- Safe to run once after selecting the Ophyra database.
ALTER TABLE store_orders
  ADD COLUMN cancellation_status ENUM('NONE','REQUESTED','REJECTED','RESEND','REFUNDED') NOT NULL DEFAULT 'NONE' AFTER status,
  ADD COLUMN cancellation_reason TEXT NULL AFTER cancellation_status,
  ADD COLUMN cancellation_previous_status VARCHAR(40) NULL AFTER cancellation_reason,
  ADD COLUMN cancellation_requested_at DATETIME NULL AFTER cancellation_previous_status,
  ADD COLUMN cancellation_admin_message TEXT NULL AFTER cancellation_requested_at,
  ADD COLUMN cancellation_decision_at DATETIME NULL AFTER cancellation_admin_message,
  ADD INDEX idx_store_orders_owner_cancellation (id_owner, cancellation_status);
