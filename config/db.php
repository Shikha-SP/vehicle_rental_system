<?php
// DB.php
// This file connects to MySQL using mysqli and automatically creates
// the database and tables for the Vehicle Rental System, including
// a generic 'vehicles' table that enforces license category restrictions.

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "vehicle_rental_db";

// Step 1: Connect to MySQL server
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Step 2: Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Step 3: Select database
$conn->select_db($dbname);

// Step 4: Create 'users' table
$conn->query("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    address VARCHAR(255),
    phone_number VARCHAR(15),
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    license_number VARCHAR(50),
    license_type ENUM('A','K','B','C','D','E') DEFAULT 'B',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(64) DEFAULT NULL,
    token_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Step 5: Create 'vehicles' table
$conn->query("
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    model VARCHAR(100) NOT NULL,
    license_type ENUM('A','B','C','D','E') NOT NULL DEFAULT 'B',
    transmission ENUM('Manual','Automatic') NOT NULL,
    fuel_type ENUM('Petrol','Diesel','Electric','Hybrid','CNG') NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    color VARCHAR(20) DEFAULT '#e03030',
    top_speed INT,
    fuel_capacity INT,
    image_path VARCHAR(255),
    status ENUM('pending','approved','rejected','available','rented') DEFAULT 'pending',
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Step 6: Create 'bookings' table
$conn->query("
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('confirmed', 'cancelled', 'completed') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
)");

$conn->query("
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id INT NOT NULL,
    user_id INT NOT NULL,

    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'card',

    card_last4 VARCHAR(4),         -- last 4 digits only (security)
    card_type VARCHAR(20),         -- Visa, Mastercard

    transaction_ref VARCHAR(100),  -- fake reference ID

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);");
// confirmation
// echo "Database and tables are ready.";
?>