-- Ophyra Community Knowledge Base setup and seed
-- Manual review required before execution.
-- Converts Explore the Community into a moderated knowledge-base forum using existing forum_* tables.
-- Seeds 60 approved official threads/replies authored by user_id = 1.

START TRANSACTION;

-- Non-destructive moderation/state columns.
ALTER TABLE forum_categories ADD COLUMN IF NOT EXISTS slug VARCHAR(160) NULL AFTER id;
ALTER TABLE forum_categories ADD UNIQUE KEY IF NOT EXISTS forum_categories_slug_unique (slug);
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS slug VARCHAR(220) NULL AFTER title;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'approved' AFTER is_approved;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS is_official TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS approved_by INT(11) NULL AFTER is_official;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS rejected_by INT(11) NULL AFTER approved_at;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL AFTER rejected_by;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER rejected_at;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS hidden_at DATETIME NULL AFTER rejection_reason;
ALTER TABLE forum_topics ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER hidden_at;
ALTER TABLE forum_topics ADD UNIQUE KEY IF NOT EXISTS forum_topics_slug_unique (slug);
ALTER TABLE forum_topics ADD KEY IF NOT EXISTS forum_topics_status_idx (status);
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'visible' AFTER is_approved;
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS is_official_answer TINYINT(1) NOT NULL DEFAULT 0 AFTER is_best_answer;
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS marked_official_by INT(11) NULL AFTER is_official_answer;
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS marked_official_at DATETIME NULL AFTER marked_official_by;
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS hidden_by INT(11) NULL AFTER marked_official_at;
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS hidden_at DATETIME NULL AFTER hidden_by;
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS banned_by INT(11) NULL AFTER hidden_at;
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS banned_at DATETIME NULL AFTER banned_by;
ALTER TABLE forum_replies ADD COLUMN IF NOT EXISTS ban_reason TEXT NULL AFTER banned_at;
ALTER TABLE forum_replies ADD KEY IF NOT EXISTS forum_replies_status_idx (status);

