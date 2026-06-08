-- Manager store module schema.
-- Execute this file in each tenant database (for example, manager_0001).
-- Tenant isolation follows the Manager tenant-database model: each manager_XXXX
-- database owns its product, image and stock records.

CREATE TABLE IF NOT EXISTS products_categories (
  id int NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL,
  description varchar(255) DEFAULT NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_categories_name (name),
  KEY idx_products_categories_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id int NOT NULL AUTO_INCREMENT,
  user_id int NOT NULL,
  product_category_id int DEFAULT NULL,
  sku varchar(60) DEFAULT NULL,
  barcode varchar(80) DEFAULT NULL,
  name varchar(160) NOT NULL,
  description text DEFAULT NULL,
  cost_price decimal(12,2) NOT NULL DEFAULT 0.00,
  sale_price decimal(12,2) NOT NULL DEFAULT 0.00,
  stock_quantity decimal(12,3) NOT NULL DEFAULT 0.000,
  minimum_stock decimal(12,3) NOT NULL DEFAULT 0.000,
  unit varchar(20) NOT NULL DEFAULT 'UN',
  active tinyint(1) NOT NULL DEFAULT 1,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  UNIQUE KEY uq_products_barcode (barcode),
  KEY idx_products_user_id (user_id),
  KEY idx_products_category (product_category_id),
  KEY idx_products_name (name),
  KEY idx_products_active (active),
  KEY idx_products_stock_quantity (stock_quantity),
  CONSTRAINT fk_products_manager_main_users
    FOREIGN KEY (user_id) REFERENCES manager_main.users (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_products_categories
    FOREIGN KEY (product_category_id) REFERENCES products_categories (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products_images (
  id int NOT NULL AUTO_INCREMENT,
  product_id int NOT NULL,
  original_name varchar(255) DEFAULT NULL,
  file_path varchar(255) NOT NULL,
  thumb_path varchar(255) DEFAULT NULL,
  mime_type varchar(100) NOT NULL,
  file_size int unsigned DEFAULT NULL,
  width int unsigned DEFAULT NULL,
  height int unsigned DEFAULT NULL,
  position int NOT NULL DEFAULT 0,
  is_main tinyint(1) NOT NULL DEFAULT 0,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_images_file_path (file_path),
  KEY idx_products_images_product (product_id),
  KEY idx_products_images_main (product_id, is_main),
  CONSTRAINT fk_products_images_products
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products_stocks_movements (
  id int NOT NULL AUTO_INCREMENT,
  product_id int NOT NULL,
  movement_type enum('IN','OUT','ADJUSTMENT') NOT NULL,
  quantity decimal(12,3) NOT NULL,
  previous_quantity decimal(12,3) NOT NULL,
  new_quantity decimal(12,3) NOT NULL,
  unit_cost decimal(12,2) DEFAULT NULL,
  unit_price decimal(12,2) DEFAULT NULL,
  reference varchar(120) DEFAULT NULL,
  observations varchar(255) DEFAULT NULL,
  movement_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_stocks_movements_product (product_id),
  KEY idx_products_stocks_movements_type (movement_type),
  KEY idx_products_stocks_movements_date (movement_date),
  CONSTRAINT fk_products_stocks_movements_products
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (`version`, `description`, `created`, `modified`)
VALUES ('store-001', 'Store products, images and stock module', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  modified = NOW();
