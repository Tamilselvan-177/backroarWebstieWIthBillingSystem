-- POS orders and returns, plus GST fields

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS gst_percent DECIMAL(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS barcode VARCHAR(64) UNIQUE NULL;

CREATE TABLE IF NOT EXISTS pos_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    store_id INT UNSIGNED NOT NULL,
    financial_year VARCHAR(9) NOT NULL,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(15),
    staff_id INT UNSIGNED NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    service_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    gst_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    cash_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    card_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    upi_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payments_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_store (store_id),
    INDEX idx_year (financial_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pos_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pos_order_id) REFERENCES pos_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (pos_order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS returns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pos_order_id INT UNSIGNED NULL,
    pos_order_item_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    order_item_id INT UNSIGNED NULL,
    store_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    refund_method ENUM('Cash','Card','UPI') NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    gst_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pos_order_id) REFERENCES pos_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (pos_order_item_id) REFERENCES pos_order_items(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_store (store_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example GST daily summary (POS)
-- SELECT DATE(created_at) as day,
--        SUM(subtotal) as subtotal,
--        SUM(discount_total) as discounts,
--        SUM(gst_total) as gst,
--        SUM(grand_total) as grand_total
-- FROM pos_orders
-- WHERE store_id = :store_id
-- GROUP BY DATE(created_at)
-- ORDER BY day DESC;
