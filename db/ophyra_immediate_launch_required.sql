-- Ophyra immediate commercial launch support.
-- Safe to run more than once.
--
-- Purpose:
-- 1. Ensure payments_all can audit paid add-on module checkout.
-- 2. Ensure the official Ophyra Base Profile, core module and add-on rows exist.
--
-- This script does not migrate legacy Venue/Service/Event payments and does not
-- remove any existing module or payment data.

SET @sql = (
    SELECT IF(
        COLUMN_TYPE LIKE "%'OphyraAddon'%",
        'SELECT 1',
        "ALTER TABLE payments_all MODIFY COLUMN concept ENUM('Venue','Service','Membership','Event','OphyraAddon') NOT NULL"
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments_all'
      AND COLUMN_NAME = 'concept'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO modules (slug, name, description, is_base, monthly_price, status, sort_order, created_at, updated_at)
VALUES
('crm', 'CRM', 'Track leads, follow-ups and client movement.', 1, 0.00, 'ACTIVE', 10, NOW(), NOW()),
('clients', 'Clients', 'Manage customer records and business relationships.', 1, 0.00, 'ACTIVE', 20, NOW(), NOW()),
('team', 'Team', 'Manage staff, permissions and internal coordination.', 1, 0.00, 'ACTIVE', 30, NOW(), NOW()),
('basic_payroll', 'Basic Payroll', 'Review team hours and basic payroll work.', 1, 0.00, 'ACTIVE', 40, NOW(), NOW()),
('orders', 'Orders', 'Manage jobs, services, notes and execution.', 1, 0.00, 'ACTIVE', 50, NOW(), NOW()),
('contracts', 'Contracts / E-signature', 'Keep signatures tied to order work.', 1, 0.00, 'ACTIVE', 60, NOW(), NOW()),
('basic_chat', 'Basic Chat', 'Coordinate with team and clients through internal conversations.', 1, 0.00, 'ACTIVE', 70, NOW(), NOW()),
('business_profile', 'Business Profile', 'Maintain the public and operational profile for the business.', 1, 0.00, 'ACTIVE', 80, NOW(), NOW()),
('base_profile', 'Ophyra Base Profile', 'Free public business profile and limited starter workspace.', 1, 0.00, 'ACTIVE', 90, NOW(), NOW()),
('service_operations', 'Service Operations', 'Core service operations system for CRM, clients, service orders, contracts, team, communication and reports.', 0, 24.00, 'ACTIVE', 110, NOW(), NOW()),
('store_logistics', 'Store + Logistics', 'Core commerce and logistics system for products, orders, fulfillment, delivery, tracking, basic inventory and reports.', 0, 34.00, 'ACTIVE', 120, NOW(), NOW()),
('advanced_storage_qr_inventory', 'Advanced Storage / QR Inventory', 'Advanced containers, physical items, QR labels, storage locations and warehouse-style organization.', 0, 15.00, 'ACTIVE', 130, NOW(), NOW()),
('ai_advisor', 'AI Advisor', 'Ideas, summaries, recommendations, content support and operational guidance.', 0, 9.00, 'ACTIVE', 140, NOW(), NOW()),
('ticket_sales_rsvp', 'Ticket Sales + RSVP', 'Ticket sales, RSVP, event registrations, attendee lists and check-in basics with no ticket fee.', 0, 15.00, 'ACTIVE', 150, NOW(), NOW()),
('custom_domain_seo_page_builder', 'Custom Domain + SEO Page Builder', 'Future custom-domain and SEO/page builder module for public business profiles.', 0, 0.00, 'INACTIVE', 160, NOW(), NOW()),
('inventory_storage', 'Advanced Storage / QR Inventory', 'Legacy alias for advanced_storage_qr_inventory.', 0, 15.00, 'ACTIVE', 170, NOW(), NOW()),
('services', 'Service Operations', 'Legacy alias for service_operations.', 0, 24.00, 'ACTIVE', 180, NOW(), NOW()),
('store_delivery_tracking', 'Store + Logistics', 'Legacy alias for store_logistics.', 0, 34.00, 'ACTIVE', 190, NOW(), NOW()),
('tickets_rsvp', 'Ticket Sales + RSVP', 'Legacy alias for ticket_sales_rsvp.', 0, 15.00, 'ACTIVE', 200, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_base = VALUES(is_base),
    monthly_price = VALUES(monthly_price),
    status = VALUES(status),
    sort_order = VALUES(sort_order),
    updated_at = NOW();