CREATE TABLE IF NOT EXISTS forum_moderation_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_topic INT(11) NULL,
  id_reply INT(11) NULL,
  action VARCHAR(80) NOT NULL,
  performed_by INT(11) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY forum_moderation_logs_topic_idx (id_topic),
  KEY forum_moderation_logs_reply_idx (id_reply),
  KEY forum_moderation_logs_action_idx (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Official predefined categories.
INSERT INTO forum_categories (slug, name, description, icon, color, order_index, is_active, created_at, updated_at, site_key)
SELECT 'business-owner-support', 'Business Owner Support', 'Business operations, organization, sales, follow-up, best practices and growth.', 'briefcase', '#14b8a6', 1, 1, NOW(), NOW(), 'ophyra'
WHERE NOT EXISTS (SELECT 1 FROM forum_categories WHERE slug = 'business-owner-support' OR name = 'Business Owner Support');
UPDATE forum_categories SET slug = 'business-owner-support', description = 'Business operations, organization, sales, follow-up, best practices and growth.', icon = 'briefcase', color = '#14b8a6', order_index = 1, is_active = 1, site_key = 'ophyra' WHERE name = 'Business Owner Support';
INSERT INTO forum_categories (slug, name, description, icon, color, order_index, is_active, created_at, updated_at, site_key)
SELECT 'system-how-to', 'System How-To', 'How to use Ophyra, configure modules, understand dashboards and activate workflows.', 'settings', '#3b82f6', 2, 1, NOW(), NOW(), 'ophyra'
WHERE NOT EXISTS (SELECT 1 FROM forum_categories WHERE slug = 'system-how-to' OR name = 'System How-To');
UPDATE forum_categories SET slug = 'system-how-to', description = 'How to use Ophyra, configure modules, understand dashboards and activate workflows.', icon = 'settings', color = '#3b82f6', order_index = 2, is_active = 1, site_key = 'ophyra' WHERE name = 'System How-To';
INSERT INTO forum_categories (slug, name, description, icon, color, order_index, is_active, created_at, updated_at, site_key)
SELECT 'consultant-advice', 'Consultant Advice', 'Consulting-style recommendations for processes, customers, reports and operations.', 'lightbulb', '#a855f7', 3, 1, NOW(), NOW(), 'ophyra'
WHERE NOT EXISTS (SELECT 1 FROM forum_categories WHERE slug = 'consultant-advice' OR name = 'Consultant Advice');
UPDATE forum_categories SET slug = 'consultant-advice', description = 'Consulting-style recommendations for processes, customers, reports and operations.', icon = 'lightbulb', color = '#a855f7', order_index = 3, is_active = 1, site_key = 'ophyra' WHERE name = 'Consultant Advice';
INSERT INTO forum_categories (slug, name, description, icon, color, order_index, is_active, created_at, updated_at, site_key)
SELECT 'system-issues-improvement-reports', 'System Issues & Improvement Reports', 'System issues, bug reports, improvement suggestions and review areas.', 'alert-triangle', '#f97316', 4, 1, NOW(), NOW(), 'ophyra'
WHERE NOT EXISTS (SELECT 1 FROM forum_categories WHERE slug = 'system-issues-improvement-reports' OR name = 'System Issues & Improvement Reports');
UPDATE forum_categories SET slug = 'system-issues-improvement-reports', description = 'System issues, bug reports, improvement suggestions and review areas.', icon = 'alert-triangle', color = '#f97316', order_index = 4, is_active = 1, site_key = 'ophyra' WHERE name = 'System Issues & Improvement Reports';

-- Seed official knowledge threads and official answers.
SET @ophyra_seed_author_id := 1;

-- 1. What is the first thing I should complete after creating my Ophyra account?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the first thing I should complete after creating my Ophyra account?', 'what-is-the-first-thing-i-should-complete-after-creating-my-ophyra-account', 'Official Ophyra knowledge-base thread.

The first step is to complete your Base Profile. This includes your business name, logo, contact information, location, business type, and public profile details. Your Base Profile is free and helps prepare your workspace before activating paid modules.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the first thing I should complete after creating my Ophyra account?' OR slug = 'what-is-the-first-thing-i-should-complete-after-creating-my-ophyra-account');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-first-thing-i-should-complete-after-creating-my-ophyra-account' OR title = 'What is the first thing I should complete after creating my Ophyra account?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'The first step is to complete your Base Profile. This includes your business name, logo, contact information, location, business type, and public profile details. Your Base Profile is free and helps prepare your workspace before activating paid modules.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'The first step is to complete your Base Profile. This includes your business name, logo, contact information, location, business type, and public profile details. Your Base Profile is free and helps prepare your workspace before activating paid modules.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 2. What is included in the free Base Profile?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is included in the free Base Profile?', 'what-is-included-in-the-free-base-profile', 'Official Ophyra knowledge-base thread.

The Base Profile includes your basic public business profile, contact information, location, logo, business type, and limited workspace access. It does not unlock CRM, orders, contracts, team management, store tools, AI, ticketing, or advanced inventory.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is included in the free Base Profile?' OR slug = 'what-is-included-in-the-free-base-profile');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-included-in-the-free-base-profile' OR title = 'What is included in the free Base Profile?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'The Base Profile includes your basic public business profile, contact information, location, logo, business type, and limited workspace access. It does not unlock CRM, orders, contracts, team management, store tools, AI, ticketing, or advanced inventory.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'The Base Profile includes your basic public business profile, contact information, location, logo, business type, and limited workspace access. It does not unlock CRM, orders, contracts, team management, store tools, AI, ticketing, or advanced inventory.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 3. Why are some modules locked in my dashboard?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why are some modules locked in my dashboard?', 'why-are-some-modules-locked-in-my-dashboard', 'Official Ophyra knowledge-base thread.

Modules are locked until they are activated through the Marketplace or manually enabled by an authorized Ophyra admin. The free Base Profile does not unlock paid operational tools.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why are some modules locked in my dashboard?' OR slug = 'why-are-some-modules-locked-in-my-dashboard');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-are-some-modules-locked-in-my-dashboard' OR title = 'Why are some modules locked in my dashboard?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Modules are locked until they are activated through the Marketplace or manually enabled by an authorized Ophyra admin. The free Base Profile does not unlock paid operational tools.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Modules are locked until they are activated through the Marketplace or manually enabled by an authorized Ophyra admin. The free Base Profile does not unlock paid operational tools.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 4. What is the difference between Service Operations and Store + Logistics?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the difference between Service Operations and Store + Logistics?', 'what-is-the-difference-between-service-operations-and-store-logistics', 'Official Ophyra knowledge-base thread.

Service Operations is for businesses that manage clients, services, contracts, teams, and service orders. Store + Logistics is for businesses that sell products, manage store orders, fulfillment, delivery, tracking, and basic inventory.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the difference between Service Operations and Store + Logistics?' OR slug = 'what-is-the-difference-between-service-operations-and-store-logistics');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-difference-between-service-operations-and-store-logistics' OR title = 'What is the difference between Service Operations and Store + Logistics?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Service Operations is for businesses that manage clients, services, contracts, teams, and service orders. Store + Logistics is for businesses that sell products, manage store orders, fulfillment, delivery, tracking, and basic inventory.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Service Operations is for businesses that manage clients, services, contracts, teams, and service orders. Store + Logistics is for businesses that sell products, manage store orders, fulfillment, delivery, tracking, and basic inventory.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 5. Why do CRM, Clients, Team, Chat, Payroll, and Reports appear as shared tools?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why do CRM, Clients, Team, Chat, Payroll, and Reports appear as shared tools?', 'why-do-crm-clients-team-chat-payroll-and-reports-appear-as-shared-tools', 'Official Ophyra knowledge-base thread.

These tools are shared operational tools. They are not sold as separate modules. They become available depending on whether Service Operations, Store + Logistics, or both are active.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why do CRM, Clients, Team, Chat, Payroll, and Reports appear as shared tools?' OR slug = 'why-do-crm-clients-team-chat-payroll-and-reports-appear-as-shared-tools');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-do-crm-clients-team-chat-payroll-and-reports-appear-as-shared-tools' OR title = 'Why do CRM, Clients, Team, Chat, Payroll, and Reports appear as shared tools?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'These tools are shared operational tools. They are not sold as separate modules. They become available depending on whether Service Operations, Store + Logistics, or both are active.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'These tools are shared operational tools. They are not sold as separate modules. They become available depending on whether Service Operations, Store + Logistics, or both are active.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 6. Where do I activate paid modules?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Where do I activate paid modules?', 'where-do-i-activate-paid-modules', 'Official Ophyra knowledge-base thread.

Paid modules should be activated from the Marketplace or Billing & Modules area. This area shows available modules, pricing, status, renewal dates, and activation options.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Where do I activate paid modules?' OR slug = 'where-do-i-activate-paid-modules');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'where-do-i-activate-paid-modules' OR title = 'Where do I activate paid modules?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Paid modules should be activated from the Marketplace or Billing & Modules area. This area shows available modules, pricing, status, renewal dates, and activation options.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Paid modules should be activated from the Marketplace or Billing & Modules area. This area shows available modules, pricing, status, renewal dates, and activation options.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 7. Does clicking Activate immediately turn on a module?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Does clicking Activate immediately turn on a module?', 'does-clicking-activate-immediately-turn-on-a-module', 'Official Ophyra knowledge-base thread.

No. A paid module should not activate immediately after clicking Activate. You should first see a review or checkout page, confirm payment details, complete payment, and only then should the module become active.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Does clicking Activate immediately turn on a module?' OR slug = 'does-clicking-activate-immediately-turn-on-a-module');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'does-clicking-activate-immediately-turn-on-a-module' OR title = 'Does clicking Activate immediately turn on a module?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'No. A paid module should not activate immediately after clicking Activate. You should first see a review or checkout page, confirm payment details, complete payment, and only then should the module become active.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'No. A paid module should not activate immediately after clicking Activate. You should first see a review or checkout page, confirm payment details, complete payment, and only then should the module become active.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 8. What happens if I cancel checkout?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What happens if I cancel checkout?', 'what-happens-if-i-cancel-checkout', 'Official Ophyra knowledge-base thread.

If you cancel checkout before completing payment, the module should remain locked. Your Base Profile remains active, but paid functionality should not be unlocked.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What happens if I cancel checkout?' OR slug = 'what-happens-if-i-cancel-checkout');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-happens-if-i-cancel-checkout' OR title = 'What happens if I cancel checkout?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'If you cancel checkout before completing payment, the module should remain locked. Your Base Profile remains active, but paid functionality should not be unlocked.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'If you cancel checkout before completing payment, the module should remain locked. Your Base Profile remains active, but paid functionality should not be unlocked.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 9. Where can I see my next renewal date?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Where can I see my next renewal date?', 'where-can-i-see-my-next-renewal-date', 'Official Ophyra knowledge-base thread.

Renewal dates should appear inside the Marketplace, Billing & Modules, or module management area. Each paid module should show its own status and renewal date when active.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Where can I see my next renewal date?' OR slug = 'where-can-i-see-my-next-renewal-date');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'where-can-i-see-my-next-renewal-date' OR title = 'Where can I see my next renewal date?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Renewal dates should appear inside the Marketplace, Billing & Modules, or module management area. Each paid module should show its own status and renewal date when active.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Renewal dates should appear inside the Marketplace, Billing & Modules, or module management area. Each paid module should show its own status and renewal date when active.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 10. Why can I choose a currency before paying?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why can I choose a currency before paying?', 'why-can-i-choose-a-currency-before-paying', 'Official Ophyra knowledge-base thread.

Ophyra supports multiple billing currencies. Prices are based on fixed values configured by the platform and should be shown clearly before checkout. The selected currency is used to process payment through Stripe when supported.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why can I choose a currency before paying?' OR slug = 'why-can-i-choose-a-currency-before-paying');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-can-i-choose-a-currency-before-paying' OR title = 'Why can I choose a currency before paying?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Ophyra supports multiple billing currencies. Prices are based on fixed values configured by the platform and should be shown clearly before checkout. The selected currency is used to process payment through Stripe when supported.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Ophyra supports multiple billing currencies. Prices are based on fixed values configured by the platform and should be shown clearly before checkout. The selected currency is used to process payment through Stripe when supported.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 11. Why is the Custom Domain + SEO Page Builder marked Coming Soon?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why is the Custom Domain + SEO Page Builder marked Coming Soon?', 'why-is-the-custom-domain-seo-page-builder-marked-coming-soon', 'Official Ophyra knowledge-base thread.

That module is not ready for checkout yet. It may appear in the Marketplace so users know it is planned, but it should not show a price or allow activation until it is released.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why is the Custom Domain + SEO Page Builder marked Coming Soon?' OR slug = 'why-is-the-custom-domain-seo-page-builder-marked-coming-soon');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-is-the-custom-domain-seo-page-builder-marked-coming-soon' OR title = 'Why is the Custom Domain + SEO Page Builder marked Coming Soon?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'That module is not ready for checkout yet. It may appear in the Marketplace so users know it is planned, but it should not show a price or allow activation until it is released.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'That module is not ready for checkout yet. It may appear in the Marketplace so users know it is planned, but it should not show a price or allow activation until it is released.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 12. What is the public business profile used for?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the public business profile used for?', 'what-is-the-public-business-profile-used-for', 'Official Ophyra knowledge-base thread.

The public business profile is your basic public-facing page. It can show your business information, contact details, location, and later connect with services, products, forms, SEO pages, or other public tools as modules become available.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the public business profile used for?' OR slug = 'what-is-the-public-business-profile-used-for');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-public-business-profile-used-for' OR title = 'What is the public business profile used for?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'The public business profile is your basic public-facing page. It can show your business information, contact details, location, and later connect with services, products, forms, SEO pages, or other public tools as modules become available.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'The public business profile is your basic public-facing page. It can show your business information, contact details, location, and later connect with services, products, forms, SEO pages, or other public tools as modules become available.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 13. How do I know which operating core I need?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How do I know which operating core I need?', 'how-do-i-know-which-operating-core-i-need', 'Official Ophyra knowledge-base thread.

If you sell services, manage contracts, assign tasks, and work with clients, start with Service Operations. If you sell products, manage fulfillment, delivery, tracking, or store orders, start with Store + Logistics.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How do I know which operating core I need?' OR slug = 'how-do-i-know-which-operating-core-i-need');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-do-i-know-which-operating-core-i-need' OR title = 'How do I know which operating core I need?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'If you sell services, manage contracts, assign tasks, and work with clients, start with Service Operations. If you sell products, manage fulfillment, delivery, tracking, or store orders, start with Store + Logistics.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'If you sell services, manage contracts, assign tasks, and work with clients, start with Service Operations. If you sell products, manage fulfillment, delivery, tracking, or store orders, start with Store + Logistics.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 14. Can I activate both Service Operations and Store + Logistics?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Can I activate both Service Operations and Store + Logistics?', 'can-i-activate-both-service-operations-and-store-logistics', 'Official Ophyra knowledge-base thread.

Yes. If your business sells both services and products, you can activate both operating cores. Shared tools like CRM, Team, Tasks, Chat, and Reports should then support both contexts.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Can I activate both Service Operations and Store + Logistics?' OR slug = 'can-i-activate-both-service-operations-and-store-logistics');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'can-i-activate-both-service-operations-and-store-logistics' OR title = 'Can I activate both Service Operations and Store + Logistics?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Yes. If your business sells both services and products, you can activate both operating cores. Shared tools like CRM, Team, Tasks, Chat, and Reports should then support both contexts.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Yes. If your business sells both services and products, you can activate both operating cores. Shared tools like CRM, Team, Tasks, Chat, and Reports should then support both contexts.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 15. What is the Marketplace used for?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the Marketplace used for?', 'what-is-the-marketplace-used-for', 'Official Ophyra knowledge-base thread.

The Marketplace is where you review, activate, renew, and manage modules. It should not be confused with your operational dashboard, which is where you actually work after modules are active.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the Marketplace used for?' OR slug = 'what-is-the-marketplace-used-for');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-marketplace-used-for' OR title = 'What is the Marketplace used for?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'The Marketplace is where you review, activate, renew, and manage modules. It should not be confused with your operational dashboard, which is where you actually work after modules are active.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'The Marketplace is where you review, activate, renew, and manage modules. It should not be confused with your operational dashboard, which is where you actually work after modules are active.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 16. How should I decide which module to activate first?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How should I decide which module to activate first?', 'how-should-i-decide-which-module-to-activate-first', 'Official Ophyra knowledge-base thread.

Start with the part of your business that creates the most operational pressure. If client follow-up, contracts, and service orders are the issue, activate Service Operations. If products, fulfillment, and delivery are the issue, activate Store + Logistics.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How should I decide which module to activate first?' OR slug = 'how-should-i-decide-which-module-to-activate-first');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-should-i-decide-which-module-to-activate-first' OR title = 'How should I decide which module to activate first?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Start with the part of your business that creates the most operational pressure. If client follow-up, contracts, and service orders are the issue, activate Service Operations. If products, fulfillment, and delivery are the issue, activate Store + Logistics.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Start with the part of your business that creates the most operational pressure. If client follow-up, contracts, and service orders are the issue, activate Service Operations. If products, fulfillment, and delivery are the issue, activate Store + Logistics.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 17. Why should I complete my business profile before activating modules?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why should I complete my business profile before activating modules?', 'why-should-i-complete-my-business-profile-before-activating-modules', 'Official Ophyra knowledge-base thread.

A complete profile helps your business look more trustworthy and gives the system better context. It also prepares your public-facing presence before customers interact with forms, products, orders, or service requests.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why should I complete my business profile before activating modules?' OR slug = 'why-should-i-complete-my-business-profile-before-activating-modules');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-should-i-complete-my-business-profile-before-activating-modules' OR title = 'Why should I complete my business profile before activating modules?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'A complete profile helps your business look more trustworthy and gives the system better context. It also prepares your public-facing presence before customers interact with forms, products, orders, or service requests.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'A complete profile helps your business look more trustworthy and gives the system better context. It also prepares your public-facing presence before customers interact with forms, products, orders, or service requests.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 18. What is the best way to organize customer information?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the best way to organize customer information?', 'what-is-the-best-way-to-organize-customer-information', 'Official Ophyra knowledge-base thread.

Keep customer information connected to real interactions: orders, service requests, purchases, contracts, chats, and payments. Avoid creating duplicate customer records when the same person interacts with your business in multiple ways.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the best way to organize customer information?' OR slug = 'what-is-the-best-way-to-organize-customer-information');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-best-way-to-organize-customer-information' OR title = 'What is the best way to organize customer information?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Keep customer information connected to real interactions: orders, service requests, purchases, contracts, chats, and payments. Avoid creating duplicate customer records when the same person interacts with your business in multiple ways.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Keep customer information connected to real interactions: orders, service requests, purchases, contracts, chats, and payments. Avoid creating duplicate customer records when the same person interacts with your business in multiple ways.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 19. Why is it important to track tasks inside an order?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why is it important to track tasks inside an order?', 'why-is-it-important-to-track-tasks-inside-an-order', 'Official Ophyra knowledge-base thread.

Tasks help turn an order into a clear execution workflow. They show who is responsible, what must happen next, what is completed, and where the operation may be blocked.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why is it important to track tasks inside an order?' OR slug = 'why-is-it-important-to-track-tasks-inside-an-order');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-is-it-important-to-track-tasks-inside-an-order' OR title = 'Why is it important to track tasks inside an order?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Tasks help turn an order into a clear execution workflow. They show who is responsible, what must happen next, what is completed, and where the operation may be blocked.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Tasks help turn an order into a clear execution workflow. They show who is responsible, what must happen next, what is completed, and where the operation may be blocked.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 20. How can small businesses avoid losing track of orders?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How can small businesses avoid losing track of orders?', 'how-can-small-businesses-avoid-losing-track-of-orders', 'Official Ophyra knowledge-base thread.

Use statuses, assigned team members, due dates, and clear internal notes. Every order should have a current status and a next action so it does not depend only on memory or chat messages.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How can small businesses avoid losing track of orders?' OR slug = 'how-can-small-businesses-avoid-losing-track-of-orders');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-can-small-businesses-avoid-losing-track-of-orders' OR title = 'How can small businesses avoid losing track of orders?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Use statuses, assigned team members, due dates, and clear internal notes. Every order should have a current status and a next action so it does not depend only on memory or chat messages.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Use statuses, assigned team members, due dates, and clear internal notes. Every order should have a current status and a next action so it does not depend only on memory or chat messages.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 21. Why should delivery and preparation be separated into tasks?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why should delivery and preparation be separated into tasks?', 'why-should-delivery-and-preparation-be-separated-into-tasks', 'Official Ophyra knowledge-base thread.

Preparation and delivery are different operational steps. Separating them helps the team know when an order is ready, who is responsible for each part, and whether delivery should wait until preparation is completed.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why should delivery and preparation be separated into tasks?' OR slug = 'why-should-delivery-and-preparation-be-separated-into-tasks');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-should-delivery-and-preparation-be-separated-into-tasks' OR title = 'Why should delivery and preparation be separated into tasks?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Preparation and delivery are different operational steps. Separating them helps the team know when an order is ready, who is responsible for each part, and whether delivery should wait until preparation is completed.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Preparation and delivery are different operational steps. Separating them helps the team know when an order is ready, who is responsible for each part, and whether delivery should wait until preparation is completed.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 22. How can I use reports without overcomplicating my business?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How can I use reports without overcomplicating my business?', 'how-can-i-use-reports-without-overcomplicating-my-business', 'Official Ophyra knowledge-base thread.

Focus first on simple reports: sales, pending orders, completed orders, active clients, unpaid balances, and team activity. These reports help you make better decisions without creating unnecessary complexity.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How can I use reports without overcomplicating my business?' OR slug = 'how-can-i-use-reports-without-overcomplicating-my-business');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-can-i-use-reports-without-overcomplicating-my-business' OR title = 'How can I use reports without overcomplicating my business?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Focus first on simple reports: sales, pending orders, completed orders, active clients, unpaid balances, and team activity. These reports help you make better decisions without creating unnecessary complexity.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Focus first on simple reports: sales, pending orders, completed orders, active clients, unpaid balances, and team activity. These reports help you make better decisions without creating unnecessary complexity.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 23. What should I review every morning in my dashboard?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I review every morning in my dashboard?', 'what-should-i-review-every-morning-in-my-dashboard', 'Official Ophyra knowledge-base thread.

Review pending orders, today''s tasks, unpaid balances, upcoming events or deliveries, assigned team members, and any customer messages that need a response.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I review every morning in my dashboard?' OR slug = 'what-should-i-review-every-morning-in-my-dashboard');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-review-every-morning-in-my-dashboard' OR title = 'What should I review every morning in my dashboard?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Review pending orders, today''s tasks, unpaid balances, upcoming events or deliveries, assigned team members, and any customer messages that need a response.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Review pending orders, today''s tasks, unpaid balances, upcoming events or deliveries, assigned team members, and any customer messages that need a response.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 24. What is the biggest mistake businesses make with operations software?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the biggest mistake businesses make with operations software?', 'what-is-the-biggest-mistake-businesses-make-with-operations-software', 'Official Ophyra knowledge-base thread.

The biggest mistake is using the software only as a storage tool. Ophyra works best when orders, clients, tasks, payments, team assignments, and statuses are actively updated.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the biggest mistake businesses make with operations software?' OR slug = 'what-is-the-biggest-mistake-businesses-make-with-operations-software');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-biggest-mistake-businesses-make-with-operations-software' OR title = 'What is the biggest mistake businesses make with operations software?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'The biggest mistake is using the software only as a storage tool. Ophyra works best when orders, clients, tasks, payments, team assignments, and statuses are actively updated.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'The biggest mistake is using the software only as a storage tool. Ophyra works best when orders, clients, tasks, payments, team assignments, and statuses are actively updated.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 25. How often should I update order status?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How often should I update order status?', 'how-often-should-i-update-order-status', 'Official Ophyra knowledge-base thread.

Order status should be updated whenever a meaningful step changes: payment received, preparation started, preparation completed, delivery started, delivered, completed, cancelled, or refunded.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How often should I update order status?' OR slug = 'how-often-should-i-update-order-status');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-often-should-i-update-order-status' OR title = 'How often should I update order status?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Order status should be updated whenever a meaningful step changes: payment received, preparation started, preparation completed, delivery started, delivered, completed, cancelled, or refunded.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Order status should be updated whenever a meaningful step changes: payment received, preparation started, preparation completed, delivery started, delivered, completed, cancelled, or refunded.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 26. How should I structure a service business inside Ophyra?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How should I structure a service business inside Ophyra?', 'how-should-i-structure-a-service-business-inside-ophyra', 'Official Ophyra knowledge-base thread.

Start with your client journey: lead, quote, order, contract, payment, task assignment, execution, completion, and follow-up. Then configure your workspace around those stages.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How should I structure a service business inside Ophyra?' OR slug = 'how-should-i-structure-a-service-business-inside-ophyra');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-should-i-structure-a-service-business-inside-ophyra' OR title = 'How should I structure a service business inside Ophyra?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Start with your client journey: lead, quote, order, contract, payment, task assignment, execution, completion, and follow-up. Then configure your workspace around those stages.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Start with your client journey: lead, quote, order, contract, payment, task assignment, execution, completion, and follow-up. Then configure your workspace around those stages.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 27. How should I structure a product or food business inside Ophyra?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How should I structure a product or food business inside Ophyra?', 'how-should-i-structure-a-product-or-food-business-inside-ophyra', 'Official Ophyra knowledge-base thread.

Start with products, orders, preparation, fulfillment, delivery, tracking, and customer follow-up. Store + Logistics should help you manage the movement from purchase to delivery.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How should I structure a product or food business inside Ophyra?' OR slug = 'how-should-i-structure-a-product-or-food-business-inside-ophyra');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-should-i-structure-a-product-or-food-business-inside-ophyra' OR title = 'How should I structure a product or food business inside Ophyra?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Start with products, orders, preparation, fulfillment, delivery, tracking, and customer follow-up. Store + Logistics should help you manage the movement from purchase to delivery.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Start with products, orders, preparation, fulfillment, delivery, tracking, and customer follow-up. Store + Logistics should help you manage the movement from purchase to delivery.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 28. When should a business use both operating cores?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'When should a business use both operating cores?', 'when-should-a-business-use-both-operating-cores', 'Official Ophyra knowledge-base thread.

A business should use both operating cores when it sells products and services at the same time. For example, an event company may sell planning services and also sell products, food boxes, rentals, or delivery items.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'When should a business use both operating cores?' OR slug = 'when-should-a-business-use-both-operating-cores');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'when-should-a-business-use-both-operating-cores' OR title = 'When should a business use both operating cores?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'A business should use both operating cores when it sells products and services at the same time. For example, an event company may sell planning services and also sell products, food boxes, rentals, or delivery items.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'A business should use both operating cores when it sells products and services at the same time. For example, an event company may sell planning services and also sell products, food boxes, rentals, or delivery items.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 29. How should I think about CRM in Ophyra?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How should I think about CRM in Ophyra?', 'how-should-i-think-about-crm-in-ophyra', 'Official Ophyra knowledge-base thread.

