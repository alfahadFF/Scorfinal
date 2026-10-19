-- =====================================================================
-- ALFAMAN — SCOR Product Admin | MySQL 8 / MariaDB 10.4+ installation
-- Policies:
--  * utf8mb4 (Arabic-safe), InnoDB, FK ON DELETE CASCADE from products
--  * UNIQUE(category_id, slug) prevents duplicate product URLs
--  * App DB user must be limited to SELECT/INSERT/UPDATE/DELETE on this DB only:
--      CREATE USER 'alfaman'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
--      GRANT SELECT, INSERT, UPDATE, DELETE ON alfaman.* TO 'alfaman'@'localhost';
-- =====================================================================
CREATE DATABASE IF NOT EXISTS alfaman CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE alfaman;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  description TEXT,
  seo_title VARCHAR(180) DEFAULT NULL,
  seo_desc VARCHAR(320) DEFAULT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_slug (category_id, slug),
  KEY idx_status (status),
  CONSTRAINT fk_product_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS spec_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_group_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS spec_rows (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  name VARCHAR(220) NOT NULL,
  value VARCHAR(320) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_row_group FOREIGN KEY (group_id) REFERENCES spec_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- kind: 'slider' (main gallery w/ thumbnails) | 'extra' (cabin/engine/detail views)
CREATE TABLE IF NOT EXISTS media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  kind ENUM('slider','extra') NOT NULL DEFAULT 'slider',
  caption VARCHAR(255) DEFAULT NULL,
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_media_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  label VARCHAR(160) NOT NULL,
  path VARCHAR(255) NOT NULL,
  CONSTRAINT fk_file_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- translations(entity: 'product'|'category', field: 'description'|'name'|'seo_desc', lang: 'ar'|'de'|'tr')
CREATE TABLE IF NOT EXISTS translations (
  entity VARCHAR(20) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  field VARCHAR(40) NOT NULL,
  lang VARCHAR(5) NOT NULL,
  value TEXT,
  PRIMARY KEY (entity, entity_id, field, lang)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  details VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log_time (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_extras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT,
  image_path VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_extra_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
