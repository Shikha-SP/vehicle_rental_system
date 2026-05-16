<?php
/**
 * User Settings Page
 * 
 * This page allows users to view and manage their account details, 
 * including changing their password, updating their name, and deleting their account.
 */
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing_page.php");
    exit;
}

// Get the user ID from the active session
$user_id = $_SESSION['user_id'];

// Include database connection to fetch user information
require_once '../../config/db.php';

// Fetch current user data securely using a prepared statement to prevent SQL injection
$query = "SELECT id, first_name, last_name, email, created_at FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Terminate execution if the user record is not found in the database
if (!$user) {
    die("User not found.");
}

// Construct the user's full name for display purposes
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$display_name = $_SESSION['username'] ?? $full_name;

// Determine which settings section is currently active, defaulting to 'overview'
$active_section = isset($_GET['section']) ? $_GET['section'] : 'overview';

// Fetch user notification preferences
$notif_stmt = $conn->prepare("SELECT enabled FROM notification_preference WHERE user_id = ?");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$notif_data = $notif_result->fetch_assoc();
// Default to true (1) if no record exists yet
$notifications_enabled = $notif_data ? (bool) $notif_data['enabled'] : true;

// Include the common site header markup
require_once '../../includes/header.php';
?>

<!-- Page specific CSS -->
<link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/style.css">
<link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/settings.css">

