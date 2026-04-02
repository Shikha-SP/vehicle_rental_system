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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Step 5: Create 'vehicles' table
$conn->query("
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    required_license ENUM('A','K','B','C','D','E') NOT NULL DEFAULT 'B',
    fuel_range INT,
    status ENUM('available','rented') DEFAULT 'available',
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    status ENUM('pending','confirmed','completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
)");

// confirmation
echo "Database and tables are ready.";
?>