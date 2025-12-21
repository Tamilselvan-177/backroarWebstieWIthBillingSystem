ALTER TABLE users 
MODIFY COLUMN role ENUM('customer','admin','staff') DEFAULT 'customer';