CRM should not be a disconnected contact list. It should show customers in relation to orders, purchases, contracts, chats, payments, and tasks. A customer may have service history, store history, or both.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How should I think about CRM in Ophyra?' OR slug = 'how-should-i-think-about-crm-in-ophyra');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-should-i-think-about-crm-in-ophyra' OR title = 'How should I think about CRM in Ophyra?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'CRM should not be a disconnected contact list. It should show customers in relation to orders, purchases, contracts, chats, payments, and tasks. A customer may have service history, store history, or both.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'CRM should not be a disconnected contact list. It should show customers in relation to orders, purchases, contracts, chats, payments, and tasks. A customer may have service history, store history, or both.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 30. How can team members be used correctly?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How can team members be used correctly?', 'how-can-team-members-be-used-correctly', 'Official Ophyra knowledge-base thread.

Team members should be assigned to specific tasks, orders, deliveries, or service steps. Their work should be connected to business context, task status, and, when applicable, payroll or hours.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How can team members be used correctly?' OR slug = 'how-can-team-members-be-used-correctly');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-can-team-members-be-used-correctly' OR title = 'How can team members be used correctly?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Team members should be assigned to specific tasks, orders, deliveries, or service steps. Their work should be connected to business context, task status, and, when applicable, payroll or hours.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Team members should be assigned to specific tasks, orders, deliveries, or service steps. Their work should be connected to business context, task status, and, when applicable, payroll or hours.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 31. What is the role of task evidence?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the role of task evidence?', 'what-is-the-role-of-task-evidence', 'Official Ophyra knowledge-base thread.

