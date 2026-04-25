<?php
/**
 * public/authentication/register.php  —  New user registration
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (currentUser()) { header('Location: ' . SITE_URL . '/public/user/index.php'); exit; }

$errors = [];
$values = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $values   = compact('name', 'email');

    if (!$name)                                        $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Valid email is required.';
    if (strlen($password) < 8)                         $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                        $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $dup = db()->prepare("SELECT id FROM users WHERE email = ?");
        $dup->execute([$email]);
        if ($dup->fetch()) {
            $errors[] = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = db()->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $ins->execute([$name, $email, $hash]);
            $uid = db()->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => $uid, 'name' => $name, 'email' => $email, 'role' => 'member', 'is_elite' => 0];
            header('Location: ' . SITE_URL . '/public/user/index.php');
            exit;
        }
    }
}

$pageTitle = 'Register — TD RENTALS';
$assetBase = '../../assets';
$siteBase  = '../..';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="auth-page">
  <div class="auth-card">
    <h1 class="auth-title">REGISTER</h1>

    <?php if ($errors): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-input" placeholder="John Doe"
               value="<?= htmlspecialchars($values['name']) ?>" required />
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" placeholder="you@example.com"
               value="<?= htmlspecialchars($values['email']) ?>" required />
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input" placeholder="Min. 8 characters" required />
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm" class="form-input" placeholder="Repeat password" required />
      </div>
      <div style="margin-top:1.5rem">
        <button type="submit" class="btn btn-primary btn-full btn-lg">CREATE ACCOUNT</button>
      </div>
    </form>

    <p class="auth-footer">Already have an account? <a href="login.php">Log in</a></p>
    <p class="auth-footer"><a href="../user/index.php">← Back to home</a></p>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
