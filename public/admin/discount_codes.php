<?php
session_start();
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../authentication/login.php');
    exit;
}

require_once '../../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid CSRF token.";
        $message_type = 'error';
    } else {
        if (isset($_POST['create_code'])) {
            $code = strtoupper(trim($_POST['code']));
            $type = ($_POST['type'] === 'flat') ? 'flat' : 'percent';
            $value = floatval($_POST['value']);
            $max_uses = !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null;
            $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

            $discount_percent = ($type === 'percent') ? $value : 0;
            $discount_flat = ($type === 'flat') ? $value : 0;

            if (!empty($code) && $value > 0) {
                $code_esc = $conn->real_escape_string($code);
                $type_esc = $conn->real_escape_string($type);
                $max_uses_sql = ($max_uses !== null) ? intval($max_uses) : 'NULL';
                $expires_sql  = ($expires_at !== null) ? "'" . $conn->real_escape_string($expires_at) . "'" : 'NULL';

                $q = "INSERT INTO discount_codes (code, type, discount_percent, discount_flat, max_uses, expires_at)
                      VALUES ('$code_esc', '$type_esc', $discount_percent, $discount_flat, $max_uses_sql, $expires_sql)";

                if ($conn->query($q)) {
                    $message = "Discount code <strong>$code</strong> created successfully.";
                } else {
                    $message = "Error creating code. The code name might already exist.";
                    $message_type = 'error';
                }
            } else {
                $message = "Please fill in all required fields.";
                $message_type = 'error';
            }
        } elseif (isset($_POST['toggle_id'])) {
            $id = intval($_POST['toggle_id']);
            $new_status = intval($_POST['new_status']);
            $conn->query("UPDATE discount_codes SET is_active = $new_status WHERE id = $id");
            $message = "Code status updated.";
        } elseif (isset($_POST['delete_id'])) {
            $id = intval($_POST['delete_id']);
            $conn->query("DELETE FROM discount_codes WHERE id = $id");
            $message = "Discount code deleted.";
        }
    }
}

