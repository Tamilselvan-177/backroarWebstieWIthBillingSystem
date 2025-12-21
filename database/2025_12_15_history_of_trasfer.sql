-- Create stock_transfers table for transfer history
CREATE TABLE IF NOT EXISTS `stock_transfers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(50) NOT NULL,
  `source_store_id` int unsigned NOT NULL,
  `dest_store_id` int unsigned NOT NULL,
  `initiated_by` int unsigned NOT NULL,
  `status` enum('PENDING','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `total_items` int NOT NULL DEFAULT 0,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `source_store_id` (`source_store_id`),
  KEY `dest_store_id` (`dest_store_id`),
  KEY `initiated_by` (`initiated_by`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create stock_transfer_items table for individual products in transfer
CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `quantity` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transfer_id` (`transfer_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;