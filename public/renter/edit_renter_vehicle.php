<?php
/**
 * Edit Renter Vehicle Script
 * 
 * This page handles updating the details of an existing vehicle listing.
 * It verifies ownership, restricts editing of already approved vehicles, 
 * handles image uploads, and updates the database record.
 */
session_start();
require_once '../../config/db.php';

// Check if user is logged in and not admin (only standard renters can edit their vehicles)
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

// Fetch vehicle details and verify ownership
$sql = "SELECT * FROM vehicles WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $vehicle_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Vehicle not found or you don't have permission to edit it";
    $_SESSION['message_type'] = "error";
    header("Location: my_vehicles.php");
    exit;
}

$vehicle = $result->fetch_assoc();

// Check if vehicle is approved - if yes, redirect back
if ($vehicle['status'] === 'approved') {
    $_SESSION['message'] = "Approved vehicles cannot be edited. Please contact admin for changes.";
    $_SESSION['message_type'] = "error";
    header("Location: my_vehicles.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $model = $_POST['model'];
    $license_type = $_POST['license_type'];
    $transmission = $_POST['transmission'];
    $fuel_type = $_POST['fuel_type'];
    $price_per_day = $_POST['price_per_day'];
    $color = $_POST['color'];
    $top_speed = $_POST['top_speed'];
    $fuel_capacity = $_POST['fuel_capacity'];
    
    // If the vehicle was rejected, change status to pending for re-review
    $new_status = ($vehicle['status'] === 'rejected') ? 'pending' : $vehicle['status'];
    
    // Handle image upload if a new image is provided
    $image_path = $vehicle['image_path']; // Keep existing image by default
    
    if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/vehicles/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['vehicle_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($vehicle['image_path']) && file_exists('../../' . $vehicle['image_path'])) {
                    unlink('../../' . $vehicle['image_path']);
                }
                $image_path = 'uploads/vehicles/' . $new_filename;
            }
        }
    }
    
    // SQL with all fields including color, license_type, and image_path
    $update_sql = "UPDATE vehicles SET 
                    model = ?, 
                    license_type = ?,
                    price_per_day = ?, 
                    transmission = ?, 
                    fuel_type = ?, 
                    top_speed = ?, 
                    fuel_capacity = ?,
                    color = ?,
                    image_path = ?,
                    status = ?
                   WHERE id = ? AND user_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    
    /**
     * Types breakdown:
     * s - model (string)
     * s - license_type (string)
     * d - price_per_day (decimal/double)
     * s - transmission (string)
     * s - fuel_type (string)
     * i - top_speed (int)
     * i - fuel_capacity (int)
     * s - color (string)
     * s - image_path (string)
     * s - status (string)
     * i - vehicle_id (int)
     * i - user_id (int)
     */
    $update_stmt->bind_param("ssdssiisssii", 
        $model, 
        $license_type,
        $price_per_day, 
        $transmission, 
        $fuel_type, 
        $top_speed, 
        $fuel_capacity,
        $color,
        $image_path,
        $new_status, 
        $vehicle_id, 
        $user_id
    );
    
    if ($update_stmt->execute()) {
        $_SESSION['message'] = "Vehicle updated successfully!";
        $_SESSION['message_type'] = "success";
        header("Location: my_vehicles.php");
        exit;
    } else {
        $error = "Failed to update vehicle: " . $conn->error;
    }
}

require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/edit_renter_vehicle.css">

