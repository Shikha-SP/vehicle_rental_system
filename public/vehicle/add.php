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
    header("Location: ../renter/list_car.php");
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

// Validate required fields individually for better debugging
$required_fields = [
    'model' => 'Make & Model',
    'license_type' => 'Vehicle Class',
    'transmission' => 'Transmission',
    'fuel_type' => 'Fuel Type',
    'kms' => 'Top Speed',
    'fuel_capacity' => 'Fuel Capacity'
];

foreach ($required_fields as $field => $label) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        $_SESSION['message'] = "The field '$label' is required.";
        $_SESSION['message_type'] = "error";
        header("Location: ../renter/list_car.php");
        exit;
    }
}

// Validate license_type values
$valid_license_types = ['A', 'B', 'C', 'D', 'E'];
if (!in_array($license_type, $valid_license_types)) {
    $_SESSION['message'] = "Invalid license type selected";
    $_SESSION['message_type'] = "error";
    header("Location: ../renter/list_car.php");
    exit;
}

// Validate transmission
$valid_transmissions = ['Manual', 'Automatic'];
if (!in_array($transmission, $valid_transmissions)) {
    $_SESSION['message'] = "Invalid transmission type";
    $_SESSION['message_type'] = "error";
    header("Location: ../renter/list_car.php");
    exit;
}

// Validate fuel type
$valid_fuel_types = ['Petrol', 'Diesel', 'Electric', 'Hybrid', 'CNG'];
if (!in_array($fuel_type, $valid_fuel_types)) {
    $_SESSION['message'] = "Invalid fuel type";
    $_SESSION['message_type'] = "error";
    header("Location: ../renter/list_car.php");
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
        $_SESSION['message'] = "Only JPG, PNG, and WEBP images are allowed";
        $_SESSION['message_type'] = "error";
        header("Location: ../renter/list_car.php");
        exit;
    }
    
    $file_extension = pathinfo($_FILES['vehicle_image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $file_extension;
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $destination)) {
        $image_path = 'uploads/vehicles/' . $filename;
    } else {
        $_SESSION['message'] = "Failed to upload image";
        $_SESSION['message_type'] = "error";
        header("Location: ../renter/list_car.php");
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
    $_SESSION['message'] = "Your vehicle has been submitted for admin approval. You'll be notified once approved.";
    $_SESSION['message_type'] = "success";
    header("Location: ../renter/my_vehicles.php");
} else {
    $_SESSION['message'] = "Failed to submit vehicle: " . $conn->error;
    $_SESSION['message_type'] = "error";
    header("Location: ../renter/list_Car.php");
}

$stmt->close();
$conn->close();
exit;
?>