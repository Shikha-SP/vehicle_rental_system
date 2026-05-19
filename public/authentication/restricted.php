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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --warning-color: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.08);
            --warning-border: rgba(245, 158, 11, 0.3);
        }
        html[data-theme="light"] {
            --warning-color: #b45309;
            --warning-bg: rgba(180, 83, 9, 0.06);
            --warning-border: rgba(180, 83, 9, 0.25);
        }
        
        /* Widescreen Split Panel Layout */
        @media (min-width: 992px) {
            .restricted-split-container {
                display: grid !important;
                grid-template-columns: 1.15fr 0.85fr !important;
                gap: 70px !important;
                max-width: 1100px !important;
                width: 100% !important;
                margin: 0 auto !important;
                align-items: center !important;
                padding: 40px 60px !important;
            }
            .restricted-showcase {
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                text-align: left !important;
                border-right: 1px solid var(--border, rgba(255, 255, 255, 0.1)) !important;
                padding-right: 70px !important;
            }
        }
        @media (max-width: 991px) {
            .restricted-showcase {
                display: none !important; /* Gracefully hide left visual panel on mobile/tablet */
            }
            .login-card {
                margin: 40px auto;
            }
        }
        
        /* Left Brand/Policy Showcase Styling */
        .restricted-showcase {
            color: var(--text, #fff);
            animation: cardIn 0.5s 0.1s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .restricted-showcase .badge {
            display: inline-block;
            background: var(--accent-dim, rgba(192, 57, 43, 0.1));
            color: var(--accent, #C0392B);
            padding: 8px 18px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-radius: 50px;
            border: 1px solid var(--accent, #C0392B);
            margin-bottom: 24px;
        }
        .restricted-showcase h2 {
            font-size: clamp(2.2rem, 3.5vw, 2.8rem);
            font-family: var(--font-head, 'Inter', sans-serif);
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.02em;
            margin-bottom: 20px;
        }
        .restricted-showcase h2 .highlight {
            color: var(--accent, #C0392B);
        }
        .restricted-showcase .showcase-sub {
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--text-muted, #8a8a8e);
            margin-bottom: 40px;
        }
        .policy-features {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }
        .feature-item {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .feature-icon {
            background: var(--bg-input, rgba(255,255,255,0.05));
            border: 1px solid var(--border, rgba(255,255,255,0.1));
            color: var(--accent, #C0392B);
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .feature-text h4 {
            font-family: var(--font-head, 'Inter', sans-serif);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text, #fff);
        }
        .feature-text p {
            font-size: 0.95rem;
            color: var(--text-muted, #8a8a8e);
            line-height: 1.5;
        }

        /* Right Card Container Overrides */
        .login-card {
            max-width: 520px !important;
            width: 100%;
        }
        .login-card__rule {
            margin-bottom: 28px !important;
        }
        .login-card__heading {
            margin-bottom: 24px !important;
        }
        .login-card__heading span {
            font-size: clamp(34px, 6vw, 44px) !important;
            line-height: 1.1 !important;
        }
        .message-box {
            background: var(--accent-dim, rgba(192, 57, 43, 0.08));
            border: 1px solid var(--accent, #C0392B);
            padding: 2.2rem 1.75rem;
            border-radius: 12px;
            margin-bottom: 2.5rem;
            color: var(--accent, #C0392B);
            font-size: 1.05rem;
            line-height: 1.6;
            text-align: center;
        }
        .days-left {
            font-size: 2.4rem;
            font-family: var(--font-head, 'Inter', sans-serif);
            font-weight: 900;
            color: var(--text, #fff);
            margin: 1.2rem 0;
            text-align: center;
            letter-spacing: -0.02em;
            text-transform: uppercase;
        }
        /* Form inputs spacing */
        .login-form__group {
            margin-bottom: 24px !important;
        }
        .login-form__label {
            margin-bottom: 12px !important;
            font-size: 11.5px !important;
            letter-spacing: 0.08em !important;
        }
        .form-group textarea {
            width: 100%;
            background: var(--bg-input, rgba(255, 255, 255, 0.05));
            border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
            border-radius: var(--radius, 8px);
            color: var(--text, #fff);
            font-family: inherit;
            font-size: 0.95rem;
            padding: 14px 18px;
            min-height: 120px;
            resize: vertical;
            transition: border-color 0.3s, box-shadow 0.3s;
            line-height: 1.5;
        }
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent, #C0392B);
            box-shadow: 0 0 0 3px var(--accent-dim, rgba(192, 57, 43, 0.15));
        }
        .login-form__submit {
            margin-top: 1.8rem !important;
            padding: 16px 24px !important;
        }
    </style>
</head>
<body>

<div class="login-page">
    <nav class="login-nav">
        <a href="../../public/landing_page.php" class="login-nav__logo">TD Rentals</a>
        <div style="display: flex; align-items: center; gap: 20px;">
            <button id="themeToggleBtn" class="login-theme-toggle" aria-label="Toggle Theme">
                <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
        </div>
    </nav>

    <main class="login-main restricted-split-container">
        <!-- Left Side: Interactive Security/Policy Showcase -->
        <div class="restricted-showcase">
            <div class="showcase-content">
                <div class="badge">🛡️ TD Security Protocol</div>
                <h2>Keeping our community <span class="highlight">safe and trusted</span>.</h2>
                <p class="showcase-sub">To maintain high-quality standards and protect both renters and owners, we actively enforce safety protocols. If your account is restricted, our support team is ready to review your appeal.</p>
                
                <div class="policy-features">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa-solid fa-scale-balanced"></i></div>
                        <div class="feature-text">
                            <h4>Fair & Transparent Appeal</h4>
                            <p>Every submission is reviewed by a human moderator within 24 hours.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="feature-text">
                            <h4>Enhanced Verification</h4>
                            <p>We ensure absolute compliance with regional motor vehicle laws.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa-solid fa-circle-info"></i></div>
                        <div class="feature-text">
                            <h4>Need Immediate Help?</h4>
                            <p>You can contact our 24/7 hotline at support@tdrentals.com.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-card">
            <div class="login-card__rule"></div>
            
            <?php if ($user['status'] === 'banned'): ?>
                <div class="login-card__heading">
                    <span style="color: var(--accent);">Account</span>
                    <span style="color: var(--accent);">Banned.</span>
                </div>
                <div class="message-box">
                    <p style="margin-bottom: 12px; font-weight: 500;">You were found violating our policies. Your account has been permanently banned and will be completely deleted in:</p>
                    <div class="days-left"><?= $time_remaining ?></div>
                    <p style="margin-top: 14px; font-size: 0.9rem; opacity: 0.85;">If you believe this is a mistake, please contact the admin immediately.</p>
                </div>
            <?php elseif ($user['status'] === 'timeout'): ?>
                <div class="login-card__heading">
                    <span style="color: var(--warning-color);">Account</span>
                    <span style="color: var(--warning-color);">Timeout.</span>
                </div>
                <div class="message-box" style="background: var(--warning-bg); border-color: var(--warning-border); color: var(--warning-color);">
                    <p style="margin-bottom: 12px; font-weight: 500;">Your account is temporarily timed out for policy violations.</p>
                    <div class="days-left"><?= $time_remaining ?></div>
                    <p style="margin-top: 14px; font-size: 0.9rem; opacity: 0.85;">Wait until the timeout expires to regain access, or contact the admin if you think this is a mistake.</p>
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

// Theme Toggle Logic
const themeBtn = document.getElementById('themeToggleBtn');
if (themeBtn) {
    themeBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        
        // Store in localStorage and Cookie
        localStorage.setItem('td-theme', next);
        document.cookie = "theme=" + next + ";path=/;max-age=" + (365*24*60*60);
    });
}
</script>

<script src="../../assets/js/loading.js?v=<?= time() ?>"></script>
</body>
</html>