Task evidence helps prove that work was completed. A photo of a prepared order, delivered package, installed setup, or completed service gives managers and customers more confidence.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the role of task evidence?' OR slug = 'what-is-the-role-of-task-evidence');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-role-of-task-evidence' OR title = 'What is the role of task evidence?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Task evidence helps prove that work was completed. A photo of a prepared order, delivered package, installed setup, or completed service gives managers and customers more confidence.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Task evidence helps prove that work was completed. A photo of a prepared order, delivered package, installed setup, or completed service gives managers and customers more confidence.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 32. How should a business use public order status links?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How should a business use public order status links?', 'how-should-a-business-use-public-order-status-links', 'Official Ophyra knowledge-base thread.

Public order status links reduce customer confusion. They allow customers to see whether an order is paid, preparing, packed, shipped, delivered, or completed without needing to ask repeatedly.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How should a business use public order status links?' OR slug = 'how-should-a-business-use-public-order-status-links');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-should-a-business-use-public-order-status-links' OR title = 'How should a business use public order status links?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Public order status links reduce customer confusion. They allow customers to see whether an order is paid, preparing, packed, shipped, delivered, or completed without needing to ask repeatedly.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Public order status links reduce customer confusion. They allow customers to see whether an order is paid, preparing, packed, shipped, delivered, or completed without needing to ask repeatedly.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 33. What should I do before adding external marketplace orders?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do before adding external marketplace orders?', 'what-should-i-do-before-adding-external-marketplace-orders', 'Official Ophyra knowledge-base thread.

