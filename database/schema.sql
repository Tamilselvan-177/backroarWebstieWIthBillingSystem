-- ============================================================
-- MOBILE BACKCASE E-COMMERCE DATABASE SCHEMA
-- ============================================================

CREATE DATABASE IF NOT EXISTS ecommerce_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecommerce_db;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(15),
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MAIN CATEGORIES (4 Fixed Categories)
-- ============================================================
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_path VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 4 Main Categories
INSERT INTO categories (name, slug, description, display_order) VALUES
('Mobile Phones', 'mobile-phones', 'Latest smartphones and mobile devices', 1),
('Mobile Backcases', 'mobile-backcases', 'Protective cases for all mobile brands', 2),
('Gadgets', 'gadgets', 'Mobile accessories and gadgets', 3),
('Tempered Glass', 'tempered-glass', 'Screen protectors and tempered glass', 4);

-- ============================================================
-- SUBCATEGORIES
-- ============================================================
CREATE TABLE subcategories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_slug (slug),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_category_slug (category_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Mobile Backcase Types (Category ID = 2)
INSERT INTO subcategories (category_id, name, slug, display_order) VALUES
(2, 'Flip Cases', 'flip-cases', 1),
(2, 'Silicon Case', 'silicon-case', 2),
(2, 'Transparent Case', 'transparent-case', 3),
(2, 'Ring Cases', 'ring-cases', 4),
(2, 'Diamond Cases', 'diamond-cases', 5),
(2, 'Metal Cases', 'metal-cases', 6),
(2, 'Photo Print Cases', 'photo-print-cases', 7),
(2, 'Anime Cases', 'anime-cases', 8),
(2, 'Car Logo Cases', 'car-logo-cases', 9),
(2, 'Hard Cases', 'hard-cases', 10),
(2, 'Fiber Case', 'fiber-case', 11),
(2, 'Toy Silicon Case', 'toy-silicon-case', 12),
(2, 'Premium Cases', 'premium-cases', 13);

-- Insert Gadget Types (Category ID = 3)
INSERT INTO subcategories (category_id, name, slug, display_order) VALUES
(3, 'Cables', 'cables', 1),
(3, 'Chargers', 'chargers', 2),
(3, 'Headphones', 'headphones', 3),
(3, 'Speakers', 'speakers', 4),
(3, 'Airpods', 'airpods', 5),
(3, 'Neckbands', 'neckbands', 6),
(3, 'Power Banks', 'power-banks', 7),
(3, 'Mobile Stands', 'mobile-stands', 8),
(3, 'Others', 'others', 9);

-- Insert Tempered Glass Types (Category ID = 4)
INSERT INTO subcategories (category_id, name, slug, display_order) VALUES
(4, 'Clear Temper', 'clear-temper', 1),
(4, 'Matte Temper', 'matte-temper', 2),
(4, 'UV Temper', 'uv-temper', 3),
(4, 'Full Glue Temper', 'full-glue-temper', 4),
(4, 'Privacy Temper', 'privacy-temper', 5),
(4, 'Borderless Temper', 'borderless-temper', 6),
(4, 'Clear Sheet', 'clear-sheet', 7),
(4, 'Matte Sheet', 'matte-sheet', 8),
(4, 'Bullet Proof Temper', 'bullet-proof-temper', 9),
(4, 'Mirror Temper', 'mirror-temper', 10);

-- ============================================================
-- BRANDS
-- ============================================================
CREATE TABLE brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    logo_path VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Brands
INSERT INTO brands (name, slug, display_order) VALUES
('iPhone', 'iphone', 1),
('Samsung', 'samsung', 2),
('Vivo', 'vivo', 3),
('Oppo', 'oppo', 4),
('Redmi', 'redmi', 5),
('OnePlus', 'oneplus', 6),
('Moto', 'moto', 7),
('Realme', 'realme', 8),
('Lava', 'lava', 9),
('Infinix', 'infinix', 10);

-- ============================================================
-- MODELS (Each Brand has Multiple Models)
-- ============================================================
CREATE TABLE models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    model_number VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    INDEX idx_brand (brand_id),
    INDEX idx_slug (slug),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_brand_model (brand_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Models for iPhone
INSERT INTO models (brand_id, name, slug, model_number) VALUES
(1, 'iPhone 15 Pro Max', 'iphone-15-pro-max', 'A2849'),
(1, 'iPhone 15 Pro', 'iphone-15-pro', 'A2848'),
(1, 'iPhone 15', 'iphone-15', 'A2846'),
(1, 'iPhone 14 Pro Max', 'iphone-14-pro-max', 'A2651'),
(1, 'iPhone 14 Pro', 'iphone-14-pro', 'A2650'),
(1, 'iPhone 14', 'iphone-14', 'A2649'),
(1, 'iPhone 13 Pro Max', 'iphone-13-pro-max', 'A2484'),
(1, 'iPhone 13', 'iphone-13', 'A2482'),
(1, 'iPhone 12', 'iphone-12', 'A2403'),
(1, 'iPhone 11', 'iphone-11', 'A2221');

-- Sample Models for Samsung
INSERT INTO models (brand_id, name, slug, model_number) VALUES
(2, 'Galaxy S24 Ultra', 'galaxy-s24-ultra', 'SM-S928'),
(2, 'Galaxy S23 Ultra', 'galaxy-s23-ultra', 'SM-S918'),
(2, 'Galaxy S23', 'galaxy-s23', 'SM-S911'),
(2, 'Galaxy A54', 'galaxy-a54', 'SM-A546'),
(2, 'Galaxy A34', 'galaxy-a34', 'SM-A346'),
(2, 'Galaxy M34', 'galaxy-m34', 'SM-M346');

-- ============================================================
-- PRODUCTS TABLE (Supports All 4 Categories)
-- ============================================================
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    subcategory_id INT UNSIGNED,
    brand_id INT UNSIGNED,
    model_id INT UNSIGNED,
    
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2),
    cost_price DECIMAL(10,2),
    
    sku VARCHAR(100) UNIQUE,
    stock_quantity INT DEFAULT 0,
    
    is_featured TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    
    meta_title VARCHAR(255),
    meta_description TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE SET NULL,
    
    INDEX idx_category (category_id),
    INDEX idx_subcategory (subcategory_id),
    INDEX idx_brand (brand_id),
    INDEX idx_model (model_id),
    INDEX idx_slug (slug),
    INDEX idx_sku (sku),
    INDEX idx_price (price),
    INDEX idx_featured (is_featured),
    INDEX idx_active (is_active),
    INDEX idx_stock (stock_quantity),
    FULLTEXT INDEX ft_search (name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PRODUCT IMAGES (Batch Storage System)
-- ============================================================
CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ADDRESSES
-- ============================================================
CREATE TABLE addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CART ITEMS
-- ============================================================
CREATE TABLE cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WISHLIST
-- ============================================================
CREATE TABLE wishlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PRODUCT REVIEWS
-- ============================================================
CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(150),
    comment TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    is_verified_purchase TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (user_id, product_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ORDERS
-- ============================================================
CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    
    subtotal DECIMAL(10,2) NOT NULL,
    shipping_charge DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    
    payment_method ENUM('COD', 'Online') DEFAULT 'COD',
    payment_status ENUM('Pending', 'Paid', 'Failed') DEFAULT 'Pending',
    order_status ENUM('Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled') DEFAULT 'Pending',
    
    shipping_name VARCHAR(100) NOT NULL,
    shipping_phone VARCHAR(15) NOT NULL,
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_state VARCHAR(100) NOT NULL,
    shipping_pincode VARCHAR(10) NOT NULL,
    
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (order_status),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ORDER ITEMS
-- ============================================================
CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAYMENT LOGS
-- ============================================================
CREATE TABLE payment_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(255),
    amount DECIMAL(10,2),
    status ENUM('Success', 'Failed', 'Pending') DEFAULT 'Pending',
    response_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;