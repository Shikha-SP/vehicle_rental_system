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

// Include the common site header markup
require_once '../../includes/header.php';
?>

<!-- Page specific CSS -->
<link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/settings.css">

<!-- Wrap everything in main-content to avoid header overlap -->
<div class="settings-page-main-content">
    <div class="settings-page-container">
        <div class="settings-page-header">
            <div class="settings-page-header__rule"></div>
            <h1>Account <span>Settings</span></h1>
            <p>Manage your account security and preferences</p>
        </div>

        <div class="settings-page-grid">
            <!-- Sidebar Navigation -->
            <div class="settings-page-sidebar">
                <div class="settings-page-nav">
                    <a href="settings.php?section=overview" class="settings-page-nav-item <?= $active_section === 'overview' ? 'active' : '' ?>">
                        <i class="fas fa-sliders-h"></i>
                        <span>Overview</span>
                    </a>
                    <a href="settings.php?section=password" class="settings-page-nav-item <?= $active_section === 'password' ? 'active' : '' ?>">
                        <i class="fas fa-key"></i>
                        <span>Change Password</span>
                    </a>
                    <a href="settings.php?section=name" class="settings-page-nav-item <?= $active_section === 'name' ? 'active' : '' ?>">
                        <i class="fas fa-user-edit"></i>
                        <span>Change Name</span>
                    </a>
                    <?php if (empty($_SESSION['is_admin'])): ?>
                    <a href="settings.php?section=delete" class="settings-page-nav-item delete-item <?= $active_section === 'delete' ? 'active' : '' ?>">
                        <i class="fas fa-trash-alt"></i>
                        <span>Delete Account</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="settings-page-content">
                <?php if ($active_section === 'password'): ?>
                    <!-- Change Password Form -->
                    <div class="settings-page-card">
                        <h2><i class="fas fa-key"></i> Change Password</h2>
                        <div class="settings-page-card__sub">Update your account password</div>
                        
                        <form>
                            <div class="settings-page-form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" 
                                       placeholder="Enter your current password">
                            </div>
                            
                            <div class="settings-page-form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" 
                                       placeholder="Enter new password">
                                <small>Must be at least 8 characters with uppercase, lowercase, and number</small>
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
                        
                        <form>
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
                                       value="<?= htmlspecialchars($user['last_name']) ?>"
                                       placeholder="Enter your last name">
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
                        
                        <div class="settings-page-delete-warning">
                            <h3><i class="fas fa-exclamation-triangle"></i> Warning: This action cannot be undone!</h3>
                            <p class="settings-page-warning-text">Deleting your account will permanently remove:</p>
                            <ul>
                                <li>Your profile information (first name, last name, email)</li>
                                <li>All your vehicle bookings</li>
                                <li>Your rental history</li>
                                <li>All associated data</li>
                            </ul>
                        </div>
                        
                        <form>
                            <div class="settings-page-form-group">
                                <label for="delete_password">Enter your password to confirm *</label>
                                <input type="password" id="delete_password" name="delete_password" 
                                       placeholder="Enter your current password">
                            </div>
                            
                            <div class="settings-page-form-group">
                                <label for="confirm_text">Type <code class="settings-page-code">DELETE MY ACCOUNT</code> to confirm *</label>
                                <input type="text" id="confirm_text" name="confirm_text" 
                                       placeholder="Type: DELETE MY ACCOUNT">
                                <small>This confirms you understand this action is permanent.</small>
                            </div>
                            
                            <div class="settings-page-form-actions">
                                <button type="submit" class="settings-page-btn-danger">Permanently Delete Account</button>
                                <a href="settings.php?section=overview" class="settings-page-btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                
                <?php else: ?>
                    <!-- Overview / Default Settings View -->
                    <div class="settings-page-card">
                        <h2><i class="fas fa-sliders-h"></i> Account Overview</h2>
                        <div class="settings-page-card__sub">Your account information and settings</div>
                        
                        <div class="settings-page-user-info">
                            <div class="settings-page-user-info__details">
                                <h3><?= htmlspecialchars($full_name) ?></h3>
                                <p><?= htmlspecialchars($user['email']) ?></p>
                                <p style="font-size: 12px; margin-top: 8px;">Member since: <?= date('F j, Y', strtotime($user['created_at'])) ?></p>
                            </div>
                            <div class="settings-page-user-info__badge">
                                <i class="fas fa-user"></i> Active Account
                            </div>
                        </div>
                        
                        <div style="margin-top: 24px;">
                            <h3 style="font-family: var(--font-display); font-size: 18px; margin-bottom: 16px;">Quick Actions</h3>
                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <a href="settings.php?section=password" class="settings-page-btn-primary" style="text-decoration: none;">
                                    <i class="fas fa-key"></i> Change Password
                                </a>
                                <a href="settings.php?section=name" class="settings-page-btn-secondary" style="text-decoration: none;">
                                    <i class="fas fa-user-edit"></i> Change Name
                                </a>
                            </div>
                        </div>
                        
                        <?php if (empty($_SESSION['is_admin'])): ?>
                        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--clr-border);">
                            <h3 style="font-family: var(--font-display); font-size: 18px; margin-bottom: 16px; color: #ff6b6b;">Danger Zone</h3>
                            <a href="settings.php?section=delete" class="settings-page-btn-danger" style="text-decoration: none; display: inline-block;">
                                <i class="fas fa-trash-alt"></i> Delete Account
                            </a>
                            <p style="font-size: 12px; color: var(--clr-muted); margin-top: 12px;">
                                Once you delete your account, there is no going back. Please be certain.
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- At the end of settings.php, after footer include -->
<script src="/vehicle_rental_collab_project/assets/js/settings.js"></script>
<?php require_once '../../includes/footer.php'; ?>