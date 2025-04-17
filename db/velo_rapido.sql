-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS velo_rapido;
USE velo_rapido;
-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active'
);
-- Bikes table
CREATE TABLE IF NOT EXISTS bikes (
    bike_id INT AUTO_INCREMENT PRIMARY KEY,
    bike_name VARCHAR(100) NOT NULL,
    bike_type VARCHAR(50) NOT NULL,
    specifications TEXT,
    image_path VARCHAR(255),
    hourly_rate DECIMAL(10, 2) NOT NULL,
    status ENUM('available', 'reserved', 'maintenance') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- Reservations table
CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bike_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    dropoff_location VARCHAR(255) NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (bike_id) REFERENCES bikes(bike_id)
);
-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('card', 'cod', 'upi') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    transaction_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id)
);
-- Damages table
CREATE TABLE IF NOT EXISTS damages (
    damage_id INT AUTO_INCREMENT PRIMARY KEY,
    bike_id INT NOT NULL,
    user_id INT NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255),
    status ENUM('reported', 'under_review', 'resolved') NOT NULL DEFAULT 'reported',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bike_id) REFERENCES bikes(bike_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
-- Maintenance table
CREATE TABLE IF NOT EXISTS maintenance (
    maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
    bike_id INT NOT NULL,
    description TEXT NOT NULL,
    maintenance_type VARCHAR(50) DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    completion_date DATE DEFAULT NULL,
    status ENUM('scheduled', 'in_progress', 'completed') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bike_id) REFERENCES bikes(bike_id)
);
-- Insert admin user (password: admin123)
INSERT INTO users (first_name, last_name, email, password, role)
VALUES (
        'Admin',
        'User',
        'admin@velorapido.com',
        '$2y$10$6vYwGAvnA8HHNoUQgM8xOuS5JxDvBxF6Sz5BLYyIZWw5esNbNGgHm',
        'admin'
    ) ON DUPLICATE KEY
UPDATE email = email;
-- Insert sample bikes
INSERT INTO bikes (
        bike_name,
        bike_type,
        specifications,
        hourly_rate,
        status
    )
VALUES (
        'Hero Lectro',
        'E-Bike',
        '250W motor, 25km range, 7-speed, lightweight frame',
        150.00,
        'available'
    ),
    (
        'Activa 6G',
        'Scooty',
        '110cc engine, disc brake, tubeless tires, comfortable seat',
        120.00,
        'available'
    ),
    (
        'TVS Jupiter',
        'Scooty',
        '110cc engine, 5.8 liter fuel tank, telescopic suspension',
        100.00,
        'available'
    ),
    (
        'Hero Splendor',
        'Motorcycle',
        '100cc engine, 60kmpl mileage, comfortable for long rides',
        200.00,
        'available'
    ),
    (
        'Firefox MTB',
        'Mountain Bike',
        '21-speed, front suspension, disc brakes, sturdy frame',
        80.00,
        'available'
    ),
    (
        'Hercules Roadeo',
        'City Bike',
        '18-speed, comfortable seat, ideal for city commuting',
        60.00,
        'available'
    ) ON DUPLICATE KEY
UPDATE bike_name = bike_name;