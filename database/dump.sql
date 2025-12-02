-- Sample data for testing the cart page
-- Usage: edit the USER_EMAIL placeholder below to match your login email,
-- then run: mysql -u root -p < database/dump.sql

USE ecommerce_db;

-- ------------------------------------------------------------
-- NOTE: Replace the email value in the SELECT assigning @userId
-- with the email you use to login to the app.
-- Example: SELECT id INTO @userId FROM users WHERE email = 'you@domain.com';
-- ------------------------------------------------------------

-- Find the user id by email (replace the email below)
SELECT id INTO @userId FROM users WHERE email = 'aktamil13@gmail.com';

-- If you prefer to use a numeric id instead, you can set it manually:
-- SET @userId = 1;

-- Insert sample products (use INSERT IGNORE so re-running is safe)
INSERT IGNORE INTO products (category_id, subcategory_id, brand_id, model_id, name, slug, description, price, sale_price, sku, stock_quantity, is_active)
VALUES
(2, 2, 1, 1, 'iPhone 15 Pro Max Designer Case', 'iphone-15-pro-max-designer-case', 'Hard protective case with designer print', 799.00, 699.00, 'CASE-IP15PM-001', 25, 1),
(2, 11, 5, NULL, 'Redmi Note Soft Silicon Case', 'redmi-note-soft-silicon-case', 'Flexible silicon case for Redmi series', 299.00, NULL, 'CASE-REDMI-001', 40, 1),
(3, 3, 2, NULL, 'Bluetooth Headphones - BassPro', 'bluetooth-headphones-basspro', 'Wireless over-ear headphones', 1499.00, 1299.00, 'GAD-BH-001', 15, 1);

-- Capture the last inserted ids for the three products (works if these were inserted now)
SET @p1 = (SELECT id FROM products WHERE slug = 'iphone-15-pro-max-designer-case' LIMIT 1);
SET @p2 = (SELECT id FROM products WHERE slug = 'redmi-note-soft-silicon-case' LIMIT 1);
SET @p3 = (SELECT id FROM products WHERE slug = 'bluetooth-headphones-basspro' LIMIT 1);

-- Insert product images (paths are examples; ensure files exist under public/images/... or use placeholders)
INSERT IGNORE INTO product_images (product_id, image_path, is_primary, display_order)
VALUES
(@p1, '/images/products/iphone_case_1.webp', 1, 1),
(@p1, '/images/products/iphone_case_2.webp', 0, 2),
(@p2, '/images/products/redmi_case_1.webp', 1, 1),
(@p3, '/images/products/headphones_1.webp', 1, 1);

-- Insert cart items for the found user (will error if @userId is NULL)
-- You can replace @userId with a numeric id if you prefer.

-- Safe insert: only insert if @userId exists
INSERT INTO cart_items (user_id, product_id, quantity)
SELECT @userId, @p1, 2 FROM DUAL WHERE @userId IS NOT NULL
UNION ALL
SELECT @userId, @p2, 1 FROM DUAL WHERE @userId IS NOT NULL;

-- OPTIONAL: Check inserted cart rows
-- SELECT ci.id, ci.user_id, ci.product_id, ci.quantity, p.name FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.user_id = @userId;

-- End of dump
