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
$time_remaining = "";

if ($now >= $expires) {
    if ($user['status'] === 'timeout') {
        // Auto-restore timeout users
        $upd = $conn->prepare("UPDATE users SET status = 'active', ban_expires_at = NULL WHERE id = ?");
        $upd->bind_param("i", $user_id);
        $upd->execute();
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $user['first_name'];
        unset($_SESSION['restricted_user_id']);
        redirect('../user/home_page.php');
    } else {
        // Banned user whose 3 days are up -> they get deleted on refresh
        $del = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del->bind_param("i", $user_id);
        $del->execute();
        session_destroy();
        redirect('login.php?deleted=1');
    }
}

$diff = $now->diff($expires);
if ($diff->days > 0) {
    $time_remaining = $diff->days . " Day" . ($diff->days > 1 ? "s " : " ") . $diff->h . " Hr" . ($diff->h != 1 ? "s" : "");
} elseif ($diff->h > 0) {
    $time_remaining = ($diff->h * 60 + $diff->i) . " Min " . $diff->s . " Sec";
} elseif ($diff->i > 0) {
    $time_remaining = $diff->i . " Min " . $diff->s . " Sec";
} else {
    $time_remaining = $diff->s . " Seconds";
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
    <link rel="stylesheet" href="../../assets/css/login.css">
    <style>
        .message-box {
            background: rgba(192, 57, 43, 0.1);
            border: 1px solid rgba(192, 57, 43, 0.3);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            color: #C0392B;
            font-size: 1rem;
            line-height: 1.5;
        .days-left {
            font-size: 2rem;
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            color: #fff;
            margin: 0.5rem 0;
            text-align: center;
            letter-spacing: -0.02em;
        }
        .form-group textarea {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-primary, #fff);
            font-family: inherit;
            font-size: 1rem;
            padding: 0.5rem 0;
            min-height: 80px;
            resize: vertical;
            transition: border-color 0.3s;
        }
        .form-group textarea:focus {
            outline: none;
            border-bottom-color: var(--red, #C0392B);
        }
    </style>
</head>
<body>

<div class="login-page">
    <nav class="login-nav">
        <a href="../../public/landing_page.php" class="login-nav__logo">TD Rentals</a>
    </nav>

    <main class="login-main">
        <div class="login-card">
            <div class="login-card__rule"></div>
            
            <?php if ($user['status'] === 'banned'): ?>
                <div class="login-card__heading">
                    <span style="color: var(--red);">Account</span>
                    <span style="color: var(--red);">Banned.</span>
                </div>
                <div class="message-box">
                    You were found violating our policies. Your account has been permanently banned and will be completely deleted in:
                    <div class="days-left"><?= $time_remaining ?></div>
                    If you believe this is a mistake, please contact the admin immediately.
                </div>
            <?php elseif ($user['status'] === 'timeout'): ?>
                <div class="login-card__heading">
                    <span style="color: #f59e0b;">Account</span>
                    <span style="color: #f59e0b;">Timeout.</span>
                </div>
                <div class="message-box" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); color: #fbbf24;">
                    Your account is temporarily timed out for policy violations.
                    <div class="days-left"><?= $time_remaining ?></div>
                    Wait until the timeout expires to regain access, or contact the admin if you think this is a mistake.
                </div>
            <?php endif; ?>

            <form class="login-form" action="../api/submit_inquiry.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                
                <div class="login-form__group">
                    <label class="login-form__label" for="message">Message Admin (Appeal)</label>
                    <div class="form-group">
                        <textarea name="message" id="message" placeholder="Explain your situation..." required></textarea>
                    </div>
                </div>

                <button class="login-form__submit" type="submit" style="margin-top: 1rem;">
                    Submit Appeal
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </button>

                <p class="login-signup-prompt" style="margin-top: 1.5rem;">
                    <a href="logout.php?confirm=yes">Return to Home</a>
                </p>
            </form>
        </div>
    </main>
</div>

<script>
// Auto-refresh when timer reaches zero
const expiresAt = new Date("<?= $user['ban_expires_at'] ?>").getTime();

function updateCountdown() {
    const now = new Date().getTime();
    const distance = expiresAt - now;

    if (distance <= 0) {
        window.location.reload();
        return;
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    let display = "";
    if (days > 0) {
        display = days + " Day" + (days > 1 ? "s " : " ") + hours + " Hr" + (hours != 1 ? "s" : "");
    } else if (hours > 0) {
        display = (hours * 60 + minutes) + " Min " + seconds + " Sec";
    } else if (minutes > 0) {
        display = minutes + " Min " + seconds + " Sec";
    } else {
        display = seconds + " Seconds";
    }

    document.querySelectorAll('.days-left').forEach(el => {
        el.innerHTML = display;
    });
}

setInterval(updateCountdown, 1000);
updateCountdown();
</script>

</body>
</html>
