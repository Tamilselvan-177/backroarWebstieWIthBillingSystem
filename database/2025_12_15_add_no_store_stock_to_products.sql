ALTER TABLE products
  ADD COLUMN no_store_stock TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