First make sure your internal order workflow is solid. Store orders, tasks, statuses, evidence, and public tracking should work properly before importing orders from outside platforms.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do before adding external marketplace orders?' OR slug = 'what-should-i-do-before-adding-external-marketplace-orders');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-before-adding-external-marketplace-orders' OR title = 'What should I do before adding external marketplace orders?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'First make sure your internal order workflow is solid. Store orders, tasks, statuses, evidence, and public tracking should work properly before importing orders from outside platforms.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'First make sure your internal order workflow is solid. Store orders, tasks, statuses, evidence, and public tracking should work properly before importing orders from outside platforms.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 34. Why should I avoid creating duplicate customers?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why should I avoid creating duplicate customers?', 'why-should-i-avoid-creating-duplicate-customers', 'Official Ophyra knowledge-base thread.

Duplicate customers make reports, communication, and order history unreliable. It is better to connect one global user or customer record to multiple business interactions when possible.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why should I avoid creating duplicate customers?' OR slug = 'why-should-i-avoid-creating-duplicate-customers');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-should-i-avoid-creating-duplicate-customers' OR title = 'Why should I avoid creating duplicate customers?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Duplicate customers make reports, communication, and order history unreliable. It is better to connect one global user or customer record to multiple business interactions when possible.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Duplicate customers make reports, communication, and order history unreliable. It is better to connect one global user or customer record to multiple business interactions when possible.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 35. How can I improve follow-up with clients?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How can I improve follow-up with clients?', 'how-can-i-improve-follow-up-with-clients', 'Official Ophyra knowledge-base thread.

