<?php
/**
 * Delete Vehicle Script
 * 
 * This script is responsible for deleting a vehicle listed by a renter.
 * It ensures that the user is authorized, the vehicle belongs to them, 
 * and that the vehicle cannot be deleted if it has active bookings.
 */
session_start();
require_once '../../config/db.php';

// Check if user is logged in and not admin
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

// Check if vehicle ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Invalid vehicle ID";
    $_SESSION['message_type'] = "error";
    header("Location: my_vehicles.php");
    exit;
}

$vehicle_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// First, check if the vehicle exists and belongs to the user
$check_sql = "SELECT id, status FROM vehicles WHERE id = ? AND user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $vehicle_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $_SESSION['message'] = "Vehicle not found or you don't have permission to delete it";
    $_SESSION['message_type'] = "error";
    header("Location: my_vehicles.php");
    exit;
}

$vehicle = $check_result->fetch_assoc();

// Optional: Check if vehicle has any active bookings before deleting
$booking_sql = "SELECT COUNT(*) as active_bookings FROM bookings 
                WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'ongoing')";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("i", $vehicle_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$bookings = $booking_result->fetch_assoc();

if ($bookings['active_bookings'] > 0) {
    $_SESSION['message'] = "Cannot delete vehicle with active bookings. Please wait until all bookings are completed.";
    $_SESSION['message_type'] = "error";
    header("Location: my_vehicles.php");
    exit;
}

// Delete the vehicle
$delete_sql = "DELETE FROM vehicles WHERE id = ? AND user_id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("ii", $vehicle_id, $user_id);

if ($delete_stmt->execute()) {
    $_SESSION['message'] = "Vehicle deleted successfully!";
    $_SESSION['message_type'] = "success";
} else {
    $_SESSION['message'] = "Failed to delete vehicle. Please try again.";
    $_SESSION['message_type'] = "error";
}

header("Location: my_vehicles.php");
exit;
?>