<!-- Wrap everything in main-content to avoid header overlap -->
<div class="settings-page-main-content">
    <button id="sidebarToggle" class="settings-sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="settings-page-dashboard">

        <!-- Sidebar Navigation -->
        <aside class="settings-page-sidebar">
            <div class="settings-sidebar-brand">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </div>

            <nav class="settings-page-nav">
                <div class="settings-nav-group">
                    <div class="settings-nav-label">General</div>
                    <a href="settings.php?section=overview"
                        class="settings-page-nav-item <?= $active_section === 'overview' ? 'active' : '' ?>">
                        <i class="fas fa-user-circle"></i>
                        <span>Overview</span>
                    </a>
                    <a href="settings.php?section=name"
                        class="settings-page-nav-item <?= $active_section === 'name' ? 'active' : '' ?>">
                        <i class="fas fa-id-card"></i>
                        <span>Change Name</span>
                    </a>
                </div>

                <div class="settings-nav-group">
                    <div class="settings-nav-label">Security</div>
                    <a href="settings.php?section=password"
                        class="settings-page-nav-item <?= $active_section === 'password' ? 'active' : '' ?>">
                        <i class="fas fa-shield-alt"></i>
                        <span>Password & Security</span>
                    </a>
                    <?php if (empty($_SESSION['is_admin'])): ?>
                        <a href="settings.php?section=delete"
                            class="settings-page-nav-item <?= $active_section === 'delete' ? 'active' : '' ?>">
                            <i class="fas fa-user-slash"></i>
                            <span>Danger Zone</span>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>

        </aside>

        <!-- Main Content Area -->
        <main class="settings-page-content-area">
            <div class="settings-page-content-header">
                <h1><?= ucfirst($active_section) ?></h1>
            </div>
            <div class="settings-page-content">

                <?php if ($active_section === 'password'): ?>
                    <!-- Change Password Form -->
                    <div class="settings-page-card">
                        <h2><i class="fas fa-key"></i> Change Password</h2>
                        <div class="settings-page-card__sub">Update your account password</div>

                        <form id="changePasswordForm">
                            <div class="settings-page-form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password"
                                    placeholder="Enter your current password">
                            </div>

                            <div class="settings-page-form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password"
                                    placeholder="Enter new password">
                                <small>Must be at least 8 characters with uppercase, lowercase, number, and special character</small>
                            </div>

                            <div class="settings-page-form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                    placeholder="Confirm new password">
                            </div>

                            <div class="settings-page-form-actions">
                                <button type="submit" class="settings-page-btn-primary">Update Password</button>
                                <a href="settings.php?section=overview" class="settings-page-btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>

                <?php elseif ($active_section === 'name'): ?>
                    <!-- Change Name Form -->
                    <div class="settings-page-card">
                        <h2><i class="fas fa-user-edit"></i> Change Name</h2>
                        <div class="settings-page-card__sub">Update your first and last name</div>

                        <form id="changeNameForm">
                            <div class="settings-page-form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name"
                                    value="<?= htmlspecialchars($user['first_name']) ?>"
                                    placeholder="Enter your first name">
                                <small>2-50 characters</small>
                            </div>

                            <div class="settings-page-form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name"
                                    value="<?= htmlspecialchars($user['last_name']) ?>" placeholder="Enter your last name">
                                <small>2-50 characters</small>
                            </div>

                            <div class="settings-page-form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password"
                                    placeholder="Enter your current password for verification">
                                <small>For security verification</small>
                            </div>

                            <div class="settings-page-form-actions">
                                <button type="submit" class="settings-page-btn-primary">Update Name</button>
                                <a href="settings.php?section=overview" class="settings-page-btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>

                <?php elseif ($active_section === 'delete' && empty($_SESSION['is_admin'])): ?>
                    <!-- Delete Account Form -->
                    <div class="settings-page-card">
                        <h2><i class="fas fa-trash-alt"></i> Delete Account</h2>
                        <div class="settings-page-card__sub">Permanently remove your account</div>

                        <div class="settings-delete-grid">
                            <div class="settings-delete-warning-box">
                                <div class="warning-header">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <h3>CRITICAL WARNING</h3>
                                </div>
                                <p>Deleting your account is permanent. This action will immediately and irreversibly remove:
                                </p>
                                <ul class="delete-impact-list">
                                    <li><i class="fas fa-check"></i> Profile info (name, email, etc.)</li>
                                    <li><i class="fas fa-check"></i> All vehicle bookings & history</li>
                                    <li><i class="fas fa-check"></i> Rental listings & analytics</li>
                                    <li><i class="fas fa-check"></i> Wishlist & saved preferences</li>
                                </ul>
                            </div>

                            <div class="settings-delete-form-box">
                                <form id="deleteAccountForm">
                                    <div class="settings-page-form-group">
                                        <label for="delete_password">Account Password *</label>
                                        <input type="password" id="delete_password" name="delete_password"
                                            placeholder="Enter password to verify identity">
                                    </div>

                                    <div class="settings-page-form-group">
                                        <label for="confirm_text">Final Confirmation *</label>
                                        <p class="confirm-instruction">Type <code
                                                class="settings-page-code">DELETE MY ACCOUNT</code> below</p>
                                        <input type="text" id="confirm_text" name="confirm_text"
                                            placeholder="Type: DELETE MY ACCOUNT">
                                    </div>

                                    <div class="settings-page-form-actions">
                                        <button type="submit" class="settings-page-btn-danger">I understand, delete my
                                            account</button>
                                        <a href="settings.php?section=overview" class="settings-page-btn-secondary">Go
                                            Back</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                <?php else: ?>
                    <!-- Overview / Default Settings View -->
                    <div class="settings-page-card">
                        <div class="settings-profile-hero">
                            <div class="settings-profile-left">
                                <div class="settings-profile-avatar">
                                    <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                </div>
                                <div class="settings-profile-main">
                                    <h3><?= htmlspecialchars($full_name) ?></h3>
                                    <p class="settings-profile-email"><?= htmlspecialchars($user['email']) ?></p>
                                </div>
                            </div>
                            <div class="settings-profile-right">
                                <div class="settings-meta-item">
                                    <span class="meta-label">Account Status</span>
                                    <span class="settings-profile-tag">Active Member</span>
                                </div>
                                <div class="settings-meta-item">
                                    <span class="meta-label">Member Since</span>
                                    <span class="meta-value"><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="settings-overview-section">
                            <h3>Quick Actions</h3>
                            <div class="settings-overview-actions">
                                <a href="settings.php?section=password" class="settings-page-btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </a>
                                <a href="settings.php?section=name" class="settings-page-btn-secondary">
                                    <i class="fas fa-user-edit"></i> Change Name
                                </a>
                            </div>
                        </div>
                        <div class="settings-page-notification-toggle">
                            <h3 style="font-family: var(--font-display); font-size: 18px; margin-bottom: 16px"> Notification
                                Toggle </h3>
                            <label class="switch">
                                <input type="checkbox" id="notif-toggle" <?= $notifications_enabled ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <?php if (empty($_SESSION['is_admin'])): ?>
                            <div class="settings-overview-section danger-zone">
                                <h3 class="danger-title">Danger Zone</h3>
                                <a href="settings.php?section=delete" class="settings-page-btn-danger">
                                    <i class="fas fa-trash-alt"></i> Delete Account
                                </a>
                                <p class="danger-help">
                                    Once you delete your account, there is no going back. Please be certain.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- At the end of settings.php, after footer include -->
<script src="/vehicle_rental_collab_project/assets/js/settings.js"></script>
<script>
    // Notification toggle logic
    const notifToggle = document.getElementById('notif-toggle');
    if (notifToggle) {
        notifToggle.addEventListener('change', async function () {
            const isEnabled = this.checked;

            try {
                const response = await fetch('/vehicle_rental_collab_project/ajax/update_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        enabled: isEnabled
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'error');
                    this.checked = !isEnabled; // Revert visually if failed
                }
            } catch (error) {
                showNotification('An error occurred while updating preferences.', 'error');
                this.checked = !isEnabled; // Revert visually if failed
            }
        });
    }
</script>
<?php require_once '../../includes/footer.php'; ?>