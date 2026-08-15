-- Ophyra Growth Hub level 1 foundation.
-- Manual review required before execution.
-- Non-destructive: creates/extends central CMS/SEO tables scoped by id_owner + site_key.

START TRANSACTION;

-- Existing installations may already have CMS/media tables. Keep this migration
-- additive so Growth Hub can run on top of older schemas.

CREATE TABLE IF NOT EXISTS growth_sites (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  site_name VARCHAR(180) NOT NULL,
  domain VARCHAR(255) NULL,
  public_base_url VARCHAR(255) NULL,
  default_language VARCHAR(12) NOT NULL DEFAULT 'en',
  secondary_language VARCHAR(12) NULL,
  brand_voice TEXT NULL,
  target_locations JSON NULL,
  main_services JSON NULL,
  main_products JSON NULL,
  excluded_topics JSON NULL,
  allowed_content_types JSON NULL,
  default_cta_label VARCHAR(160) NULL,
  default_cta_url VARCHAR(255) NULL,
  phone VARCHAR(80) NULL,
  contact_email VARCHAR(190) NULL,
  cloudinary_folder VARCHAR(255) NULL,
  openai_model_preferences JSON NULL,
  approval_rules JSON NULL,
  auto_publish_allowed TINYINT(1) NOT NULL DEFAULT 0,
  sitemap_settings JSON NULL,
  route_rules JSON NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY growth_sites_owner_site_unique (id_owner, site_key),
  KEY growth_sites_status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS id_owner INT(11) NOT NULL AFTER id;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS site_key VARCHAR(80) NOT NULL AFTER id_owner;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS site_name VARCHAR(180) NOT NULL AFTER site_key;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS domain VARCHAR(255) NULL AFTER site_name;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS public_base_url VARCHAR(255) NULL AFTER domain;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS default_language VARCHAR(12) NOT NULL DEFAULT 'en' AFTER public_base_url;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS secondary_language VARCHAR(12) NULL AFTER default_language;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS brand_voice TEXT NULL AFTER secondary_language;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS target_locations JSON NULL AFTER brand_voice;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS main_services JSON NULL AFTER target_locations;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS main_products JSON NULL AFTER main_services;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS excluded_topics JSON NULL AFTER main_products;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS allowed_content_types JSON NULL AFTER excluded_topics;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS default_cta_label VARCHAR(160) NULL AFTER allowed_content_types;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS default_cta_url VARCHAR(255) NULL AFTER default_cta_label;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS phone VARCHAR(80) NULL AFTER default_cta_url;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS contact_email VARCHAR(190) NULL AFTER phone;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS cloudinary_folder VARCHAR(255) NULL AFTER contact_email;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS openai_model_preferences JSON NULL AFTER cloudinary_folder;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS approval_rules JSON NULL AFTER openai_model_preferences;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS auto_publish_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER approval_rules;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS sitemap_settings JSON NULL AFTER auto_publish_allowed;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS route_rules JSON NULL AFTER sitemap_settings;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'active' AFTER route_rules;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status;
ALTER TABLE growth_sites ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE growth_sites ADD UNIQUE KEY IF NOT EXISTS growth_sites_owner_site_unique (id_owner, site_key);
ALTER TABLE growth_sites ADD KEY IF NOT EXISTS growth_sites_status_idx (status);

CREATE TABLE IF NOT EXISTS cms_contents (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  content_type VARCHAR(60) NOT NULL DEFAULT 'blog',
  type VARCHAR(60) NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  excerpt TEXT NULL,
  body LONGTEXT NULL,
  body_html LONGTEXT NULL,
  content_json JSON NULL,
  meta_title VARCHAR(255) NULL,
  seo_title VARCHAR(255) NULL,
  meta_description TEXT NULL,
  canonical_url VARCHAR(255) NULL,
  robots VARCHAR(80) NOT NULL DEFAULT 'index, follow',
  featured_image_url TEXT NULL,
  primary_keyword VARCHAR(255) NULL,
  target_location VARCHAR(180) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'DRAFT',
  approval_status VARCHAR(40) NOT NULL DEFAULT 'DRAFT',
  scheduled_at DATETIME NULL,
  published_at DATETIME NULL,
  generated_by_agent TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT(11) NULL,
  approved_by INT(11) NULL,
  schema_json JSON NULL,
  metadata_json JSON NULL,
  id_user_business INT(11) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_contents_owner_site_slug_unique (id_owner, site_key, slug),
  KEY cms_contents_owner_site_status_idx (id_owner, site_key, status),
  KEY cms_contents_type_idx (content_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS id_owner INT(11) NULL AFTER id;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS id_template INT(11) NULL AFTER id_owner;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS id_cms_category INT(11) NULL AFTER id_template;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS site_key VARCHAR(80) NULL AFTER id_owner;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS content_type VARCHAR(60) NOT NULL DEFAULT 'blog' AFTER site_key;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS type VARCHAR(60) NULL AFTER content_type;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS title VARCHAR(255) NULL AFTER content_type;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS slug VARCHAR(220) NULL AFTER title;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS excerpt TEXT NULL AFTER slug;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS body LONGTEXT NULL AFTER excerpt;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS body_html LONGTEXT NULL AFTER body;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS content_json JSON NULL AFTER body_html;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) NULL AFTER content_json;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS seo_title VARCHAR(255) NULL AFTER body;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS meta_description TEXT NULL AFTER seo_title;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(255) NULL AFTER meta_description;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS robots VARCHAR(80) NOT NULL DEFAULT 'index, follow' AFTER canonical_url;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS featured_image_url TEXT NULL AFTER robots;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS primary_keyword VARCHAR(255) NULL AFTER meta_description;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS target_location VARCHAR(180) NULL AFTER primary_keyword;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'DRAFT' AFTER target_location;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS approval_status VARCHAR(40) NOT NULL DEFAULT 'DRAFT' AFTER status;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS scheduled_at DATETIME NULL AFTER approval_status;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS published_at DATETIME NULL AFTER scheduled_at;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS generated_by_agent TINYINT(1) NOT NULL DEFAULT 0 AFTER published_at;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS created_by INT(11) NULL AFTER generated_by_agent;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS approved_by INT(11) NULL AFTER created_by;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS schema_json JSON NULL AFTER approved_by;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS metadata_json JSON NULL AFTER schema_json;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS id_user_business INT(11) NULL AFTER metadata_json;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER id_user_business;
ALTER TABLE cms_contents ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE cms_contents DROP INDEX IF EXISTS uk_cms_contents_slug_owner_lang;
ALTER TABLE cms_contents ADD UNIQUE KEY IF NOT EXISTS cms_contents_owner_site_slug_unique (id_owner, site_key, slug);
ALTER TABLE cms_contents ADD KEY IF NOT EXISTS cms_contents_owner_site_status_idx (id_owner, site_key, status);
ALTER TABLE cms_contents ADD KEY IF NOT EXISTS cms_contents_type_idx (content_type);
ALTER TABLE cms_contents ADD KEY IF NOT EXISTS cms_contents_template_idx (id_template);
ALTER TABLE cms_contents ADD KEY IF NOT EXISTS cms_contents_cms_category_idx (id_cms_category);

CREATE TABLE IF NOT EXISTS cms_templates (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NULL,
  site_key VARCHAR(80) NULL,
  name VARCHAR(150) NOT NULL,
  template_key VARCHAR(120) NOT NULL,
  description TEXT NULL,
  type VARCHAR(50) DEFAULT 'general',
  preview_html LONGTEXT NULL,
  template_structure_json LONGTEXT NULL,
  css_text LONGTEXT NULL,
  metadata_json LONGTEXT NULL,
  status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cms_templates_template_key_owner (template_key, id_owner),
  KEY idx_cms_templates_owner (id_owner),
  KEY idx_cms_templates_site (site_key),
  KEY idx_cms_templates_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cms_templates ADD COLUMN IF NOT EXISTS site_key VARCHAR(80) NULL AFTER id_owner;
ALTER TABLE cms_templates ADD COLUMN IF NOT EXISTS css_text LONGTEXT NULL AFTER template_structure_json;
ALTER TABLE cms_templates ADD COLUMN IF NOT EXISTS metadata_json LONGTEXT NULL AFTER css_text;
ALTER TABLE cms_templates DROP INDEX IF EXISTS uk_cms_templates_template_key_owner;
ALTER TABLE cms_templates ADD UNIQUE KEY IF NOT EXISTS cms_templates_owner_site_key_unique (template_key, id_owner, site_key);
ALTER TABLE cms_templates ADD KEY IF NOT EXISTS idx_cms_templates_site (site_key);

CREATE TABLE IF NOT EXISTS cms_categories (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NULL,
  site_key VARCHAR(80) NOT NULL DEFAULT 'vnvevents',
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  content_origin VARCHAR(80) NOT NULL DEFAULT 'growth_hub',
  origin_site_key VARCHAR(80) NOT NULL DEFAULT 'vnvevents',
  created_by INT(11) NULL,
  updated_by INT(11) NULL,
  origin_metadata_json MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_categories_site_slug_unique (site_key, slug),
  KEY cms_categories_owner_site_idx (id_owner, site_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_content_blocks (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  id_content INT(11) NOT NULL,
  block_type VARCHAR(80) NOT NULL,
  block_key VARCHAR(120) NULL,
  title VARCHAR(255) NULL,
  data_json JSON NULL,
  sort_order INT(11) NOT NULL DEFAULT 0,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  generated_by_agent TINYINT(1) NOT NULL DEFAULT 0,
  locked_by_user TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY cms_content_blocks_content_idx (id_content),
  KEY cms_content_blocks_owner_site_idx (id_owner, site_key),
  KEY cms_content_blocks_type_idx (block_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS id_owner INT(11) NULL AFTER id;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS site_key VARCHAR(80) NULL AFTER id_owner;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS id_content INT(11) NULL AFTER site_key;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS block_type VARCHAR(80) NULL AFTER id_content;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS block_key VARCHAR(120) NULL AFTER block_type;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS title VARCHAR(255) NULL AFTER block_key;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS data_json JSON NULL AFTER title;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS sort_order INT(11) NOT NULL DEFAULT 0 AFTER data_json;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'active' AFTER sort_order;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS generated_by_agent TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS locked_by_user TINYINT(1) NOT NULL DEFAULT 0 AFTER generated_by_agent;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER locked_by_user;
ALTER TABLE cms_content_blocks ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE cms_content_blocks ADD KEY IF NOT EXISTS cms_content_blocks_content_idx (id_content);
ALTER TABLE cms_content_blocks ADD KEY IF NOT EXISTS cms_content_blocks_owner_site_idx (id_owner, site_key);
ALTER TABLE cms_content_blocks ADD KEY IF NOT EXISTS cms_content_blocks_type_idx (block_type);

CREATE TABLE IF NOT EXISTS cms_routes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  id_content INT(11) NULL,
  route VARCHAR(255) NOT NULL,
  route_type VARCHAR(60) NOT NULL DEFAULT 'landing',
  language VARCHAR(20) NULL,
  is_main TINYINT(1) NOT NULL DEFAULT 1,
  status VARCHAR(40) NOT NULL DEFAULT 'draft',
  canonical_url VARCHAR(255) NULL,
  redirect_to VARCHAR(255) NULL,
  route_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_routes_owner_site_route_unique (id_owner, site_key, route),
  KEY cms_routes_content_idx (id_content),
  KEY cms_routes_status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS id_owner INT(11) NULL AFTER id;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS site_key VARCHAR(80) NULL AFTER id_owner;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS id_content INT(11) NULL AFTER site_key;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS route VARCHAR(255) NULL AFTER id_content;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS route_type VARCHAR(60) NOT NULL DEFAULT 'landing' AFTER route;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS language VARCHAR(20) NULL AFTER route_type;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS is_main TINYINT(1) NOT NULL DEFAULT 1 AFTER language;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'draft' AFTER route_type;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(255) NULL AFTER status;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS redirect_to VARCHAR(255) NULL AFTER canonical_url;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS route_hash CHAR(64) NULL AFTER redirect_to;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER route_hash;
ALTER TABLE cms_routes ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE cms_routes ADD UNIQUE KEY IF NOT EXISTS cms_routes_owner_site_route_unique (id_owner, site_key, route);
ALTER TABLE cms_routes ADD KEY IF NOT EXISTS cms_routes_content_idx (id_content);
ALTER TABLE cms_routes ADD KEY IF NOT EXISTS cms_routes_status_idx (status);

CREATE TABLE IF NOT EXISTS cms_media (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  id_content INT(11) NULL,
  related_block_id INT(11) NULL,
  cloudinary_public_id VARCHAR(255) NOT NULL,
  cloudinary_url TEXT NULL,
  secure_url TEXT NOT NULL,
  asset_type VARCHAR(40) NULL,
  media_type VARCHAR(80) NULL,
  source_type VARCHAR(40) NOT NULL DEFAULT 'uploaded',
  usage_type VARCHAR(40) NOT NULL DEFAULT 'gallery',
  prompt_used TEXT NULL,
  revised_prompt TEXT NULL,
  model VARCHAR(120) NULL,
  alt_text VARCHAR(255) NULL,
  title_text VARCHAR(255) NULL,
  caption TEXT NULL,
  width INT(11) NULL,
  height INT(11) NULL,
  format VARCHAR(30) NULL,
  bytes BIGINT NULL,
  folder VARCHAR(255) NULL,
  metadata_json JSON NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  created_by INT(11) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_media_cloudinary_public_unique (cloudinary_public_id),
  KEY cms_media_owner_site_idx (id_owner, site_key),
  KEY cms_media_content_idx (id_content),
  KEY cms_media_source_usage_idx (source_type, usage_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS id_owner INT(11) NULL AFTER id;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS site_key VARCHAR(80) NULL AFTER id_owner;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS id_content INT(11) NULL AFTER site_key;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS related_block_id INT(11) NULL AFTER id_content;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS cloudinary_public_id VARCHAR(255) NULL AFTER related_block_id;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS cloudinary_url TEXT NULL AFTER cloudinary_public_id;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS secure_url TEXT NULL AFTER cloudinary_url;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS asset_type VARCHAR(40) NULL AFTER secure_url;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS media_type VARCHAR(80) NULL AFTER asset_type;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS source_type VARCHAR(40) NOT NULL DEFAULT 'uploaded' AFTER media_type;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS usage_type VARCHAR(40) NOT NULL DEFAULT 'gallery' AFTER source_type;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS prompt_used TEXT NULL AFTER usage_type;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS revised_prompt TEXT NULL AFTER prompt_used;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS model VARCHAR(120) NULL AFTER revised_prompt;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS alt_text VARCHAR(255) NULL AFTER model;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS title_text VARCHAR(255) NULL AFTER alt_text;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS caption TEXT NULL AFTER title_text;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS width INT(11) NULL AFTER caption;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS height INT(11) NULL AFTER width;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS format VARCHAR(30) NULL AFTER height;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS bytes BIGINT NULL AFTER format;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS folder VARCHAR(255) NULL AFTER bytes;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS metadata_json JSON NULL AFTER folder;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'active' AFTER metadata_json;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS created_by INT(11) NULL AFTER status;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by;
ALTER TABLE cms_media ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
UPDATE cms_media SET cloudinary_public_id = CONCAT('legacy-', id) WHERE cloudinary_public_id IS NULL OR cloudinary_public_id = '';
UPDATE cms_media SET secure_url = COALESCE(secure_url, cloudinary_url, '') WHERE secure_url IS NULL;
ALTER TABLE cms_media MODIFY cloudinary_public_id VARCHAR(255) NOT NULL;
ALTER TABLE cms_media MODIFY secure_url TEXT NOT NULL;
ALTER TABLE cms_media ADD UNIQUE KEY IF NOT EXISTS cms_media_cloudinary_public_unique (cloudinary_public_id);
ALTER TABLE cms_media ADD KEY IF NOT EXISTS cms_media_owner_site_idx (id_owner, site_key);
ALTER TABLE cms_media ADD KEY IF NOT EXISTS cms_media_content_idx (id_content);
ALTER TABLE cms_media ADD KEY IF NOT EXISTS cms_media_source_usage_idx (source_type, usage_type);

CREATE TABLE IF NOT EXISTS seo_keywords (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  keyword_text VARCHAR(255) NOT NULL,
  location VARCHAR(180) NULL,
  service_or_product VARCHAR(190) NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'manual',
  avg_monthly_searches INT(11) NULL,
  competition VARCHAR(80) NULL,
  cpc_low DECIMAL(10,2) NULL,
  cpc_high DECIMAL(10,2) NULL,
  intent VARCHAR(60) NULL,
  priority_score DECIMAL(6,2) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  imported_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY seo_keywords_owner_site_keyword_unique (id_owner, site_key, keyword_text, location),
  KEY seo_keywords_status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_target_locations (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  location_name VARCHAR(180) NOT NULL,
  county VARCHAR(180) NULL,
  state VARCHAR(80) NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'US',
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  priority_score DECIMAL(6,2) NOT NULL DEFAULT 50.00,
  notes TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY growth_locations_owner_site_name_unique (id_owner, site_key, location_name, county, state),
  KEY growth_locations_owner_site_idx (id_owner, site_key),
  KEY growth_locations_status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS growth_competitors (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  competitor_name VARCHAR(190) NOT NULL,
  competitor_url VARCHAR(255) NOT NULL,
  competitor_domain VARCHAR(190) NOT NULL,
  county VARCHAR(180) NULL,
  city VARCHAR(180) NULL,
  state VARCHAR(80) NULL,
  service_or_product VARCHAR(190) NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'manual',
  notes TEXT NULL,
  scan_status VARCHAR(40) NOT NULL DEFAULT 'pending',
  last_scanned_at DATETIME NULL,
  strengths_json JSON NULL,
  gaps_json JSON NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY growth_competitors_owner_site_domain_unique (id_owner, site_key, competitor_domain),
  KEY growth_competitors_owner_site_idx (id_owner, site_key),
  KEY growth_competitors_location_idx (county, city, state),
  KEY growth_competitors_scan_idx (scan_status, last_scanned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_serp_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  id_keyword INT(11) NULL,
  property_url VARCHAR(255) NULL,
  domain VARCHAR(255) NULL,
  keyword_text VARCHAR(255) NOT NULL,
  location_name VARCHAR(180) NULL,
  county VARCHAR(180) NULL,
  state VARCHAR(80) NULL,
  language_code VARCHAR(20) NULL,
  device VARCHAR(40) NOT NULL DEFAULT 'desktop',
  search_engine VARCHAR(40) NOT NULL DEFAULT 'google',
  provider VARCHAR(80) NULL,
  provider_task_id VARCHAR(120) NULL,
  depth INT(11) NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'manual_serp',
  own_domain VARCHAR(190) NULL,
  own_best_position INT(11) NULL,
  own_best_url VARCHAR(255) NULL,
  top_competitor_domain VARCHAR(190) NULL,
  top_competitor_position INT(11) NULL,
  result_count INT(11) NULL,
  intent_detected VARCHAR(80) NULL,
  recommended_content_type VARCHAR(80) NULL,
  difficulty_estimate VARCHAR(80) NULL,
  validation_status VARCHAR(80) NULL,
  raw_response_json JSON NULL,
  searched_at DATETIME NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY seo_serp_snapshots_owner_site_idx (id_owner, site_key),
  KEY seo_serp_snapshots_keyword_idx (id_keyword),
  KEY seo_serp_snapshots_location_idx (location_name, county, state),
  KEY seo_serp_snapshots_checked_idx (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS property_url VARCHAR(255) NULL AFTER id_keyword;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS domain VARCHAR(255) NULL AFTER property_url;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS language_code VARCHAR(20) NULL AFTER state;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS provider VARCHAR(80) NULL AFTER search_engine;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS provider_task_id VARCHAR(120) NULL AFTER provider;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS depth INT(11) NULL AFTER provider_task_id;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS intent_detected VARCHAR(80) NULL AFTER result_count;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS recommended_content_type VARCHAR(80) NULL AFTER intent_detected;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS difficulty_estimate VARCHAR(80) NULL AFTER recommended_content_type;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS validation_status VARCHAR(80) NULL AFTER difficulty_estimate;
ALTER TABLE seo_serp_snapshots ADD COLUMN IF NOT EXISTS searched_at DATETIME NULL AFTER raw_response_json;

CREATE TABLE IF NOT EXISTS seo_serp_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_snapshot BIGINT UNSIGNED NOT NULL,
  id_competitor INT(11) NULL,
  result_position INT(11) NOT NULL,
  result_title VARCHAR(255) NULL,
  result_url TEXT NULL,
  result_domain VARCHAR(190) NULL,
  snippet TEXT NULL,
  is_own_domain TINYINT(1) NOT NULL DEFAULT 0,
  result_type VARCHAR(60) NOT NULL DEFAULT 'organic',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY seo_serp_results_snapshot_idx (id_snapshot),
  KEY seo_serp_results_domain_idx (result_domain),
  KEY seo_serp_results_competitor_idx (id_competitor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_serp_import_status (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  provider VARCHAR(80) NOT NULL,
  keyword_text VARCHAR(255) NOT NULL,
  location_name VARCHAR(180) NOT NULL,
  device VARCHAR(40) NOT NULL DEFAULT 'desktop',
  connection_status VARCHAR(40) NOT NULL DEFAULT 'not_connected',
  provider_task_id VARCHAR(120) NULL,
  last_successful_fetch DATETIME NULL,
  rows_saved INT(11) NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY seo_serp_status_unique (id_owner, site_key, provider, keyword_text, location_name, device),
  KEY seo_serp_status_site_idx (id_owner, site_key),
  KEY seo_serp_status_status_idx (connection_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_search_console_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  property_url VARCHAR(255) NULL,
  domain VARCHAR(255) NULL,
  keyword_text VARCHAR(255) NOT NULL,
  page_url TEXT NULL,
  country VARCHAR(12) NULL,
  device VARCHAR(40) NULL,
  clicks INT(11) NOT NULL DEFAULT 0,
  impressions INT(11) NOT NULL DEFAULT 0,
  ctr DECIMAL(8,4) NULL,
  average_position DECIMAL(8,2) NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY seo_gsc_owner_site_keyword_idx (id_owner, site_key, keyword_text),
  KEY seo_gsc_property_idx (property_url),
  KEY seo_gsc_dates_idx (date_from, date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE seo_search_console_snapshots ADD COLUMN IF NOT EXISTS property_url VARCHAR(255) NULL AFTER site_key;
ALTER TABLE seo_search_console_snapshots ADD COLUMN IF NOT EXISTS domain VARCHAR(255) NULL AFTER property_url;
ALTER TABLE seo_search_console_snapshots ADD KEY IF NOT EXISTS seo_gsc_property_idx (property_url);

CREATE TABLE IF NOT EXISTS seo_search_console_import_status (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  property_url VARCHAR(255) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  connection_status VARCHAR(40) NOT NULL DEFAULT 'not_connected',
  last_successful_import DATETIME NULL,
  rows_imported INT(11) NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY seo_gsc_status_site_property_unique (id_owner, site_key, property_url),
  KEY seo_gsc_status_domain_idx (domain),
  KEY seo_gsc_status_status_idx (connection_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_google_trends_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  keyword_text VARCHAR(255) NOT NULL,
  location_name VARCHAR(180) NULL,
  geo_code VARCHAR(40) NULL,
  interest_score INT(11) NULL,
  trend_direction VARCHAR(40) NULL,
  related_queries_json JSON NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY seo_trends_owner_site_keyword_idx (id_owner, site_key, keyword_text),
  KEY seo_trends_location_idx (location_name, geo_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_opportunities (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  id_keyword INT(11) NULL,
  opportunity_type VARCHAR(80) NOT NULL,
  title VARCHAR(255) NOT NULL,
  keyword_text VARCHAR(255) NULL,
  location_name VARCHAR(180) NULL,
  service_or_product VARCHAR(190) NULL,
  score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  reason_summary TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'IDEA',
  recommended_content_type VARCHAR(60) NULL,
  recommended_route VARCHAR(255) NULL,
  data_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY seo_opportunities_owner_site_idx (id_owner, site_key),
  KEY seo_opportunities_status_idx (status),
  KEY seo_opportunities_score_idx (score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_agent_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_owner INT(11) NOT NULL,
  site_key VARCHAR(80) NOT NULL,
  run_type VARCHAR(80) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'PENDING',
  input_json JSON NULL,
  output_json JSON NULL,
  summary TEXT NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY seo_agent_runs_owner_site_idx (id_owner, site_key),
  KEY seo_agent_runs_status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO growth_sites
  (id_owner, site_key, site_name, domain, public_base_url, default_language, brand_voice, target_locations, main_services, main_products, default_cta_label, default_cta_url, cloudinary_folder, auto_publish_allowed, sitemap_settings, route_rules, status)
VALUES
  (2, 'vnvevents', 'VNV Events', 'vnvevents.com', 'https://vnvevents.com', 'en', 'Professional, warm, luxury but accessible, local South Florida expert.', JSON_ARRAY('Miami-Dade County','Broward County','Palm Beach County','Miami, Florida, United States','Doral, Florida, United States','Kendall, Florida, United States','Hialeah, Florida, United States','Fort Lauderdale, Florida, United States','Hollywood, Florida, United States','Pembroke Pines, Florida, United States','Weston, Florida, United States','Sunrise, Florida, United States','West Palm Beach, Florida, United States','Boca Raton, Florida, United States','Delray Beach, Florida, United States'), JSON_ARRAY(JSON_OBJECT('label','wedding planning','url','/wedding-planning/'),JSON_OBJECT('label','corporate events','url','/corporate-events/'),JSON_OBJECT('label','quinceaneras','url','/quinceaneras/'),JSON_OBJECT('label','baby showers','url','/baby-showers/'),JSON_OBJECT('label','event rentals','url','/event-rentals/'),JSON_OBJECT('label','flowers and decor','url','/flowers-and-decor/'),JSON_OBJECT('label','DJ','url','/dj/'),JSON_OBJECT('label','photo and video','url','/photo-video/'),JSON_OBJECT('label','multimedia','url','/multimedia/'),JSON_OBJECT('label','bartending','url','/bartending/'),JSON_OBJECT('label','event staffing','url','/event-staffing/')), JSON_ARRAY(), 'Request a Quote', '/quote', 'ophyra-growth-hub/vnvevents', 0, JSON_OBJECT('public_base_url', 'https://vnvevents.com', 'sitemap_url', 'https://vnvevents.com/sitemap.xml', 'environment', 'development', 'receiver_sitemap_endpoint', ''), JSON_OBJECT('page', '/{slug}', 'landing', '/{slug}', 'custom', '/{slug}', 'location', '/locations/{slug}', 'blog', '/blog/{slug}'), 'active'),
  (2, 'avomeal', 'Avomeal / VNV Gourmet', 'avomeal.com', 'https://avomeal.com', 'en', 'Fresh, practical, warm, food-forward and reliable for meals and catering.', JSON_ARRAY('Miami','Doral','Broward','South Florida'), JSON_ARRAY(JSON_OBJECT('label','catering','url','/catering/'),JSON_OBJECT('label','meal prep','url','/meal-prep/'),JSON_OBJECT('label','office lunch','url','/office-lunch/'),JSON_OBJECT('label','event food setup','url','/catering/')), JSON_ARRAY(JSON_OBJECT('label','meal prep containers','url','/store/meal-prep/'),JSON_OBJECT('label','catering trays','url','/store/catering-trays/'),JSON_OBJECT('label','Venezuelan food','url','/venezuelan-food/'),JSON_OBJECT('label','office lunches','url','/office-lunch/')), 'Order / Request Catering', '/order', 'ophyra-growth-hub/avomeal', 0, JSON_OBJECT('public_base_url', 'https://avomeal.com', 'sitemap_url', 'https://avomeal.com/sitemap.xml', 'environment', 'development', 'receiver_sitemap_endpoint', ''), JSON_OBJECT('page', '/{slug}', 'landing', '/{slug}', 'custom', '/{slug}', 'location', '/locations/{slug}', 'blog', '/blog/{slug}'), 'active'),
  (2, 'jonnysmedia', 'Jonnys Media', 'jonnys.media', 'https://jonnys.media', 'en', 'Creative, direct, technical and premium visual production.', JSON_ARRAY('Miami','South Florida'), JSON_ARRAY(JSON_OBJECT('label','photo','url','/photo/'),JSON_OBJECT('label','video','url','/video/'),JSON_OBJECT('label','multimedia sessions','url','/multimedia-sessions/'),JSON_OBJECT('label','creative production','url','/creative-production/')), JSON_ARRAY(), 'Book a Session', '/contact', 'ophyra-growth-hub/jonnysmedia', 0, JSON_OBJECT('public_base_url', 'https://jonnys.media', 'sitemap_url', 'https://jonnys.media/sitemap.xml', 'environment', 'development', 'receiver_sitemap_endpoint', ''), JSON_OBJECT('page', '/{slug}', 'landing', '/{slug}', 'custom', '/{slug}', 'location', '/locations/{slug}', 'blog', '/blog/{slug}'), 'active')
ON DUPLICATE KEY UPDATE
  site_name = VALUES(site_name),
  domain = COALESCE(NULLIF(domain, ''), VALUES(domain)),
  public_base_url = COALESCE(NULLIF(public_base_url, ''), VALUES(public_base_url)),
  main_services = VALUES(main_services),
  main_products = VALUES(main_products),
  cloudinary_folder = VALUES(cloudinary_folder),
  sitemap_settings = COALESCE(sitemap_settings, VALUES(sitemap_settings)),
  route_rules = COALESCE(route_rules, VALUES(route_rules)),
  updated_at = NOW();

UPDATE growth_sites
SET sitemap_settings = JSON_SET(
    COALESCE(sitemap_settings, JSON_OBJECT()),
    '$.public_base_url', COALESCE(NULLIF(public_base_url, ''), JSON_UNQUOTE(JSON_EXTRACT(COALESCE(sitemap_settings, JSON_OBJECT()), '$.public_base_url'))),
    '$.sitemap_url', COALESCE(
      JSON_UNQUOTE(JSON_EXTRACT(COALESCE(sitemap_settings, JSON_OBJECT()), '$.sitemap_url')),
      CONCAT(COALESCE(NULLIF(public_base_url, ''), CONCAT('https://', domain)), '/sitemap.xml')
    ),
    '$.environment', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(sitemap_settings, JSON_OBJECT()), '$.environment')), 'development'),
    '$.receiver_sitemap_endpoint', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(sitemap_settings, JSON_OBJECT()), '$.receiver_sitemap_endpoint')), '')
  ),
  updated_at = NOW()
WHERE id_owner = 2
  AND site_key IN ('vnvevents', 'avomeal', 'jonnysmedia');

INSERT INTO growth_target_locations
  (id_owner, site_key, location_name, county, state, lat, lng, priority_score, notes, status)
VALUES
  (2, 'vnvevents', 'Miami, Florida, United States', 'Miami-Dade', 'FL', 25.7617000, -80.1918000, 95.00, 'Default VNV Events SERP market.', 'active'),
  (2, 'vnvevents', 'Doral, Florida, United States', 'Miami-Dade', 'FL', 25.8195000, -80.3553000, 90.00, 'Important quinceanera, corporate and event planning market.', 'active'),
  (2, 'vnvevents', 'Kendall, Florida, United States', 'Miami-Dade', 'FL', 25.6660000, -80.3578000, 84.00, 'Miami-Dade family and social events market.', 'active'),
  (2, 'vnvevents', 'Hialeah, Florida, United States', 'Miami-Dade', 'FL', 25.8576000, -80.2781000, 82.00, 'Miami-Dade social events market.', 'active'),
  (2, 'vnvevents', 'Fort Lauderdale, Florida, United States', 'Broward', 'FL', 26.1224000, -80.1373000, 85.00, 'Broward event planning target.', 'active'),
  (2, 'vnvevents', 'Hollywood, Florida, United States', 'Broward', 'FL', 26.0112000, -80.1495000, 82.00, 'Broward beach and social events market.', 'active'),
  (2, 'vnvevents', 'Pembroke Pines, Florida, United States', 'Broward', 'FL', 26.0078000, -80.2963000, 80.00, 'Broward social events market.', 'active'),
  (2, 'vnvevents', 'Weston, Florida, United States', 'Broward', 'FL', 26.1004000, -80.3998000, 82.00, 'High-value event planning target area.', 'active'),
  (2, 'vnvevents', 'Sunrise, Florida, United States', 'Broward', 'FL', 26.1669000, -80.2566000, 78.00, 'Broward event planning target.', 'active'),
  (2, 'vnvevents', 'West Palm Beach, Florida, United States', 'Palm Beach', 'FL', 26.7153000, -80.0534000, 78.00, 'Palm Beach expansion target.', 'active'),
  (2, 'vnvevents', 'Boca Raton, Florida, United States', 'Palm Beach', 'FL', 26.3683000, -80.1289000, 76.00, 'Palm Beach premium events market.', 'active'),
  (2, 'vnvevents', 'Delray Beach, Florida, United States', 'Palm Beach', 'FL', 26.4615000, -80.0728000, 74.00, 'Palm Beach social events market.', 'active'),
  (2, 'avomeal', 'Miami', 'Miami-Dade', 'FL', 25.7617000, -80.1918000, 92.00, 'Meal prep and catering core market.', 'active'),
  (2, 'avomeal', 'Doral', 'Miami-Dade', 'FL', 25.8195000, -80.3553000, 88.00, 'Office lunch and catering target.', 'active'),
  (2, 'jonnysmedia', 'Miami', 'Miami-Dade', 'FL', 25.7617000, -80.1918000, 90.00, 'Creative production core market.', 'active')
ON DUPLICATE KEY UPDATE
  priority_score = VALUES(priority_score),
  notes = VALUES(notes),
  updated_at = NOW();

UPDATE growth_target_locations
SET status = 'inactive', updated_at = NOW()
WHERE id_owner = 2
  AND site_key = 'vnvevents'
  AND location_name NOT IN (
    'Miami, Florida, United States',
    'Doral, Florida, United States',
    'Kendall, Florida, United States',
    'Hialeah, Florida, United States',
    'Fort Lauderdale, Florida, United States',
    'Hollywood, Florida, United States',
    'Pembroke Pines, Florida, United States',
    'Weston, Florida, United States',
    'Sunrise, Florida, United States',
    'West Palm Beach, Florida, United States',
    'Boca Raton, Florida, United States',
    'Delray Beach, Florida, United States'
  );

INSERT INTO cms_categories
  (id_owner, site_key, name, slug, description, is_active, content_origin, origin_site_key, origin_metadata_json)
VALUES
  (2, 'vnvevents', 'Weddings', 'weddings', 'Wedding planning, packages and service pages.', 1, 'growth_hub', 'vnvevents', JSON_OBJECT('default_template', 'service-landing')),
  (2, 'vnvevents', 'Quinceaneras', 'quinceaneras', 'Quinceanera packages, planning guides and local pages.', 1, 'growth_hub', 'vnvevents', JSON_OBJECT('default_template', 'service-landing')),
  (2, 'vnvevents', 'Corporate Events', 'corporate-events', 'Corporate event planning and production content.', 1, 'growth_hub', 'vnvevents', JSON_OBJECT('default_template', 'service-landing')),
  (2, 'vnvevents', 'Event Locations', 'event-locations', 'Location landing pages and local guides.', 1, 'growth_hub', 'vnvevents', JSON_OBJECT('default_template', 'local-location-page')),
  (2, 'vnvevents', 'Planning Guides', 'planning-guides', 'Editorial planning guides and educational articles.', 1, 'growth_hub', 'vnvevents', JSON_OBJECT('default_template', 'editorial-guide')),
  (2, 'avomeal', 'Weekly Menus', 'weekly-menus', 'Weekly menu and meal-prep content.', 1, 'growth_hub', 'avomeal', JSON_OBJECT('default_template', 'editorial-guide')),
  (2, 'avomeal', 'Meal Plans', 'meal-plans', 'Meal prep, subscription and nutrition content.', 1, 'growth_hub', 'avomeal', JSON_OBJECT('default_template', 'service-landing')),
  (2, 'avomeal', 'Delivery Areas', 'delivery-areas', 'Local delivery and service area pages.', 1, 'growth_hub', 'avomeal', JSON_OBJECT('default_template', 'local-location-page')),
  (2, 'jonnysmedia', 'Brand Pages', 'brand-pages', 'Professional identity and service landing pages.', 1, 'growth_hub', 'jonnysmedia', JSON_OBJECT('default_template', 'service-landing')),
  (2, 'jonnysmedia', 'Articles', 'articles', 'Editorial articles and portfolio context.', 1, 'growth_hub', 'jonnysmedia', JSON_OBJECT('default_template', 'editorial-guide'))
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  is_active = VALUES(is_active),
  origin_metadata_json = VALUES(origin_metadata_json),
  updated_at = NOW();

INSERT INTO cms_templates
  (id_owner, site_key, name, template_key, description, type, preview_html, template_structure_json, css_text, metadata_json, status)
VALUES
  (2, 'vnvevents', 'Service Landing', 'service-landing', 'Conversion-focused service landing page with hero, proof, sections, CTA and FAQ.', 'landing', '<section class="cms-preview cms-preview-service"><h1>{{ title }}</h1><div>{{ body_html|raw }}</div></section>', JSON_OBJECT('supports', JSON_ARRAY('hero','body_section','photo_gallery','local_map','cta','faq'), 'default_blocks', JSON_ARRAY('hero','body_section','cta','faq')), '.cms-preview-template-service-landing .cms-block-hero{background:#0f766e;color:#fff}.cms-preview-template-service-landing .cms-block-cta{background:#f0fdfa}', JSON_OBJECT('source', 'growth_hub_seed'), 'ACTIVE'),
  (2, 'vnvevents', 'Local Location Page', 'local-location-page', 'Local market page with map, services, proof, FAQ and CTA.', 'location', '<section class="cms-preview cms-preview-local"><h1>{{ title }}</h1><div>{{ body_html|raw }}</div></section>', JSON_OBJECT('supports', JSON_ARRAY('hero','local_map','body_section','cta','faq'), 'default_blocks', JSON_ARRAY('hero','local_map','body_section','cta','faq')), '.cms-preview-template-local-location-page .cms-block-hero{background:#134e4a;color:#fff}.cms-preview-template-local-location-page .cms-block-default{background:#ecfeff}', JSON_OBJECT('source', 'growth_hub_seed'), 'ACTIVE'),
  (2, 'vnvevents', 'Editorial Guide', 'editorial-guide', 'Long-form guide or article with body sections, FAQ and soft CTA.', 'post', '<article class="cms-preview cms-preview-guide"><h1>{{ title }}</h1><div>{{ body_html|raw }}</div></article>', JSON_OBJECT('supports', JSON_ARRAY('hero','body_section','photo_gallery','cta','faq'), 'default_blocks', JSON_ARRAY('hero','body_section','faq','cta')), '.cms-preview-template-editorial-guide .cms-block-hero{background:#1f2937;color:#fff}.cms-preview-template-editorial-guide .cms-block-body{max-width:840px}', JSON_OBJECT('source', 'growth_hub_seed'), 'ACTIVE'),
  (2, 'vnvevents', 'FAQ Resource', 'faq-resource', 'FAQ-led resource page.', 'page', '<section class="cms-preview cms-preview-faq"><h1>{{ title }}</h1><div>{{ body_html|raw }}</div></section>', JSON_OBJECT('supports', JSON_ARRAY('hero','faq','cta'), 'default_blocks', JSON_ARRAY('hero','faq','cta')), '.cms-preview-template-faq-resource .cms-block-hero{background:#334155;color:#fff}', JSON_OBJECT('source', 'growth_hub_seed'), 'ACTIVE'),
  (2, 'avomeal', 'Avomeal Service Landing', 'service-landing', 'Food service or meal-plan landing page.', 'landing', '<section class="cms-preview cms-preview-service"><h1>{{ title }}</h1><div>{{ body_html|raw }}</div></section>', JSON_OBJECT('supports', JSON_ARRAY('hero','body_section','photo_gallery','cta','faq'), 'default_blocks', JSON_ARRAY('hero','body_section','cta','faq')), '.cms-preview-template-service-landing .cms-block-hero{background:#166534;color:#fff}.cms-preview-template-service-landing .cms-block-cta{background:#f0fdf4}', JSON_OBJECT('source', 'growth_hub_seed'), 'ACTIVE'),
  (2, 'avomeal', 'Avomeal Editorial Guide', 'editorial-guide', 'Meal prep article, nutrition guide or menu story.', 'post', '<article class="cms-preview cms-preview-guide"><h1>{{ title }}</h1><div>{{ body_html|raw }}</div></article>', JSON_OBJECT('supports', JSON_ARRAY('hero','body_section','cta','faq'), 'default_blocks', JSON_ARRAY('hero','body_section','faq','cta')), '.cms-preview-template-editorial-guide .cms-block-hero{background:#365314;color:#fff}', JSON_OBJECT('source', 'growth_hub_seed'), 'ACTIVE'),
  (2, 'jonnysmedia', 'Jonnys Media Service Landing', 'service-landing', 'Professional service landing page.', 'landing', '<section class="cms-preview cms-preview-service"><h1>{{ title }}</h1><div>{{ body_html|raw }}</div></section>', JSON_OBJECT('supports', JSON_ARRAY('hero','body_section','cta','faq'), 'default_blocks', JSON_ARRAY('hero','body_section','cta','faq')), '.cms-preview-template-service-landing .cms-block-hero{background:#111827;color:#fff}.cms-preview-template-service-landing .cms-block-cta{background:#f8fafc}', JSON_OBJECT('source', 'growth_hub_seed'), 'ACTIVE')
ON DUPLICATE KEY UPDATE
  site_key = VALUES(site_key),
  name = VALUES(name),
  description = VALUES(description),
  type = VALUES(type),
  preview_html = VALUES(preview_html),
  template_structure_json = VALUES(template_structure_json),
  css_text = VALUES(css_text),
  metadata_json = VALUES(metadata_json),
  status = VALUES(status),
  updated_at = NOW();

UPDATE cms_templates
SET css_text = '.cms-preview-template-service-landing{--brand-bg:#0a0c0c;--brand-panel:#141818;--brand-ink:#f3f5f6;--brand-muted:rgba(255,255,255,.74);--brand-teal:#5ec6c4;--brand-gold:#c5a059;--brand-marble:#f4f7f8;--brand-line:rgba(255,255,255,.12);--brand-shadow:0 32px 80px rgba(0,0,0,.26);background:var(--brand-bg);color:var(--brand-ink);font-family:Plus Jakarta Sans,Inter,system-ui,sans-serif;line-height:1.8;border-radius:24px;overflow:hidden}.cms-preview-template-service-landing .cms-block{margin:0;padding:54px 42px;border:0}.cms-preview-template-service-landing .cms-block-hero{min-height:480px;display:grid;align-content:center;position:relative;overflow:hidden;background:linear-gradient(90deg,rgba(10,12,12,.95),rgba(10,12,12,.74) 52%,rgba(10,12,12,.42)),linear-gradient(135deg,#101414,#1b2021);border-radius:0}.cms-preview-template-service-landing .cms-block-hero:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 82% 18%,rgba(94,198,196,.2),transparent 30%),radial-gradient(circle at 18% 88%,rgba(197,160,89,.16),transparent 28%);pointer-events:none}.cms-preview-template-service-landing .cms-eyebrow{position:relative;z-index:1;display:inline-flex;width:max-content;margin:0 0 18px;padding:9px 16px;border:1px solid rgba(94,198,196,.42);border-radius:999px;background:rgba(94,198,196,.1);color:var(--brand-teal);font-size:.74rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.cms-preview-template-service-landing h1,.cms-preview-template-service-landing h2{font-family:Playfair Display,Georgia,serif;letter-spacing:0}.cms-preview-template-service-landing .cms-block-hero h1{position:relative;z-index:1;max-width:900px;margin:0 0 20px;color:#fff;font-size:clamp(2.8rem,6vw,5.4rem);line-height:1.02;font-weight:900}.cms-preview-template-service-landing .cms-block-hero p:not(.cms-eyebrow){position:relative;z-index:1;max-width:760px;margin:0;color:var(--brand-muted);font-size:1.08rem}.cms-preview-template-service-landing .cms-block-body{background:var(--brand-marble);color:#11181a}.cms-preview-template-service-landing .cms-block-body h2{font-size:clamp(2rem,4vw,3.6rem);line-height:1.08;margin:0 0 20px;color:#11181a}.cms-preview-template-service-landing .cms-block-body>div{max-width:900px;color:#4f5b5f;font-size:1.04rem}.cms-preview-template-service-landing .cms-block-body ul{list-style:none;padding:0;margin:24px 0 0}.cms-preview-template-service-landing .cms-block-body li{position:relative;padding-left:24px;margin:0 0 12px}.cms-preview-template-service-landing .cms-block-body li:before{content:"";position:absolute;left:0;top:.72em;width:8px;height:8px;border-radius:50%;background:var(--brand-gold)}.cms-preview-template-service-landing .cms-block-gallery{background:#fff;color:#11181a}.cms-preview-template-service-landing .cms-gallery-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.cms-preview-template-service-landing .cms-gallery-grid figure{margin:0;border-radius:28px;overflow:hidden;box-shadow:0 25px 70px rgba(0,0,0,.12);background:#fff}.cms-preview-template-service-landing .cms-gallery-grid img{width:100%;height:260px;object-fit:cover;display:block}.cms-preview-template-service-landing .cms-block-cta{background:linear-gradient(145deg,#161a1b,#0d0f0f);color:#fff;text-align:center}.cms-preview-template-service-landing .cms-block-cta h2{font-size:clamp(2rem,4vw,3.6rem);margin:0 0 12px}.cms-preview-template-service-landing .cms-block-cta p{max-width:760px;margin:0 auto 22px;color:var(--brand-muted)}.cms-preview-template-service-landing .cms-block-cta a{display:inline-flex;align-items:center;justify-content:center;padding:16px 28px;border-radius:999px;background:linear-gradient(135deg,#bf953f,#fcf6ba 48%,#b38728);color:#050505;text-decoration:none;font-weight:900;text-transform:uppercase;letter-spacing:.12em;font-size:.76rem}.cms-preview-template-service-landing .cms-block-faq{background:#fff;color:#11181a}.cms-preview-template-service-landing .cms-block-faq h2{font-size:clamp(2rem,4vw,3.3rem);margin:0 0 24px}.cms-preview-template-service-landing details{border:1px solid rgba(17,24,26,.08);border-radius:18px;padding:18px 20px;margin:0 0 12px;background:#f8fafb}.cms-preview-template-service-landing summary{cursor:pointer;font-weight:800;color:#11181a}.cms-preview-template-service-landing details p{margin:12px 0 0;color:#566164}@media(max-width:760px){.cms-preview-template-service-landing .cms-block{padding:38px 22px}.cms-preview-template-service-landing .cms-block-hero{min-height:420px}.cms-preview-template-service-landing .cms-block-hero h1{font-size:2.55rem}}',
    metadata_json = JSON_SET(COALESCE(NULLIF(metadata_json, ''), JSON_OBJECT()), '$.visual_source', 'reviewed from vnv-events public service pages')
WHERE id_owner = 2 AND site_key = 'vnvevents' AND template_key = 'service-landing';

UPDATE cms_templates
SET css_text = '.cms-preview-template-local-location-page{--brand-dark:#0a0c0c;--brand-teal:#5ec6c4;--brand-gold:#c5a059;--brand-marble:#f4f7f8;--brand-text:#11181a;--brand-muted:#566164;background:var(--brand-marble);color:var(--brand-text);font-family:Plus Jakarta Sans,Inter,system-ui,sans-serif;border-radius:24px;overflow:hidden}.cms-preview-template-local-location-page .cms-block{padding:46px 38px;margin:0;border:0}.cms-preview-template-local-location-page .cms-block-hero{background:linear-gradient(135deg,#0a0c0c,#182020);color:#fff;border-radius:0;position:relative}.cms-preview-template-local-location-page .cms-block-hero:after{content:"";position:absolute;right:-80px;bottom:-90px;width:280px;height:280px;border-radius:999px;background:radial-gradient(circle,rgba(94,198,196,.26),transparent 68%)}.cms-preview-template-local-location-page .cms-eyebrow{display:inline-flex;margin:0 0 16px;padding:8px 14px;border-radius:999px;border:1px solid rgba(94,198,196,.45);color:var(--brand-teal);background:rgba(94,198,196,.1);font-size:.74rem;font-weight:900;text-transform:uppercase;letter-spacing:.14em}.cms-preview-template-local-location-page h1,.cms-preview-template-local-location-page h2{font-family:Playfair Display,Georgia,serif;letter-spacing:0}.cms-preview-template-local-location-page .cms-block-hero h1{max-width:900px;margin:0 0 16px;color:#fff;font-size:clamp(2.4rem,5vw,4.5rem);line-height:1.05}.cms-preview-template-local-location-page .cms-block-hero p:not(.cms-eyebrow){max-width:780px;color:rgba(255,255,255,.78);font-size:1.05rem}.cms-preview-template-local-location-page .cms-block-body,.cms-preview-template-local-location-page .cms-block-default{background:#fff;border:1px solid #e7e1d7;border-radius:26px;margin:28px 34px;box-shadow:0 18px 50px rgba(24,18,10,.1)}.cms-preview-template-local-location-page .cms-block-body h2,.cms-preview-template-local-location-page .cms-block-default h2{margin:0 0 14px;color:#11181a;font-size:clamp(1.9rem,3.4vw,3.1rem)}.cms-preview-template-local-location-page .cms-block-body div,.cms-preview-template-local-location-page .cms-block-default div{color:var(--brand-muted);line-height:1.85}.cms-preview-template-local-location-page .cms-block-cta{background:#111316;color:#fff;text-align:center}.cms-preview-template-local-location-page .cms-block-cta a{display:inline-flex;padding:14px 24px;border-radius:999px;background:var(--brand-teal);color:#071011;text-decoration:none;font-weight:900;text-transform:uppercase;letter-spacing:.1em;font-size:.76rem}.cms-preview-template-local-location-page .cms-block-faq{background:var(--brand-marble)}.cms-preview-template-local-location-page details{background:#fff;border:1px solid #e7e1d7;border-radius:16px;padding:16px 18px;margin-bottom:10px}.cms-preview-template-local-location-page summary{font-weight:900;cursor:pointer}@media(max-width:760px){.cms-preview-template-local-location-page .cms-block{padding:34px 20px}.cms-preview-template-local-location-page .cms-block-body,.cms-preview-template-local-location-page .cms-block-default{margin:18px 16px}}',
    metadata_json = JSON_SET(COALESCE(NULLIF(metadata_json, ''), JSON_OBJECT()), '$.visual_source', 'reviewed from vnv-events Growth Hub and location renderers')
WHERE id_owner = 2 AND site_key = 'vnvevents' AND template_key = 'local-location-page';

UPDATE cms_templates
SET css_text = '.cms-preview-template-editorial-guide{--article-bg:#f6f1e8;--surface:#fff;--ink:#171717;--muted:#6b6358;--line:#e8ddcc;--gold:#c8a86b;--dark:#15161d;background:radial-gradient(circle at 12% 0,rgba(200,168,107,.18),transparent 34%),linear-gradient(180deg,#fff,var(--article-bg));color:var(--ink);font-family:Inter,Plus Jakarta Sans,system-ui,sans-serif;border-radius:24px;padding:34px;line-height:1.9}.cms-preview-template-editorial-guide .cms-block{max-width:900px;margin:0 auto 24px;padding:0;border:0}.cms-preview-template-editorial-guide .cms-block-hero{max-width:100%;background:linear-gradient(135deg,#15161d,#252a33 55%,#3f4553);color:#fff;border-radius:30px;padding:38px 34px;box-shadow:0 28px 66px rgba(10,10,14,.28);position:relative;overflow:hidden}.cms-preview-template-editorial-guide .cms-block-hero:after{content:"";position:absolute;right:-70px;bottom:-70px;width:260px;height:260px;border-radius:999px;background:radial-gradient(circle,rgba(200,168,107,.34),transparent 68%)}.cms-preview-template-editorial-guide .cms-eyebrow{display:inline-flex;margin:0 0 18px;padding:8px 14px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(200,168,107,.5);color:#fff;font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.cms-preview-template-editorial-guide h1,.cms-preview-template-editorial-guide h2{font-family:Georgia,serif;letter-spacing:0}.cms-preview-template-editorial-guide .cms-block-hero h1{font-size:clamp(2.25rem,4.2vw,3.8rem);line-height:1.05;margin:0 0 18px;color:#fff}.cms-preview-template-editorial-guide .cms-block-hero p:not(.cms-eyebrow){max-width:900px;color:rgba(255,255,255,.9);font-size:1.12rem}.cms-preview-template-editorial-guide .cms-block-body,.cms-preview-template-editorial-guide .cms-block-faq,.cms-preview-template-editorial-guide .cms-block-gallery,.cms-preview-template-editorial-guide .cms-block-default{background:var(--surface);border:1px solid var(--line);border-radius:24px;box-shadow:0 18px 42px rgba(24,18,10,.12);padding:30px 28px}.cms-preview-template-editorial-guide .cms-block-body h2,.cms-preview-template-editorial-guide .cms-block-faq h2,.cms-preview-template-editorial-guide .cms-block-default h2{font-size:clamp(1.8rem,3vw,2.6rem);margin:0 0 16px;color:var(--ink)}.cms-preview-template-editorial-guide .cms-block-body p:first-of-type:first-letter{float:left;font-size:3.4rem;line-height:.85;font-weight:800;color:var(--gold);margin:.08rem .44rem 0 0;font-family:Georgia,serif}.cms-preview-template-editorial-guide blockquote{border-left:4px solid var(--gold);padding:14px 20px;background:#fffaf1;border-radius:0 14px 14px 0;color:#433829}.cms-preview-template-editorial-guide details{border:1px solid var(--line);border-radius:14px;padding:15px 18px;margin-bottom:10px;background:#fffaf3}.cms-preview-template-editorial-guide summary{font-weight:800;cursor:pointer}.cms-preview-template-editorial-guide .cms-block-cta{max-width:900px;background:#15161d;color:#fff;border-radius:24px;padding:32px;text-align:center}.cms-preview-template-editorial-guide .cms-block-cta a{display:inline-flex;margin-top:8px;padding:12px 18px;border-radius:12px;background:#f3f4f6;color:#171717;text-decoration:none;font-weight:800;border:1px solid var(--line)}@media(max-width:760px){.cms-preview-template-editorial-guide{padding:18px}.cms-preview-template-editorial-guide .cms-block-hero,.cms-preview-template-editorial-guide .cms-block-body,.cms-preview-template-editorial-guide .cms-block-faq,.cms-preview-template-editorial-guide .cms-block-default{padding:24px 20px}}',
    metadata_json = JSON_SET(COALESCE(NULLIF(metadata_json, ''), JSON_OBJECT()), '$.visual_source', 'reviewed from vnv-events blog-post renderer')
WHERE id_owner = 2 AND site_key = 'vnvevents' AND template_key = 'editorial-guide';

UPDATE cms_templates
SET css_text = '.cms-preview-template-faq-resource{--dark:#0a0c0c;--panel:#141818;--teal:#5ec6c4;--gold:#c5a059;--light:#f4f7f8;--text:#11181a;background:#f4f7f8;color:var(--text);font-family:Plus Jakarta Sans,Inter,system-ui,sans-serif;border-radius:24px;overflow:hidden}.cms-preview-template-faq-resource .cms-block{padding:42px 34px;margin:0;border:0}.cms-preview-template-faq-resource .cms-block-hero{background:linear-gradient(135deg,var(--dark),var(--panel));color:#fff;text-align:center}.cms-preview-template-faq-resource .cms-eyebrow{display:inline-flex;margin:0 0 14px;padding:8px 14px;border-radius:999px;background:rgba(94,198,196,.1);border:1px solid rgba(94,198,196,.42);color:var(--teal);font-size:.74rem;font-weight:900;text-transform:uppercase;letter-spacing:.14em}.cms-preview-template-faq-resource h1,.cms-preview-template-faq-resource h2{font-family:Playfair Display,Georgia,serif}.cms-preview-template-faq-resource h1{font-size:clamp(2.3rem,5vw,4.4rem);line-height:1.05;color:#fff;margin:0 0 12px}.cms-preview-template-faq-resource .cms-block-hero p:not(.cms-eyebrow){max-width:760px;margin:0 auto;color:rgba(255,255,255,.76)}.cms-preview-template-faq-resource .cms-block-faq{background:#fff}.cms-preview-template-faq-resource .cms-block-faq h2{font-size:clamp(2rem,4vw,3.4rem);text-align:center;margin:0 0 26px}.cms-preview-template-faq-resource details{max-width:900px;margin:0 auto 12px;background:#f8fafb;border:1px solid rgba(17,24,26,.09);border-radius:18px;padding:18px 20px}.cms-preview-template-faq-resource summary{font-weight:900;cursor:pointer}.cms-preview-template-faq-resource details p{margin:12px 0 0;color:#566164}.cms-preview-template-faq-resource .cms-block-cta{background:var(--dark);color:#fff;text-align:center}.cms-preview-template-faq-resource .cms-block-cta a{display:inline-flex;padding:14px 24px;border-radius:999px;background:linear-gradient(135deg,#bf953f,#fcf6ba 48%,#b38728);color:#050505;text-decoration:none;font-weight:900;text-transform:uppercase;letter-spacing:.1em;font-size:.76rem}',
    metadata_json = JSON_SET(COALESCE(NULLIF(metadata_json, ''), JSON_OBJECT()), '$.visual_source', 'reviewed from vnv-events FAQ/service patterns')
WHERE id_owner = 2 AND site_key = 'vnvevents' AND template_key = 'faq-resource';

UPDATE cms_templates
SET css_text = '.cms-preview-template-service-landing{--food-ink:#1f1307;--food-soft:#3d2a1a;--food-muted:#6b5344;--food-cream:#faf7f2;--food-card:#fff;--food-accent:#c4a574;--food-green:#365314;--food-line:rgba(31,19,7,.08);background:linear-gradient(180deg,var(--food-cream),#fff 42%);color:var(--food-ink);font-family:Karla,Mulish,Inter,system-ui,sans-serif;line-height:1.75;border-radius:22px;overflow:hidden}.cms-preview-template-service-landing .cms-block{padding:42px 34px;margin:0;border:0}.cms-preview-template-service-landing .cms-block-hero{background:linear-gradient(135deg,#1f1307,#3d2a1a);color:#fff}.cms-preview-template-service-landing .cms-eyebrow{display:inline-flex;margin:0 0 14px;padding:8px 14px;border-radius:999px;background:rgba(196,165,116,.16);border:1px solid rgba(196,165,116,.38);color:#e8d1aa;font-size:.76rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em}.cms-preview-template-service-landing h1,.cms-preview-template-service-landing h2{letter-spacing:-.02em}.cms-preview-template-service-landing h1{max-width:760px;margin:0 0 14px;color:#fff;font-size:clamp(2rem,4vw,3.25rem);line-height:1.12;font-weight:900}.cms-preview-template-service-landing .cms-block-hero p:not(.cms-eyebrow){max-width:760px;color:rgba(255,255,255,.84);font-size:1.08rem}.cms-preview-template-service-landing .cms-block-body,.cms-preview-template-service-landing .cms-block-default,.cms-preview-template-service-landing .cms-block-faq{max-width:900px;margin:26px auto;background:var(--food-card);border:1px solid var(--food-line);border-radius:20px;box-shadow:0 18px 50px rgba(31,19,7,.08)}.cms-preview-template-service-landing .cms-block-body h2,.cms-preview-template-service-landing .cms-block-default h2,.cms-preview-template-service-landing .cms-block-faq h2{font-size:clamp(1.7rem,3vw,2.45rem);color:var(--food-ink);margin:0 0 14px}.cms-preview-template-service-landing .cms-block-body div,.cms-preview-template-service-landing .cms-block-default div{color:var(--food-soft);font-size:1.03rem}.cms-preview-template-service-landing .cms-block-gallery{background:#fff}.cms-preview-template-service-landing .cms-gallery-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0;border-radius:20px;overflow:hidden;border:1px solid var(--food-line)}.cms-preview-template-service-landing .cms-gallery-grid figure{margin:0;position:relative;background:#ede8e0}.cms-preview-template-service-landing .cms-gallery-grid img{width:100%;height:230px;object-fit:cover;display:block}.cms-preview-template-service-landing figcaption{position:absolute;left:0;right:0;bottom:0;padding:.65rem .85rem;color:#fff;background:linear-gradient(transparent,rgba(31,19,7,.75));font-size:.78rem}.cms-preview-template-service-landing details{border:1px solid var(--food-line);border-radius:14px;padding:15px 17px;margin-bottom:10px;background:#faf7f2}.cms-preview-template-service-landing summary{font-weight:900;cursor:pointer}.cms-preview-template-service-landing .cms-block-cta{max-width:900px;margin:30px auto;background:var(--food-ink);color:#fff;text-align:center;border-radius:20px}.cms-preview-template-service-landing .cms-block-cta a{display:inline-flex;align-items:center;justify-content:center;padding:.78rem 1.55rem;border-radius:999px;background:var(--food-accent);color:var(--food-ink);font-weight:900;text-decoration:none}@media(max-width:760px){.cms-preview-template-service-landing .cms-block{padding:30px 20px}}',
    metadata_json = JSON_SET(COALESCE(NULLIF(metadata_json, ''), JSON_OBJECT()), '$.visual_source', 'reviewed from vnv-gourmet CMS template and store tone')
WHERE id_owner = 2 AND site_key = 'avomeal' AND template_key = 'service-landing';

UPDATE cms_templates
SET css_text = '.cms-preview-template-editorial-guide{--food-ink:#1f1307;--food-soft:#3d2a1a;--food-muted:#6b5344;--food-cream:#faf7f2;--food-card:#fff;--food-accent:#c4a574;background:linear-gradient(180deg,var(--food-cream),#fff 46%);color:var(--food-ink);font-family:Karla,Mulish,Inter,system-ui,sans-serif;line-height:1.78;border-radius:22px;padding:34px 18px}.cms-preview-template-editorial-guide .cms-block{max-width:760px;margin:0 auto 28px;padding:0;border:0}.cms-preview-template-editorial-guide .cms-block-hero{max-width:760px;background:transparent;color:var(--food-ink)}.cms-preview-template-editorial-guide .cms-eyebrow{display:flex;gap:.5rem;color:var(--food-muted);font-size:.82rem;letter-spacing:.06em;text-transform:uppercase;font-weight:800;margin:0 0 1rem}.cms-preview-template-editorial-guide h1{font-size:clamp(1.9rem,4vw,2.65rem);font-weight:900;color:var(--food-ink);line-height:1.14;margin:0 0 1rem}.cms-preview-template-editorial-guide .cms-block-hero p:not(.cms-eyebrow){font-size:1.13rem;line-height:1.65;color:var(--food-soft);font-weight:500}.cms-preview-template-editorial-guide .cms-block-body,.cms-preview-template-editorial-guide .cms-block-gallery,.cms-preview-template-editorial-guide .cms-block-faq,.cms-preview-template-editorial-guide .cms-block-default{background:var(--food-card);border-radius:20px;box-shadow:0 18px 50px rgba(31,19,7,.08);border:1px solid rgba(31,19,7,.06);overflow:hidden;padding:2rem}.cms-preview-template-editorial-guide h2{font-size:clamp(1.5rem,3vw,2.1rem);font-weight:900;color:var(--food-ink);margin:0 0 1rem}.cms-preview-template-editorial-guide .cms-gallery-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;margin:-2rem -2rem 0}.cms-preview-template-editorial-guide .cms-gallery-grid figure{margin:0;position:relative;aspect-ratio:4/3;background:#ede8e0}.cms-preview-template-editorial-guide .cms-gallery-grid img{width:100%;height:100%;object-fit:cover;display:block}.cms-preview-template-editorial-guide details{padding:1rem 0;border-bottom:1px solid rgba(31,19,7,.08)}.cms-preview-template-editorial-guide summary{font-weight:900;cursor:pointer}.cms-preview-template-editorial-guide .cms-block-cta{background:var(--food-ink);color:#fff;text-align:center;border-radius:20px;padding:2rem}.cms-preview-template-editorial-guide .cms-block-cta a{display:inline-flex;padding:.75rem 1.5rem;border-radius:999px;background:var(--food-accent);color:var(--food-ink);font-weight:900;text-decoration:none}@media(max-width:640px){.cms-preview-template-editorial-guide{padding:24px 12px}.cms-preview-template-editorial-guide .cms-gallery-grid{grid-template-columns:1fr}}',
    metadata_json = JSON_SET(COALESCE(NULLIF(metadata_json, ''), JSON_OBJECT()), '$.visual_source', 'reviewed from vnv-gourmet test-template-new CMS article')
WHERE id_owner = 2 AND site_key = 'avomeal' AND template_key = 'editorial-guide';

UPDATE cms_templates
SET css_text = '.cms-preview-template-service-landing{--jm-bg:#05070a;--jm-panel:#10151d;--jm-panel-2:#161d28;--jm-ink:#f8fafc;--jm-muted:#a8b3c2;--jm-line:rgba(255,255,255,.1);--jm-cyan:#67e8f9;--jm-blue:#60a5fa;background:var(--jm-bg);color:var(--jm-ink);font-family:Inter,Plus Jakarta Sans,system-ui,sans-serif;line-height:1.75;border-radius:22px;overflow:hidden}.cms-preview-template-service-landing .cms-block{padding:46px 38px;margin:0;border:0}.cms-preview-template-service-landing .cms-block-hero{min-height:460px;display:grid;align-content:end;background:linear-gradient(135deg,#05070a,#10151d 58%,#172033);position:relative;overflow:hidden}.cms-preview-template-service-landing .cms-block-hero:after{content:"";position:absolute;inset:0;background:linear-gradient(120deg,rgba(96,165,250,.18),transparent 42%),radial-gradient(circle at 86% 18%,rgba(103,232,249,.18),transparent 28%);pointer-events:none}.cms-preview-template-service-landing .cms-eyebrow{position:relative;z-index:1;display:inline-flex;width:max-content;margin:0 0 16px;padding:8px 14px;border-radius:999px;border:1px solid rgba(103,232,249,.35);background:rgba(103,232,249,.08);color:var(--jm-cyan);font-size:.74rem;font-weight:900;text-transform:uppercase;letter-spacing:.14em}.cms-preview-template-service-landing h1{position:relative;z-index:1;max-width:900px;margin:0 0 16px;color:#fff;font-size:clamp(2.4rem,5vw,4.8rem);line-height:1.02;font-weight:900;letter-spacing:-.02em}.cms-preview-template-service-landing .cms-block-hero p:not(.cms-eyebrow){position:relative;z-index:1;max-width:760px;color:var(--jm-muted);font-size:1.06rem}.cms-preview-template-service-landing h2{color:#fff;font-size:clamp(1.8rem,3vw,2.8rem);letter-spacing:-.02em;margin:0 0 14px}.cms-preview-template-service-landing .cms-block-body,.cms-preview-template-service-landing .cms-block-default,.cms-preview-template-service-landing .cms-block-faq{background:var(--jm-panel);border:1px solid var(--jm-line);border-radius:20px;margin:26px 30px;color:var(--jm-muted);box-shadow:0 22px 60px rgba(0,0,0,.24)}.cms-preview-template-service-landing .cms-block-body strong,.cms-preview-template-service-landing .cms-block-default strong{color:#fff}.cms-preview-template-service-landing .cms-block-gallery{background:#080b10}.cms-preview-template-service-landing .cms-gallery-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.cms-preview-template-service-landing .cms-gallery-grid figure{margin:0;border-radius:18px;overflow:hidden;background:#111827;border:1px solid var(--jm-line)}.cms-preview-template-service-landing .cms-gallery-grid img{width:100%;height:250px;object-fit:cover;display:block;filter:saturate(1.05) contrast(1.04)}.cms-preview-template-service-landing details{border:1px solid var(--jm-line);border-radius:14px;padding:15px 17px;margin-bottom:10px;background:var(--jm-panel-2)}.cms-preview-template-service-landing summary{color:#fff;font-weight:900;cursor:pointer}.cms-preview-template-service-landing .cms-block-cta{background:linear-gradient(135deg,#0b1220,#172033);text-align:center;color:#fff}.cms-preview-template-service-landing .cms-block-cta a{display:inline-flex;padding:14px 24px;border-radius:999px;background:#fff;color:#05070a;text-decoration:none;font-weight:900;text-transform:uppercase;letter-spacing:.1em;font-size:.76rem}@media(max-width:760px){.cms-preview-template-service-landing .cms-block{padding:34px 20px}.cms-preview-template-service-landing .cms-block-body,.cms-preview-template-service-landing .cms-block-default,.cms-preview-template-service-landing .cms-block-faq{margin:18px 14px}}',
    metadata_json = JSON_SET(COALESCE(NULLIF(metadata_json, ''), JSON_OBJECT()), '$.visual_source', 'reviewed from jonnys-media public renderer and audiovisual brand direction')
WHERE id_owner = 2 AND site_key = 'jonnysmedia' AND template_key = 'service-landing';

COMMIT;
