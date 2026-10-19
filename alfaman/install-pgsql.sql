-- =====================================================================
-- ALFAMAN — SCOR Product Admin | PostgreSQL 13+ installation
-- HOW TO RUN:
--   1) Create an EMPTY database named "alfaman" from your hosting panel
--      (phpPgAdmin / cPanel PostgreSQL Databases).
--   2) Open THIS database, then run this whole file in its SQL window.
-- Policies:
--  * UTF8 database encoding (Arabic-safe) — choose UTF8 when creating the DB
--  * FK ON DELETE CASCADE from products to their specs/media/files
--  * UNIQUE(category_id, slug) prevents duplicate product URLs
--  * App DB user should hold only SELECT/INSERT/UPDATE/DELETE on this DB
-- =====================================================================

CREATE TABLE IF NOT EXISTS users (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'editor' CHECK (role IN ('admin','editor')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT,
  sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS products (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  category_id BIGINT NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  description TEXT,
  seo_title VARCHAR(180),
  seo_desc VARCHAR(320),
  status VARCHAR(10) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published')),
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (category_id, slug)
);
CREATE INDEX IF NOT EXISTS idx_products_status ON products(status);

CREATE OR REPLACE FUNCTION touch_updated_at() RETURNS trigger AS $$
BEGIN NEW.updated_at = CURRENT_TIMESTAMP; RETURN NEW; END;
$$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS trg_products_touch ON products;
CREATE TRIGGER trg_products_touch BEFORE UPDATE ON products
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE IF NOT EXISTS spec_groups (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  name VARCHAR(160) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS spec_rows (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  group_id BIGINT NOT NULL REFERENCES spec_groups(id) ON DELETE CASCADE,
  name VARCHAR(220) NOT NULL,
  value VARCHAR(320) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0
);

-- kind: 'slider' (main gallery w/ thumbnails) | 'extra' (cabin/engine/detail views)
CREATE TABLE IF NOT EXISTS media (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  path VARCHAR(255) NOT NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'slider' CHECK (kind IN ('slider','extra')),
  caption VARCHAR(255),
  is_main SMALLINT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS product_files (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  label VARCHAR(160) NOT NULL,
  path VARCHAR(255) NOT NULL
);

-- translations(entity: 'product'|'category', field: 'description'|'name'|'seo_desc', lang: 'ar'|'de'|'tr')
CREATE TABLE IF NOT EXISTS translations (
  entity VARCHAR(20) NOT NULL,
  entity_id BIGINT NOT NULL,
  field VARCHAR(40) NOT NULL,
  lang VARCHAR(5) NOT NULL,
  value TEXT,
  PRIMARY KEY (entity, entity_id, field, lang)
);

CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id BIGINT,
  action VARCHAR(80) NOT NULL,
  details VARCHAR(500),
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_log_time ON activity_log(created_at);

-- Optional commercial extras / attachments per model (bucket types, forks, cabins...)
CREATE TABLE IF NOT EXISTS product_extras (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  name VARCHAR(160) NOT NULL,
  description TEXT,
  image_path VARCHAR(255),
  sort_order INT NOT NULL DEFAULT 0
);
