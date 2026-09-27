-- Bubba Hub Standalone MySQL schema
-- Designed to live alongside an existing WordPress database.
-- IMPORTANT: do NOT create/select a new database here. Import this file while
-- the hosting control panel has selected database SCWORDPRESS-35303433c4eb.
-- All Bubba Hub tables use the bh_ prefix so existing WordPress tables are untouched.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS bh_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  role ENUM('family','leader','admin') NOT NULL DEFAULT 'family',
  status ENUM('active','pending','suspended') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bh_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_provider_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  business_name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  website_url TEXT NULL,
  booking_url TEXT NULL,
  address VARCHAR(255) NULL,
  town VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postcode VARCHAR(20) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  status ENUM('draft','pending','published','suspended') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bh_provider_user (user_id),
  KEY idx_bh_provider_town (town),
  CONSTRAINT fk_bh_provider_user FOREIGN KEY (user_id) REFERENCES bh_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bh_categories_slug (slug),
  KEY idx_bh_categories_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  county VARCHAR(120) NULL,
  postcode_prefix VARCHAR(20) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bh_locations_slug (slug),
  KEY idx_bh_locations_county (county),
  KEY idx_bh_locations_coords (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_activities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_id BIGINT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  description TEXT NULL,
  image_url TEXT NULL,
  address VARCHAR(255) NULL,
  town VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postcode VARCHAR(20) NULL,
  location_id BIGINT UNSIGNED NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  category VARCHAR(120) NULL,
  age_min DECIMAL(4,1) NULL,
  age_max DECIMAL(4,1) NULL,
  price DECIMAL(10,2) NULL,
  price_label VARCHAR(120) NULL,
  session_length VARCHAR(120) NULL,
  website_url TEXT NULL,
  booking_url TEXT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','pending','published','archived') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bh_activities_slug (slug),
  KEY idx_bh_activities_status (status),
  KEY idx_bh_activities_town (town),
  KEY idx_bh_activities_category (category),
  KEY idx_bh_activities_age (age_min, age_max),
  KEY idx_bh_activities_location (latitude, longitude),
  KEY idx_bh_activities_provider (provider_id),
  KEY idx_bh_activities_location_id (location_id),
  CONSTRAINT fk_bh_activity_provider FOREIGN KEY (provider_id) REFERENCES bh_provider_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_activity_location FOREIGN KEY (location_id) REFERENCES bh_locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_activity_categories (
  activity_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (activity_id, category_id),
  CONSTRAINT fk_bh_ac_activity FOREIGN KEY (activity_id) REFERENCES bh_activities(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_ac_category FOREIGN KEY (category_id) REFERENCES bh_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_activity_days (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  activity_id BIGINT UNSIGNED NOT NULL,
  day_name VARCHAR(20) NOT NULL,
  day_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bh_activity_day (activity_id, day_name),
  CONSTRAINT fk_bh_activity_days_activity FOREIGN KEY (activity_id) REFERENCES bh_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_activity_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  activity_id BIGINT UNSIGNED NOT NULL,
  day_name VARCHAR(20) NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  session_length VARCHAR(120) NULL,
  capacity INT UNSIGNED NULL,
  price DECIMAL(10,2) NULL,
  price_label VARCHAR(120) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_bh_sessions_activity (activity_id),
  KEY idx_bh_sessions_day (day_name),
  CONSTRAINT fk_bh_sessions_activity FOREIGN KEY (activity_id) REFERENCES bh_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_activity_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  activity_id BIGINT UNSIGNED NOT NULL,
  image_url TEXT NOT NULL,
  alt_text VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bh_images_activity (activity_id, sort_order),
  CONSTRAINT fk_bh_images_activity FOREIGN KEY (activity_id) REFERENCES bh_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compatibility view is deliberately omitted: the API will use bh_ tables directly.

SET FOREIGN_KEY_CHECKS = 1;