<main class="edit-vehicle">
    <h1>Edit Vehicle Listing</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" action="" class="car-form" enctype="multipart/form-data">
        <!-- Vehicle Identity Section -->
        <div class="form-section">
            <h2>Vehicle Identity</h2>
            
            <div class="form-group">
                <label for="model">Vehicle Model <span class="required">*</span></label>
                <input type="text" id="model" name="model" value="<?= htmlspecialchars($vehicle['model']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="license_type">Vehicle Class <span class="required">*</span></label>
                <select id="license_type" name="license_type" required>
                    <option value="">Select vehicle class</option>
                    <option value="A" <?= ($vehicle['license_type'] === 'A') ? 'selected' : '' ?>>A — Motorcycles &amp; Scooters</option>
                    <option value="B" <?= ($vehicle['license_type'] === 'B') ? 'selected' : '' ?>>B — Cars, Jeeps, Vans</option>
                    <option value="C" <?= ($vehicle['license_type'] === 'C') ? 'selected' : '' ?>>C — Commercial Heavy</option>
                    <option value="D" <?= ($vehicle['license_type'] === 'D') ? 'selected' : '' ?>>D — Public Service</option>
                    <option value="E" <?= ($vehicle['license_type'] === 'E') ? 'selected' : '' ?>>E — Heavy with Trailers</option>
                </select>
            </div>
        </div>
        
        <!-- Specs Section -->
        <div class="form-section">
            <h2>Specifications</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="transmission">Transmission <span class="required">*</span></label>
                    <select id="transmission" name="transmission" required>
                        <option value="Manual" <?= $vehicle['transmission'] === 'Manual' ? 'selected' : '' ?>>Manual</option>
                        <option value="Automatic" <?= $vehicle['transmission'] === 'Automatic' ? 'selected' : '' ?>>Automatic</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fuel_type">Fuel Type <span class="required">*</span></label>
                    <select id="fuel_type" name="fuel_type" required>
                        <option value="Petrol" <?= $vehicle['fuel_type'] === 'Petrol' ? 'selected' : '' ?>>Petrol</option>
                        <option value="Diesel" <?= $vehicle['fuel_type'] === 'Diesel' ? 'selected' : '' ?>>Diesel</option>
                        <option value="Electric" <?= $vehicle['fuel_type'] === 'Electric' ? 'selected' : '' ?>>Electric</option>
                        <option value="Hybrid" <?= $vehicle['fuel_type'] === 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                        <option value="CNG" <?= $vehicle['fuel_type'] === 'CNG' ? 'selected' : '' ?>>CNG</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="top_speed">Top Speed (km/h)</label>
                    <input type="number" id="top_speed" name="top_speed" value="<?= $vehicle['top_speed'] ?>" min="0">
                </div>
                
                <div class="form-group">
                    <label for="fuel_capacity">Fuel Capacity (Liters)</label>
                    <input type="number" id="fuel_capacity" name="fuel_capacity" value="<?= $vehicle['fuel_capacity'] ?>" min="0">
                </div>
            </div>
            
            <div class="form-group">
                <label for="color">Vehicle Color</label>
                <div class="color-input-group">
                    <input type="color" id="color" name="color" value="<?= htmlspecialchars($vehicle['color'] ?? '#e03030') ?>">
                    <span class="color-value"><?= htmlspecialchars($vehicle['color'] ?? '#e03030') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Pricing Section -->
        <div class="form-section">
            <h2>Pricing</h2>
            
            <div class="form-group">
                <label for="price_per_day">Price per Day (Rs.) <span class="required">*</span></label>
                <input type="number" id="price_per_day" name="price_per_day" step="0.01" value="<?= $vehicle['price_per_day'] ?>" required>
            </div>
        </div>
        
        <!-- Photos Section -->
        <div class="form-section">
            <h2>Vehicle Photo</h2>
            
            <div class="form-group">
                <label>Current Image</label>
                <?php if (!empty($vehicle['image_path'])): ?>
                    <div class="current-image">
                        <img src="../../<?= htmlspecialchars($vehicle['image_path']) ?>" alt="Current vehicle image" style="max-width: 200px; border-radius: 8px;">
                    </div>
                <?php endif; ?>
                
                <label for="vehicle_image" class="upload-label">Upload New Image (Optional)</label>
                <input type="file" id="vehicle_image" name="vehicle_image" accept="image/jpeg,image/png,image/webp">
                <small class="form-hint">Leave empty to keep current image. Max 8MB. JPG, PNG, or WEBP only.</small>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Vehicle</button>
            <a href="my_vehicles.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</main>


<?php require_once '../../includes/footer.php'; ?>