Use CRM notes, order statuses, reminders, and chat context. Follow-up should be connected to what the customer actually requested, purchased, signed, or paid.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How can I improve follow-up with clients?' OR slug = 'how-can-i-improve-follow-up-with-clients');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-can-i-improve-follow-up-with-clients' OR title = 'How can I improve follow-up with clients?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Use CRM notes, order statuses, reminders, and chat context. Follow-up should be connected to what the customer actually requested, purchased, signed, or paid.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Use CRM notes, order statuses, reminders, and chat context. Follow-up should be connected to what the customer actually requested, purchased, signed, or paid.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 36. What should I do if a module appears active but I did not pay for it?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if a module appears active but I did not pay for it?', 'what-should-i-do-if-a-module-appears-active-but-i-did-not-pay-for-it', 'Official Ophyra knowledge-base thread.

Report it to the Ophyra team. Paid modules should only become active after confirmed payment or manual activation by an authorized Ophyra admin.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if a module appears active but I did not pay for it?' OR slug = 'what-should-i-do-if-a-module-appears-active-but-i-did-not-pay-for-it');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-a-module-appears-active-but-i-did-not-pay-for-it' OR title = 'What should I do if a module appears active but I did not pay for it?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report it to the Ophyra team. Paid modules should only become active after confirmed payment or manual activation by an authorized Ophyra admin.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report it to the Ophyra team. Paid modules should only become active after confirmed payment or manual activation by an authorized Ophyra admin.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 37. What should I do if checkout only shows USD?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if checkout only shows USD?', 'what-should-i-do-if-checkout-only-shows-usd', 'Official Ophyra knowledge-base thread.

Report the issue. Ophyra billing should support the configured currencies. If only USD appears, the currency selector, pricing configuration, or Stripe checkout setup may need review.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if checkout only shows USD?' OR slug = 'what-should-i-do-if-checkout-only-shows-usd');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-checkout-only-shows-usd' OR title = 'What should I do if checkout only shows USD?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report the issue. Ophyra billing should support the configured currencies. If only USD appears, the currency selector, pricing configuration, or Stripe checkout setup may need review.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report the issue. Ophyra billing should support the configured currencies. If only USD appears, the currency selector, pricing configuration, or Stripe checkout setup may need review.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 38. What should I do if I click calendar view and the orders page goes blank?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if I click calendar view and the orders page goes blank?', 'what-should-i-do-if-i-click-calendar-view-and-the-orders-page-goes-blank', 'Official Ophyra knowledge-base thread.

Report the issue with your user role and browser. The order calendar view should work without breaking the table view.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if I click calendar view and the orders page goes blank?' OR slug = 'what-should-i-do-if-i-click-calendar-view-and-the-orders-page-goes-blank');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-i-click-calendar-view-and-the-orders-page-goes-blank' OR title = 'What should I do if I click calendar view and the orders page goes blank?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report the issue with your user role and browser. The order calendar view should work without breaking the table view.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report the issue with your user role and browser. The order calendar view should work without breaking the table view.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 39. What should I report if I see VNV Events content inside Ophyra Platform?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I report if I see VNV Events content inside Ophyra Platform?', 'what-should-i-report-if-i-see-vnv-events-content-inside-ophyra-platform', 'Official Ophyra knowledge-base thread.

Report the page and content you saw. Ophyra Platform should not show private VNV Events content by default. Content must respect the active business or project context.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I report if I see VNV Events content inside Ophyra Platform?' OR slug = 'what-should-i-report-if-i-see-vnv-events-content-inside-ophyra-platform');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-report-if-i-see-vnv-events-content-inside-ophyra-platform' OR title = 'What should I report if I see VNV Events content inside Ophyra Platform?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report the page and content you saw. Ophyra Platform should not show private VNV Events content by default. Content must respect the active business or project context.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report the page and content you saw. Ophyra Platform should not show private VNV Events content by default. Content must respect the active business or project context.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 40. What should I do if a locked module opens anyway?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if a locked module opens anyway?', 'what-should-i-do-if-a-locked-module-opens-anyway', 'Official Ophyra knowledge-base thread.

Report it immediately. Locked modules should not allow access unless the correct operating core or add-on is active.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if a locked module opens anyway?' OR slug = 'what-should-i-do-if-a-locked-module-opens-anyway');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-a-locked-module-opens-anyway' OR title = 'What should I do if a locked module opens anyway?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report it immediately. Locked modules should not allow access unless the correct operating core or add-on is active.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report it immediately. Locked modules should not allow access unless the correct operating core or add-on is active.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 41. What should I do if my sidebar changes unexpectedly?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if my sidebar changes unexpectedly?', 'what-should-i-do-if-my-sidebar-changes-unexpectedly', 'Official Ophyra knowledge-base thread.

Report your user role, active business context, and the page where it happened. Sidebar navigation should depend on your role, associations, and active business context.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if my sidebar changes unexpectedly?' OR slug = 'what-should-i-do-if-my-sidebar-changes-unexpectedly');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-my-sidebar-changes-unexpectedly' OR title = 'What should I do if my sidebar changes unexpectedly?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report your user role, active business context, and the page where it happened. Sidebar navigation should depend on your role, associations, and active business context.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report your user role, active business context, and the page where it happened. Sidebar navigation should depend on your role, associations, and active business context.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 42. What should I do if a payment succeeds but the module stays locked?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if a payment succeeds but the module stays locked?', 'what-should-i-do-if-a-payment-succeeds-but-the-module-stays-locked', 'Official Ophyra knowledge-base thread.

Report the payment date, module, and currency. The team may need to verify the payment record, Stripe webhook, module activation, and renewal date.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if a payment succeeds but the module stays locked?' OR slug = 'what-should-i-do-if-a-payment-succeeds-but-the-module-stays-locked');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-a-payment-succeeds-but-the-module-stays-locked' OR title = 'What should I do if a payment succeeds but the module stays locked?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report the payment date, module, and currency. The team may need to verify the payment record, Stripe webhook, module activation, and renewal date.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report the payment date, module, and currency. The team may need to verify the payment record, Stripe webhook, module activation, and renewal date.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 43. What should I do if a payment fails but the module becomes active?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if a payment fails but the module becomes active?', 'what-should-i-do-if-a-payment-fails-but-the-module-becomes-active', 'Official Ophyra knowledge-base thread.

Report it immediately. Modules should not activate after failed or cancelled payments. This may indicate a checkout or webhook issue.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if a payment fails but the module becomes active?' OR slug = 'what-should-i-do-if-a-payment-fails-but-the-module-becomes-active');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-a-payment-fails-but-the-module-becomes-active' OR title = 'What should I do if a payment fails but the module becomes active?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report it immediately. Modules should not activate after failed or cancelled payments. This may indicate a checkout or webhook issue.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report it immediately. Modules should not activate after failed or cancelled payments. This may indicate a checkout or webhook issue.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 44. What should I do if I see customers from another business?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if I see customers from another business?', 'what-should-i-do-if-i-see-customers-from-another-business', 'Official Ophyra knowledge-base thread.

