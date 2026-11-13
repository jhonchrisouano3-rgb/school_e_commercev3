CREATE DATABASE IF NOT EXISTS campusmart_db;
USE campusmart_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','student','teacher','seller') NOT NULL,
    school_picture VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image VARCHAR(255),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id)
);

CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    UNIQUE KEY user_product (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reference_number VARCHAR(100) NOT NULL UNIQUE,
    total DECIMAL(10,2) NOT NULL,
    delivery_method ENUM('pickup','cod') NOT NULL,
    address TEXT,
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('unpaid','paid','failed','refunded') DEFAULT 'unpaid',
    order_status ENUM('pending','processing','ready','shipped','completed','cancelled') DEFAULT 'pending',
    paymongo_session_id VARCHAR(100) DEFAULT NULL,
    checkout_url TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (seller_id) REFERENCES users(id)
);

CREATE TABLE payment_webhook_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(150) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Demo administrator:
-- School ID: admin001
-- Password: Admin123!
-- The login code below upgrades this legacy password to a secure hash on first login.
INSERT INTO users (school_id, full_name, password, role, status)
VALUES ('admin001', 'System Administrator', 'Admin123!', 'admin', 'approved');