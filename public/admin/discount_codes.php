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
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>

/* =========================================================
   DARK MODE DEFAULT
   ========================================================= */

:root{
    --bg: #0f0f10;
    --surface: #181818;
    --surface-2: #111111;

    --border: #2a2a2a;
    --border-soft: #1e1e1e;

    --text: #ffffff;
    --text-soft: #bbbbbb;
    --text-muted: #777777;

    --accent: #C0392B;
    --accent-hover: #C0392B;

    --input-bg: #111111;
    --input-border: #2a2a2a;

    --success: #2ecc71;
    --danger: #C0392B;

    --shadow: none;
}

/* =========================================================
   LIGHT MODE
   ========================================================= */

html[data-theme="light"]{

    --bg: #f5f5f5;
    --surface: #ffffff;
    --surface-2: #fafafa;

    --border: #dddddd;
    --border-soft: #e9e9e9;

    --text: #181818;
    --text-soft: #666666;
    --text-muted: #888888;

    --accent: #C0392B;
    --accent-hover: #C0392B;

    --input-bg: #ffffff;
    --input-border: #d8d8d8;

    --success: #2ecc71;
    --danger: #C0392B;

    --shadow: 0 4px 20px rgba(0,0,0,0.05);
}

/* =========================================================
   GLOBAL
   ========================================================= */

html,
body,
.admin-wrapper,
.main{
    background: var(--bg);
    color: var(--text);
    transition:
        background 0.25s ease,
        color 0.25s ease;
}

/* =========================================================
   PAGE
   ========================================================= */

.page-wrap{
    max-width: 960px;
    margin: 40px auto;
    font-family: 'Inter', sans-serif;
}

h1{
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 32px;
    color: var(--text);
}

h1 span{
    color: var(--accent);
}

/* =========================================================
   ALERTS
   ========================================================= */

.msg{
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-size: 0.9rem;
}

.msg.success{
    background: rgba(46,204,113,0.10);
    border: 1px solid rgba(46,204,113,0.30);
    color: var(--success);
}

.msg.error{
    background: rgba(192, 57, 43, 0.10);
    border: 1px solid rgba(192, 57, 43, 0.30);
    color: var(--danger);
}

/* =========================================================
   SECTION TITLE
   ========================================================= */

.section-title{
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-soft);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 16px;
}

/* =========================================================
   CARDS
   ========================================================= */

.create-card,
.medal-card{
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: var(--shadow);
    transition: all 0.25s ease;
}

.create-card{
    padding: 28px;
    margin-bottom: 40px;
}

.medal-card{
    padding: 24px 20px;
    text-align: center;
}

.create-card h2{
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 24px;
    color: var(--text);
}

/* =========================================================
   FORM
   ========================================================= */

.form-grid{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.form-grid.three{
    grid-template-columns: repeat(3, 1fr);
}

.field label{
    display: block;
    font-size: 0.75rem;
    color: var(--text-soft);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.field label span{
    color: var(--text-muted);
}

input[type="text"],
input[type="number"],
input[type="date"],
select{
    width: 100%;
    padding: 12px 14px;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    color: var(--text);
    border-radius: 10px;
    font-size: 0.92rem;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s ease;
    appearance: none;
}

input::placeholder{
    color: var(--text-muted);
}

input:focus,
select:focus{
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.12);
}

/* =========================================================
   DATE INPUT
   ========================================================= */

input[type="date"]{
    color-scheme: dark;
}

html[data-theme="light"] input[type="date"]{
    color-scheme: light;
}

/* =========================================================
   SELECT
   ========================================================= */

select{
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23888' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 38px;
}

/* =========================================================
   BUTTONS
   ========================================================= */

.btn-create{
    width: 100%;
    padding: 14px;
    background: var(--accent);
    border: 1px solid var(--accent);
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    border-radius: 10px;
    cursor: pointer;
    margin-top: 8px;
    transition: all 0.25s ease;
}

.btn-create:hover{
    background: var(--accent-hover);
    border-color: var(--accent-hover);
    transform: translateY(-1px);
}

/* =========================================================
   CODE LIST
   ========================================================= */

.code-row{
    display: flex;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid var(--border-soft);
    gap: 16px;
}

.code-row:first-child{
    border-top: 1px solid var(--border-soft);
}

.code-badge{
    background: var(--accent);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 8px;
    min-width: 90px;
    text-align: center;
}

.code-badge.inactive{
    background: #555;
    color: #ccc;
}

.code-type{
    flex: 1;
    color: var(--text-soft);
}

.code-uses,
.code-expiry{
    color: var(--text-muted);
}

.code-status{
    font-size: 0.75rem;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 20px;
}

.code-status.active{
    background: rgba(46,204,113,0.15);
    color: var(--success);
}

.code-status.inactive{
    background: rgba(150,150,150,0.15);
    color: var(--text-muted);
}

/* =========================================================
   SMALL BUTTONS
   ========================================================= */

.code-actions{
    display: flex;
    gap: 8px;
}

.btn-sm{
    padding: 6px 12px;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.2s ease;
}

.btn-toggle{
    background: var(--surface-2);
    color: var(--text-soft);
    border: 1px solid var(--border);
}

.btn-toggle:hover{
    border-color: var(--accent);
    color: var(--text);
}

.btn-del{
    background: transparent;
    color: var(--danger);
    border: 1px solid rgba(192, 57, 43, 0.30);
}

.btn-del:hover{
    background: rgba(192, 57, 43, 0.08);
}

/* =========================================================
   MEDALS
   ========================================================= */

.medal-grid{
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}

.medal-icon{
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.medal-name{
    font-size: 1rem;
    font-weight: 700;
}

.medal-sub{
    font-size: 0.8rem;
    color: var(--text-soft);
}

.medal-discount{
    font-size: 0.85rem;
    font-weight: 600;
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px){

    .form-grid,
    .form-grid.three{
        grid-template-columns: 1fr;
    }

    .code-row{
        flex-wrap: wrap;
    }

    .medal-grid{
        grid-template-columns: 1fr;
    }
}

</style>
<div class="admin-wrapper">
  <div class="main">
    <div class="page-wrap">

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
                    <span style="font-size: 0.65rem; color: #C0392B; border: 1px solid #C0392B; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 8px;">PERSONAL</span>
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

    </div> <!-- end page-wrap -->
  </div> <!-- end main -->
</div> <!-- end admin-wrapper -->

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
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
