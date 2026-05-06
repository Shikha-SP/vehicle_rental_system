<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

if (!isset($_SESSION['restricted_user_id'])) {
    redirect('login.php');
}

$user_id = $_SESSION['restricted_user_id'];

// Fetch user status
$stmt = $conn->prepare("SELECT first_name, status, ban_expires_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    session_destroy();
    redirect('login.php');
}

$user = $res->fetch_assoc();

if ($user['status'] === 'active') {
    // Re-login them
    $_SESSION['user_id'] = $user_id;
    unset($_SESSION['restricted_user_id']);
    redirect('../user/home_page.php');
}

$now = new DateTime();
$expires = new DateTime($user['ban_expires_at']);
$diff = $now->diff($expires);
$days_left = $diff->days;
if ($expires > $now && $diff->h > 0 && $days_left == 0) {
    $days_left = 1; // Round up for display if under 24 hours
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Restricted — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/settings.css">
    <style>
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .restricted-card {
            background: rgba(20,20,20,0.8);
            border: 1px solid rgba(255,255,255,0.08);
            padding: 3rem;
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .restricted-card h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3rem;
            color: var(--red);
            margin-bottom: 1rem;
            letter-spacing: 0.05em;
        }
        .message-box {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.3);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            color: #f87171;
            font-size: 1.1rem;
            line-height: 1.5;
        }
        .days-left {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
            margin: 0.5rem 0;
        }
        .form-group {
            text-align: left;
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #aaa;
            font-size: 0.9rem;
        }
        .form-group textarea {
            width: 100%;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 1rem;
            border-radius: 8px;
            min-height: 120px;
            font-family: inherit;
            resize: vertical;
        }
        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
        }
        .btn-submit {
            background: var(--red);
            color: #fff;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            background: #b02020;
            transform: translateY(-2px);
        }
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #888;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>

<div class="restricted-card">
    <?php if ($user['status'] === 'banned'): ?>
        <h1>ACCOUNT BANNED</h1>
        <div class="message-box">
            You were found violating our policies. Your account has been permanently banned and will be completely deleted in:
            <div class="days-left"><?= $days_left ?> Days</div>
            If you believe this is a mistake, please contact the admin immediately.
        </div>
    <?php elseif ($user['status'] === 'timeout'): ?>
        <h1 style="color: #f59e0b;">ACCOUNT TIMEOUT</h1>
        <div class="message-box" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); color: #fbbf24;">
            Your account is temporarily timed out for policy violations.
            <div class="days-left"><?= $days_left ?> Days</div>
            Wait until the timeout expires to regain access, or contact the admin if you think this is a mistake.
        </div>
    <?php endif; ?>

    <form action="../api/submit_inquiry.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <div class="form-group">
            <label for="message">Message Admin (Appeal)</label>
            <textarea name="message" id="message" placeholder="Explain your situation..." required></textarea>
        </div>
        <button type="submit" class="btn-submit">Submit Appeal</button>
    </form>
    
    <a href="logout.php" class="back-link">Return to Login</a>
</div>

</body>
</html>