Report the page and business context. Customer and order data must be scoped by association, business, order, or active context. Users should not see data from unrelated businesses.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if I see customers from another business?' OR slug = 'what-should-i-do-if-i-see-customers-from-another-business');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-i-see-customers-from-another-business' OR title = 'What should I do if I see customers from another business?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Report the page and business context. Customer and order data must be scoped by association, business, order, or active context. Users should not see data from unrelated businesses.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Report the page and business context. Customer and order data must be scoped by association, business, order, or active context. Users should not see data from unrelated businesses.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 45. What should I do if search in the community does not find an answer?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-issues-improvement-reports' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I do if search in the community does not find an answer?', 'what-should-i-do-if-search-in-the-community-does-not-find-an-answer', 'Official Ophyra knowledge-base thread.

Try different keywords. If you still cannot find an answer, create a new thread. Your thread may require approval before being published.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I do if search in the community does not find an answer?' OR slug = 'what-should-i-do-if-search-in-the-community-does-not-find-an-answer');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-do-if-search-in-the-community-does-not-find-an-answer' OR title = 'What should I do if search in the community does not find an answer?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Try different keywords. If you still cannot find an answer, create a new thread. Your thread may require approval before being published.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Try different keywords. If you still cannot find an answer, create a new thread. Your thread may require approval before being published.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 46. How does a client relate to multiple businesses?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How does a client relate to multiple businesses?', 'how-does-a-client-relate-to-multiple-businesses', 'Official Ophyra knowledge-base thread.

A client is a global user who can interact with multiple businesses. The relationship is created through orders, purchases, requests, contracts, chats, or associations. The first company that created the client does not permanently own that user.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How does a client relate to multiple businesses?' OR slug = 'how-does-a-client-relate-to-multiple-businesses');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-does-a-client-relate-to-multiple-businesses' OR title = 'How does a client relate to multiple businesses?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'A client is a global user who can interact with multiple businesses. The relationship is created through orders, purchases, requests, contracts, chats, or associations. The first company that created the client does not permanently own that user.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'A client is a global user who can interact with multiple businesses. The relationship is created through orders, purchases, requests, contracts, chats, or associations. The first company that created the client does not permanently own that user.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 47. How does a team member relate to a business?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How does a team member relate to a business?', 'how-does-a-team-member-relate-to-a-business', 'Official Ophyra knowledge-base thread.

A team member works inside a business context. Their tasks, orders, chats, payroll, and permissions depend on the company they are currently working for.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How does a team member relate to a business?' OR slug = 'how-does-a-team-member-relate-to-a-business');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-does-a-team-member-relate-to-a-business' OR title = 'How does a team member relate to a business?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'A team member works inside a business context. Their tasks, orders, chats, payroll, and permissions depend on the company they are currently working for.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'A team member works inside a business context. Their tasks, orders, chats, payroll, and permissions depend on the company they are currently working for.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 48. Can a client later create their own business?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Can a client later create their own business?', 'can-a-client-later-create-their-own-business', 'Official Ophyra knowledge-base thread.

Yes. A user who started as a client can later create a business and become a business owner without losing their previous history as a client.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Can a client later create their own business?' OR slug = 'can-a-client-later-create-their-own-business');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'can-a-client-later-create-their-own-business' OR title = 'Can a client later create their own business?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Yes. A user who started as a client can later create a business and become a business owner without losing their previous history as a client.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Yes. A user who started as a client can later create a business and become a business owner without losing their previous history as a client.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 49. Can a team member later create their own business?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Can a team member later create their own business?', 'can-a-team-member-later-create-their-own-business', 'Official Ophyra knowledge-base thread.

Yes. A team member can later create a business and become a business owner while keeping their previous team member associations.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Can a team member later create their own business?' OR slug = 'can-a-team-member-later-create-their-own-business');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'can-a-team-member-later-create-their-own-business' OR title = 'Can a team member later create their own business?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Yes. A team member can later create a business and become a business owner while keeping their previous team member associations.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Yes. A team member can later create a business and become a business owner while keeping their previous team member associations.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 50. Why does Ophyra use business context?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'Why does Ophyra use business context?', 'why-does-ophyra-use-business-context', 'Official Ophyra knowledge-base thread.

Business context keeps data separated. It helps make sure orders, customers, products, tasks, chats, payroll, CMS pages, and private content belong to the correct business.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'Why does Ophyra use business context?' OR slug = 'why-does-ophyra-use-business-context');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'why-does-ophyra-use-business-context' OR title = 'Why does Ophyra use business context?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Business context keeps data separated. It helps make sure orders, customers, products, tasks, chats, payroll, CMS pages, and private content belong to the correct business.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Business context keeps data separated. It helps make sure orders, customers, products, tasks, chats, payroll, CMS pages, and private content belong to the correct business.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 51. What is the difference between Ophyra Platform and a business workspace?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the difference between Ophyra Platform and a business workspace?', 'what-is-the-difference-between-ophyra-platform-and-a-business-workspace', 'Official Ophyra knowledge-base thread.

Ophyra Platform manages the SaaS system, memberships, modules, billing, tenants, and global settings. A business workspace manages a specific business operation such as services, store orders, team, products, or customers.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the difference between Ophyra Platform and a business workspace?' OR slug = 'what-is-the-difference-between-ophyra-platform-and-a-business-workspace');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-difference-between-ophyra-platform-and-a-business-workspace' OR title = 'What is the difference between Ophyra Platform and a business workspace?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Ophyra Platform manages the SaaS system, memberships, modules, billing, tenants, and global settings. A business workspace manages a specific business operation such as services, store orders, team, products, or customers.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Ophyra Platform manages the SaaS system, memberships, modules, billing, tenants, and global settings. A business workspace manages a specific business operation such as services, store orders, team, products, or customers.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 52. What is the difference between a public profile and a public order link?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the difference between a public profile and a public order link?', 'what-is-the-difference-between-a-public-profile-and-a-public-order-link', 'Official Ophyra knowledge-base thread.

A public profile shows business information. A public order link shows the status, payment, tracking, or progress of a specific order.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the difference between a public profile and a public order link?' OR slug = 'what-is-the-difference-between-a-public-profile-and-a-public-order-link');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-difference-between-a-public-profile-and-a-public-order-link' OR title = 'What is the difference between a public profile and a public order link?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'A public profile shows business information. A public order link shows the status, payment, tracking, or progress of a specific order.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'A public profile shows business information. A public order link shows the status, payment, tracking, or progress of a specific order.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 53. What should appear in a public order status link?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should appear in a public order status link?', 'what-should-appear-in-a-public-order-status-link', 'Official Ophyra knowledge-base thread.

A public order status link should show order summary, payment status, preparation or service progress, delivery or tracking status, and approved customer-visible evidence when available.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should appear in a public order status link?' OR slug = 'what-should-appear-in-a-public-order-status-link');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-appear-in-a-public-order-status-link' OR title = 'What should appear in a public order status link?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'A public order status link should show order summary, payment status, preparation or service progress, delivery or tracking status, and approved customer-visible evidence when available.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'A public order status link should show order summary, payment status, preparation or service progress, delivery or tracking status, and approved customer-visible evidence when available.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 54. What is Advanced Storage / QR Inventory for?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is Advanced Storage / QR Inventory for?', 'what-is-advanced-storage-qr-inventory-for', 'Official Ophyra knowledge-base thread.

