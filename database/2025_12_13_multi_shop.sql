-- Multi-shop foundation
CREATE TABLE IF NOT EXISTS stores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    gstin VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO stores (code, name, city, state, is_active) VALUES
('WM1', 'Main Branch', 'Surat', 'Gujarat', 1),
('WM2', 'Branch 2', 'Surat', 'Gujarat', 1),
('WM3', 'Branch 3', 'Surat', 'Gujarat', 1);

CREATE TABLE IF NOT EXISTS store_stock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_store_product (store_id, product_id),
    INDEX idx_store (store_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_store_id INT UNSIGNED NOT NULL,
    to_store_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    transferred_by INT UNSIGNED,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (to_store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (transferred_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_from_to (from_store_id, to_store_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_store_stock_ai;
DROP TRIGGER IF EXISTS trg_store_stock_au;
DROP TRIGGER IF EXISTS trg_store_stock_ad;

DELIMITER $$
CREATE TRIGGER trg_store_stock_ai AFTER INSERT ON store_stock
FOR EACH ROW
BEGIN
    UPDATE products p
    SET p.stock_quantity = (
        SELECT COALESCE(SUM(ss.quantity), 0)
        FROM store_stock ss
        WHERE ss.product_id = NEW.product_id
    )
    WHERE p.id = NEW.product_id;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_store_stock_au AFTER UPDATE ON store_stock
FOR EACH ROW
BEGIN
    UPDATE products p
    SET p.stock_quantity = (
        SELECT COALESCE(SUM(ss.quantity), 0)
        FROM store_stock ss
        WHERE ss.product_id = NEW.product_id
    )
    WHERE p.id = NEW.product_id;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_store_stock_ad AFTER DELETE ON store_stock
FOR EACH ROW
BEGIN
    UPDATE products p
    SET p.stock_quantity = (
        SELECT COALESCE(SUM(ss.quantity), 0)
        FROM store_stock ss
        WHERE ss.product_id = OLD.product_id
    )
    WHERE p.id = OLD.product_id;
END$$
DELIMITER ;
