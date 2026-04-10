<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in and NOT admin
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../renter/list_Car.php");
    exit;
}

// Get form data - MATCHING YOUR FORM FIELDS
$user_id = $_SESSION['user_id'];
$model = $_POST['model'] ?? '';
$license_type = $_POST['license_type'] ?? '';
$transmission = $_POST['transmission'] ?? '';
$fuel_type = $_POST['fuel_type'] ?? '';
$price = $_POST['price'] ?? 2500;
$color = $_POST['color'] ?? '#e03030';
$top_speed = $_POST['kms'] ?? '';           // 'kms' from form stores top speed
$fuel_capacity = $_POST['fuel_capacity'] ?? '';

// Validate required fields
if (empty($model) || empty($license_type) || empty($transmission) || 
    empty($fuel_type) || empty($top_speed) || empty($fuel_capacity)) {
    $_SESSION['error'] = "Please fill all required fields";
    header("Location: ../renter/list_Car.php");
    exit;
}

// Validate license_type values
$valid_license_types = ['A', 'B', 'C', 'D', 'E'];
if (!in_array($license_type, $valid_license_types)) {
    $_SESSION['error'] = "Invalid license type selected";
    header("Location: ../renter/list_Car.php");
    exit;
}

// Validate transmission
$valid_transmissions = ['Manual', 'Automatic'];
if (!in_array($transmission, $valid_transmissions)) {
    $_SESSION['error'] = "Invalid transmission type";
    header("Location: ../renter/list_Car.php");
    exit;
}

// Validate fuel type
$valid_fuel_types = ['Petrol', 'Diesel', 'Electric', 'Hybrid', 'CNG'];
if (!in_array($fuel_type, $valid_fuel_types)) {
    $_SESSION['error'] = "Invalid fuel type";
    header("Location: ../renter/list_Car.php");
    exit;
}

// Handle image upload
$image_path = null;
if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../uploads/vehicles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $file_type = $_FILES['vehicle_image']['type'];
    
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error'] = "Only JPG, PNG, and WEBP images are allowed";
        header("Location: ../renter/list_Car.php");
        exit;
    }
    
    $file_extension = pathinfo($_FILES['vehicle_image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $file_extension;
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $destination)) {
        $image_path = 'uploads/vehicles/' . $filename;
    } else {
        $_SESSION['error'] = "Failed to upload image";
        header("Location: ../renter/list_Car.php");
        exit;
    }
}

// Insert vehicle with pending status
$status = 'pending';

$sql = "INSERT INTO vehicles (user_id, model, license_type, transmission, fuel_type, 
        price_per_day, color, top_speed, fuel_capacity, image_path, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issssdsiiss", 
    $user_id, $model, $license_type, $transmission, 
    $fuel_type, $price, $color, $top_speed, $fuel_capacity, 
    $image_path, $status
);

if ($stmt->execute()) {
    $_SESSION['success'] = "Your vehicle has been submitted for admin approval. You'll be notified once approved.";
    header("Location: ../renter/my_vehicles.php");
} else {
    $_SESSION['error'] = "Failed to submit vehicle: " . $conn->error;
    header("Location: ../renter/list_Car.php");
}

$stmt->close();
$conn->close();
exit;
?>