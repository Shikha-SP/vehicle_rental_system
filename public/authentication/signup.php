<?php
require_once '../../config/db.php';          // Connect to the database
require_once '../../includes/functions.php'; // Include helper functions
session_start();                           // Start session to manage login state and CSRF tokens

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

// Redirect logged-in users to dashboard (or vehicle list)
if (isLoggedIn()) {
    redirect('dashboard.php'); // Change dashboard.php to your landing page
}

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Security error: Invalid CSRF token.");
    }

    // Sanitize and trim inputs
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $last_name  = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $email      = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $address    = trim(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS));
    $phone      = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_NUMBER_INT));
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm_password'] ?? '');
    $license_no = trim(filter_input(INPUT_POST, 'license_no', FILTER_SANITIZE_SPECIAL_CHARS));
    $license_type = trim(filter_input(INPUT_POST, 'license_type', FILTER_SANITIZE_SPECIAL_CHARS));

    // Validation
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";
    if (empty($license_no)) $errors[] = "License number is required.";
    if (empty($license_type)) $errors[] = "License type is required.";

    // Check if email already exists
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Email is already registered.";
        }
    }

    // Insert user if no errors
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (first_name,last_name,email,address,phone,password,license_no,license_type,is_admin) VALUES (?,?,?,?,?,?,?,?,0)");
        mysqli_stmt_bind_param($stmt, "ssssssss", $first_name, $last_name, $email, $address, $phone, $password_hash, $license_no, $license_type);
        if (mysqli_stmt_execute($stmt)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = mysqli_insert_id($conn);
            $_SESSION['username'] = $first_name;
            $_SESSION['csrf_token'] = generateCsrfToken();
            redirect('dashboard.php'); // redirect after signup
        } else {
            $errors[] = "Failed to create account. Try again.";
        }
    }
}
?>

<!-- HTML Form -->
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>
</head>
<body>
    <h1>Sign Up</h1>
    <?php if (!empty($errors)): ?>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="text" name="first_name" placeholder="First Name" value="<?= e($first_name ?? '') ?>" required><br>
        <input type="text" name="last_name" placeholder="Last Name" value="<?= e($last_name ?? '') ?>" required><br>
        <input type="email" name="email" placeholder="Email" value="<?= e($email ?? '') ?>" required><br>
        <input type="text" name="address" placeholder="Address" value="<?= e($address ?? '') ?>" required><br>
        <input type="text" name="phone" placeholder="Phone" value="<?= e($phone ?? '') ?>" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required><br>
        <input type="text" name="license_no" placeholder="License Number" value="<?= e($license_no ?? '') ?>" required><br>
        <select name="license_type" required>
            <option value="">Select License Type</option>
            <option value="A" <?= (isset($license_type) && $license_type=="A")?"selected":"" ?>>A - Motorcycles, Scooters, Mopeds</option>
            <option value="B" <?= (isset($license_type) && $license_type=="B")?"selected":"" ?>>B - Cars, Jeeps, Vans</option>
            <option value="C" <?= (isset($license_type) && $license_type=="C")?"selected":"" ?>>C - Commercial heavy vehicles</option>
            <option value="D" <?= (isset($license_type) && $license_type=="D")?"selected":"" ?>>D - Public service vehicles</option>
            <option value="E" <?= (isset($license_type) && $license_type=="E")?"selected":"" ?>>E - Heavy vehicles with trailers</option>
        </select><br>
        <button type="submit">Sign Up</button>
    </form>
    <a href="login.php">Already have an account? Login</a>
</body>
</html>