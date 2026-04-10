<?php
/**
 * public/authentication/login.php  —  User login
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (currentUser()) { header('Location: ' . SITE_URL . '/public/user/index.php'); exit; }

$error    = '';
$redirect = htmlspecialchars($_GET['redirect'] ?? 'user/index.php', ENT_QUOTES);
$showMsg  = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $redirect = $_POST['redirect'] ?? 'user/index.php';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'       => $user['id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'is_elite' => $user['is_elite'],
            ];
            if ($user['role'] === 'admin') {
                header('Location: ' . SITE_URL . '/public/admin/admin.php');
            } else {
                $allowed = ['user/index.php', 'vehicle/fleet.php', 'booking/my-bookings.php', 'vehicle/car.php'];
                $safe = in_array(explode('?', $redirect)[0], $allowed) ? $redirect : 'user/index.php';
                header('Location: ' . SITE_URL . '/public/' . $safe);
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Log In — TD RENTALS';
$assetBase = '../../assets';
$siteBase  = '../..';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="auth-page">
  <div class="auth-card">
    <h1 class="auth-title">LOG IN</h1>

    <?php if ($showMsg === 'login_to_book'): ?>
    <div class="alert alert-error" style="margin-bottom:1rem">
      Please log in or <a href="register.php" style="color:var(--primary)">register</a> to confirm a booking.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
      <input type="hidden" name="redirect"   value="<?= htmlspecialchars($redirect, ENT_QUOTES) ?>" />

      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-input"
               placeholder="you@example.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-input"
               placeholder="••••••••" required />
      </div>
      <div style="margin-top:1.5rem">
        <button type="submit" class="btn btn-primary btn-full btn-lg">LOG IN</button>
      </div>
    </form>

    <p class="auth-footer">Don't have an account? <a href="register.php">Register</a></p>
    <p class="auth-footer"><a href="../user/index.php">← Back to home</a></p>

    <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border);font-size:.75rem;color:var(--fg-muted)">
      <strong>Demo Admin:</strong> admin@tdrentals.com / admin123
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
