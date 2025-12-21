-- Returns table for POS and web order returns

CREATE TABLE IF NOT EXISTS returns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    refund_method VARCHAR(20) NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    gst_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255),
    pos_order_id INT UNSIGNED NULL,
    pos_order_item_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    order_item_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (pos_order_id) REFERENCES pos_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (pos_order_item_id) REFERENCES pos_order_items(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,
    INDEX idx_store (store_id),
    INDEX idx_product (product_id),
    INDEX idx_pos_order (pos_order_id),
    INDEX idx_order (order_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

