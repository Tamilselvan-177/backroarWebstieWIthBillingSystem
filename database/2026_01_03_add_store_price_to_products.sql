-- Add store_price to products for POS shop pricing
ALTER TABLE `products`
  ADD COLUMN `store_price` DECIMAL(10,2) NULL AFTER `price`;

-- Optional: backfill existing rows to use price as store_price if desired
-- UPDATE `products` SET `store_price` = `price` WHERE `store_price` IS NULL;
