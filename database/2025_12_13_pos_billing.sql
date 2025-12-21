-- POS billing sequence and payments

CREATE TABLE IF NOT EXISTS bill_sequences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    financial_year VARCHAR(9) NOT NULL,
    current_seq INT UNSIGNED NOT NULL DEFAULT 0,
    prefix VARCHAR(20) DEFAULT 'POS',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_store_year (store_id, financial_year),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pos_order_id INT UNSIGNED NOT NULL,
    method ENUM('Cash','Card','UPI') NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pos_order_id) REFERENCES pos_orders(id) ON DELETE CASCADE,
    INDEX idx_order (pos_order_id),
    INDEX idx_method (method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