Advanced Storage / QR Inventory is for businesses that need deeper physical tracking, such as containers, equipment, rentals, QR labels, storage locations, or warehouse-style organization.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is Advanced Storage / QR Inventory for?' OR slug = 'what-is-advanced-storage-qr-inventory-for');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-advanced-storage-qr-inventory-for' OR title = 'What is Advanced Storage / QR Inventory for?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Advanced Storage / QR Inventory is for businesses that need deeper physical tracking, such as containers, equipment, rentals, QR labels, storage locations, or warehouse-style organization.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Advanced Storage / QR Inventory is for businesses that need deeper physical tracking, such as containers, equipment, rentals, QR labels, storage locations, or warehouse-style organization.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 55. What is AI Advisor for?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is AI Advisor for?', 'what-is-ai-advisor-for', 'Official Ophyra knowledge-base thread.

AI Advisor helps with ideas, summaries, operational suggestions, content support, and recommendations. It should work in context, such as platform, service operations, or store operations.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is AI Advisor for?' OR slug = 'what-is-ai-advisor-for');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-ai-advisor-for' OR title = 'What is AI Advisor for?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'AI Advisor helps with ideas, summaries, operational suggestions, content support, and recommendations. It should work in context, such as platform, service operations, or store operations.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'AI Advisor helps with ideas, summaries, operational suggestions, content support, and recommendations. It should work in context, such as platform, service operations, or store operations.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 56. What is Ticket Sales + RSVP for?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'system-how-to' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is Ticket Sales + RSVP for?', 'what-is-ticket-sales-rsvp-for', 'Official Ophyra knowledge-base thread.

Ticket Sales + RSVP is for events, registrations, attendance, RSVP lists, and ticket workflows. It should only be active when the module is enabled.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is Ticket Sales + RSVP for?' OR slug = 'what-is-ticket-sales-rsvp-for');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-ticket-sales-rsvp-for' OR title = 'What is Ticket Sales + RSVP for?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Ticket Sales + RSVP is for events, registrations, attendance, RSVP lists, and ticket workflows. It should only be active when the module is enabled.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Ticket Sales + RSVP is for events, registrations, attendance, RSVP lists, and ticket workflows. It should only be active when the module is enabled.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 57. How can I know if my business is ready for automation?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How can I know if my business is ready for automation?', 'how-can-i-know-if-my-business-is-ready-for-automation', 'Official Ophyra knowledge-base thread.

Your business is ready for automation when your process is already clear. First define orders, statuses, tasks, team roles, payment steps, and customer communication. Then automation can help reduce repetitive work.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How can I know if my business is ready for automation?' OR slug = 'how-can-i-know-if-my-business-is-ready-for-automation');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-can-i-know-if-my-business-is-ready-for-automation' OR title = 'How can I know if my business is ready for automation?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Your business is ready for automation when your process is already clear. First define orders, statuses, tasks, team roles, payment steps, and customer communication. Then automation can help reduce repetitive work.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Your business is ready for automation when your process is already clear. First define orders, statuses, tasks, team roles, payment steps, and customer communication. Then automation can help reduce repetitive work.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 58. How can I reduce customer confusion?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'business-owner-support' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'How can I reduce customer confusion?', 'how-can-i-reduce-customer-confusion', 'Official Ophyra knowledge-base thread.

Keep customers informed with clear statuses, payment links, public order tracking, confirmations, and timely updates. Confusion usually happens when customers do not know what step comes next.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'How can I reduce customer confusion?' OR slug = 'how-can-i-reduce-customer-confusion');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'how-can-i-reduce-customer-confusion' OR title = 'How can I reduce customer confusion?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Keep customers informed with clear statuses, payment links, public order tracking, confirmations, and timely updates. Confusion usually happens when customers do not know what step comes next.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Keep customers informed with clear statuses, payment links, public order tracking, confirmations, and timely updates. Confusion usually happens when customers do not know what step comes next.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 59. What is the best way to onboard my team into Ophyra?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What is the best way to onboard my team into Ophyra?', 'what-is-the-best-way-to-onboard-my-team-into-ophyra', 'Official Ophyra knowledge-base thread.

Start with simple responsibilities. Assign team members to real tasks, teach them how to update status, require evidence when needed, and review completed work regularly.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What is the best way to onboard my team into Ophyra?' OR slug = 'what-is-the-best-way-to-onboard-my-team-into-ophyra');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-is-the-best-way-to-onboard-my-team-into-ophyra' OR title = 'What is the best way to onboard my team into Ophyra?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Start with simple responsibilities. Assign team members to real tasks, teach them how to update status, require evidence when needed, and review completed work regularly.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Start with simple responsibilities. Assign team members to real tasks, teach them how to update status, require evidence when needed, and review completed work regularly.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

-- 60. What should I document inside my business process?
SET @category_id := (SELECT id FROM forum_categories WHERE slug = 'consultant-advice' LIMIT 1);
INSERT INTO forum_topics (id_category, id_user, title, slug, content, is_pinned, is_locked, is_approved, status, is_official, approved_by, approved_at, views_count, replies_count, likes_count, site_key, created_at, updated_at)
SELECT @category_id, @ophyra_seed_author_id, 'What should I document inside my business process?', 'what-should-i-document-inside-my-business-process', 'Official Ophyra knowledge-base thread.

Document your customer journey, order stages, task responsibilities, payment process, delivery process, cancellation rules, and follow-up steps. The clearer your process is, the easier Ophyra is to configure.', 0, 0, 1, 'approved', 1, @ophyra_seed_author_id, NOW(), 0, 1, 0, 'ophyra', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM forum_topics WHERE title = 'What should I document inside my business process?' OR slug = 'what-should-i-document-inside-my-business-process');
SET @topic_id := (SELECT id FROM forum_topics WHERE slug = 'what-should-i-document-inside-my-business-process' OR title = 'What should I document inside my business process?' LIMIT 1);
INSERT INTO forum_replies (id_topic, id_user, id_parent_reply, content, is_approved, status, is_best_answer, is_official_answer, marked_official_by, marked_official_at, likes_count, site_key, created_at, updated_at)
SELECT @topic_id, @ophyra_seed_author_id, NULL, 'Document your customer journey, order stages, task responsibilities, payment process, delivery process, cancellation rules, and follow-up steps. The clearer your process is, the easier Ophyra is to configure.', 1, 'official', 1, 1, @ophyra_seed_author_id, NOW(), 0, 'ophyra', NOW(), NOW()
WHERE @topic_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forum_replies WHERE id_topic = @topic_id AND is_official_answer = 1 AND content = 'Document your customer journey, order stages, task responsibilities, payment process, delivery process, cancellation rules, and follow-up steps. The clearer your process is, the easier Ophyra is to configure.');
UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_replies WHERE id_topic = @topic_id AND is_approved = 1) WHERE id = @topic_id;

COMMIT;
