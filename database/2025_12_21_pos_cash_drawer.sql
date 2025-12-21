CREATE TABLE IF NOT EXISTS pos_cash_drawer_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id INT UNSIGNED NOT NULL,
  counter_id INT UNSIGNED NULL,
  staff_id INT UNSIGNED NULL,
  opening_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  opening_notes VARCHAR(255),
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  closing_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  variance DECIMAL(10,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE SET NULL,
  FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_store (store_id),
  INDEX idx_counter (counter_id),
  INDEX idx_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_cash_drawer_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL,
  event_type ENUM('OPEN','CLOSE','PAID_IN','PAID_OUT','KICK') NOT NULL,
  amount DECIMAL(10,2) NULL,
  reason VARCHAR(255),
  order_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES pos_cash_drawer_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (order_id) REFERENCES pos_orders(id) ON DELETE SET NULL,
  INDEX idx_session (session_id),
  INDEX idx_type (event_type),
  INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pos_orders
  ADD COLUMN IF NOT EXISTS change_breakdown_json TEXT;