$codes = $conn->query("SELECT * FROM discount_codes ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Medal thresholds
$medal_tiers = [
    'bronze' => ['rentals' => 3,  'discount' => 5,  'icon' => '🥉', 'color' => '#cd7f32'],
    'silver' => ['rentals' => 7,  'discount' => 10, 'icon' => '🥈', 'color' => '#aaaaaa'],
    'gold'   => ['rentals' => 15, 'discount' => 20, 'icon' => '🥇', 'color' => '#ffd700'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Codes | TD Rentals Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0e0e0e;
            color: #ffffff;
            min-height: 100vh;
            padding: 40px 24px;
        }

        .page-wrap { max-width: 960px; margin: 0 auto; }

        .back-link {
            display: inline-block;
            color: #666;
            text-decoration: none;
            font-size: 0.85rem;
            margin-bottom: 24px;
        }
        .back-link:hover { color: #fff; }

        h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 32px;
        }
        h1 span { color: #e03030; }

        /* Message */
        .msg {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.9rem;
        }
        .msg.success { background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.3); color: #2ecc71; }
        .msg.error   { background: rgba(224,48,48,0.1);  border: 1px solid rgba(224,48,48,0.3);  color: #e03030; }

        /* Section headers */
        .section-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
        }

        /* Create form card */
        .create-card {
            background: #181818;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 40px;
        }
        .create-card h2 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 24px;
            color: #ddd;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-grid.three { grid-template-columns: 1fr 1fr 1fr; }
        .form-full { grid-column: 1 / -1; }

        .field label {
            display: block;
            font-size: 0.75rem;
            color: #888;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        input[type="text"], input[type="number"], input[type="date"], select {
            width: 100%;
            padding: 12px 14px;
            background: #111;
            border: 1px solid #2a2a2a;
            color: #fff;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
            appearance: none;
        }
        select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23888' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
        input:focus, select:focus { outline: none; border-color: #e03030; }
        input::placeholder { color: #444; }

        .btn-create {
            width: 100%;
            padding: 14px;
            background: #1a1a1a;
            border: 1px solid #333;
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s, border-color 0.2s;
        }
        .btn-create:hover { background: #222; border-color: #555; }

        /* Codes table */
        .codes-section { margin-bottom: 48px; }

        .code-row {
            display: flex;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #1e1e1e;
            gap: 16px;
        }
        .code-row:first-child { border-top: 1px solid #1e1e1e; }

        .code-badge {
            background: #e03030;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 6px;
            letter-spacing: 0.05em;
            white-space: nowrap;
            min-width: 90px;
            text-align: center;
        }
        .code-badge.inactive { background: #333; color: #888; }

        .code-type {
            flex: 1;
            font-size: 0.85rem;
            color: #bbb;
        }

        .code-uses {
            font-size: 0.85rem;
            color: #888;
            white-space: nowrap;
            min-width: 80px;
            text-align: right;
        }

        .code-expiry {
            font-size: 0.8rem;
            color: #666;
            white-space: nowrap;
            min-width: 100px;
            text-align: right;
        }

        .code-status {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }
        .code-status.active   { background: rgba(46,204,113,0.15); color: #2ecc71; }
        .code-status.inactive { background: rgba(150,150,150,0.15); color: #888; }

        .code-actions { display: flex; gap: 8px; }
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .btn-toggle { background: #222; color: #aaa; border-color: #333; }
        .btn-toggle:hover { border-color: #555; color: #fff; }
        .btn-del { background: transparent; color: #e03030; border-color: rgba(224,48,48,0.3); }
        .btn-del:hover { background: rgba(224,48,48,0.1); }

        .empty-codes { color: #555; font-size: 0.9rem; padding: 20px 0; }

        /* Medal section */
        .medal-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        .medal-card {
            background: #181818;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 24px 20px;
            text-align: center;
        }
        .medal-icon { font-size: 2.5rem; margin-bottom: 10px; display: block; }
        .medal-name { font-size: 1rem; font-weight: 700; margin-bottom: 4px; }
        .medal-sub  { font-size: 0.8rem; color: #666; margin-bottom: 6px; }
        .medal-discount { font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="page-wrap">

    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
    <h1>Discount <span>Codes</span></h1>

    <?php if ($message): ?>
        <div class="msg <?= $message_type ?>"><?= $message ?></div>
    <?php endif; ?>

    <!-- Create Form -->
    <div class="create-card">
        <h2>Create new discount code</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-grid">
                <div class="field">
                    <label>Code word</label>
                    <input type="text" name="code" placeholder="E.G. SAVE20" required autocomplete="off">
                </div>
                <div class="field">
                    <label>Type</label>
                    <select name="type" id="code-type" onchange="toggleValueLabel()">
                        <option value="percent">Percentage (% off)</option>
                        <option value="flat">Flat amount (NPR off)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid three">
                <div class="field">
                    <label id="value-label">Value</label>
                    <input type="number" name="value" id="value-input" placeholder="20" min="1" step="0.01" required>
                </div>
                <div class="field">
                    <label>Max uses <span style="color:#555;">(optional)</span></label>
                    <input type="number" name="max_uses" placeholder="100" min="1">
                </div>
                <div class="field">
                    <label>Expiry date <span style="color:#555;">(optional)</span></label>
                    <input type="date" name="expires_at" min="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <button type="submit" name="create_code" class="btn-create">CREATE CODE</button>
        </form>
    </div>

    <!-- Codes List -->
    <div class="codes-section">
        <p class="section-title">All codes in database</p>

        <?php if (empty($codes)): ?>
            <p class="empty-codes">No discount codes yet. Create one above.</p>
        <?php else: ?>
            <?php foreach ($codes as $c):
                $is_active = (bool)$c['is_active'];
                $expired = $c['expires_at'] && $c['expires_at'] < date('Y-m-d');
                $maxed   = $c['max_uses'] && $c['used_count'] >= $c['max_uses'];
                $effectively_active = $is_active && !$expired && !$maxed;

                if ($c['type'] === 'flat') {
                    $type_label = 'NPR ' . number_format($c['discount_flat'], 0) . ' off';
                } else {
                    $type_label = number_format($c['discount_percent'], 0) . '% off';
                }

                $uses_label = $c['max_uses']
                    ? $c['used_count'] . '/' . $c['max_uses']
                    : $c['used_count'] . ' uses';

                $expiry_label = $c['expires_at']
                    ? date('d/m/Y', strtotime($c['expires_at']))
                    : '—';
            ?>
            <div class="code-row">
                <span class="code-badge <?= $effectively_active ? '' : 'inactive' ?>">
                    <?= htmlspecialchars($c['code']) ?>
                </span>
                <span class="code-type"><?= $type_label ?></span>
                <?php if ($c['owner_user_id']): ?>
                    <span style="font-size: 0.65rem; color: #ff6b6b; border: 1px solid #ff6b6b; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 8px;">PERSONAL</span>
                <?php endif; ?>
                <span class="code-uses"><?= $uses_label ?></span>
                <span class="code-expiry"><?= $expiry_label ?></span>
                <span class="code-status <?= $effectively_active ? 'active' : 'inactive' ?>">
                    <?= $effectively_active ? 'active' : ($expired ? 'expired' : ($maxed ? 'maxed' : 'inactive')) ?>
                </span>
                <div class="code-actions">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="toggle_id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="new_status" value="<?= $is_active ? 0 : 1 ?>">
                        <button type="submit" class="btn-sm btn-toggle"><?= $is_active ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this code?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn-sm btn-del">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Medal Tiers Reference -->
    <p class="section-title">Loyalty medal tiers</p>
    <div class="medal-grid">
        <?php foreach ($medal_tiers as $name => $tier): ?>
        <div class="medal-card">
            <span class="medal-icon"><?= $tier['icon'] ?></span>
            <div class="medal-name" style="color: <?= $tier['color'] ?>"><?= ucfirst($name) ?></div>
            <div class="medal-sub"><?= $tier['rentals'] ?> rentals</div>
            <div class="medal-discount" style="color: <?= $tier['color'] ?>"><?= $tier['discount'] ?>% off</div>
        </div>
        <?php endforeach; ?>
    </div>

</div>
<script>
function toggleValueLabel() {
    const type = document.getElementById('code-type').value;
    const label = document.getElementById('value-label');
    const input = document.getElementById('value-input');
    if (type === 'flat') {
        label.textContent = 'Value (NPR)';
        input.placeholder = '500';
    } else {
        label.textContent = 'Value (%)';
        input.placeholder = '20';
    }
}
</script>
</body>
</html>
