<?php
require_once __DIR__ . '/config.php';
requireAdmin();

$user = currentUser();

try {
    db()->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED,
        admin_name VARCHAR(100),
        action VARCHAR(80) NOT NULL,
        target_type VARCHAR(40),
        target_id INT UNSIGNED,
        detail TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

function localAuditLog(string $action, string $targetType = '', int $targetId = 0, string $detail = ''): void {
    $u = currentUser();
    try {
        db()->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, target_type, target_id, detail) VALUES (?,?,?,?,?,?)")
           ->execute([$u['id'] ?? null, $u['name'] ?? 'System', $action, $targetType, $targetId ?: null, $detail ?: null]);
    } catch (Exception $e) {}
}

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $bid    = (int)$_POST['booking_id'];
        $status = $_POST['status'];
        $allowed = ['pending','confirmed','cancelled','completed'];
        if (in_array($status, $allowed)) {
            $old = db()->prepare("SELECT status FROM bookings WHERE id = ?");
            $old->execute([$bid]);
            $oldRow = $old->fetch();
            db()->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$status, $bid]);
            localAuditLog("booking_status_changed", "booking", $bid, "from {$oldRow['status']} to {$status}");
        }
        header('Location: admin.php?page=reservations'); exit;
    }

    if ($action === 'add_vehicle') {
        $name     = trim($_POST['car_name'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $price    = (float)($_POST['price_per_day'] ?? 0);
        $tag      = trim($_POST['tag'] ?? '');
        $desc1    = trim($_POST['description1'] ?? '');
        $desc2    = trim($_POST['description2'] ?? '');
        $topspeed = trim($_POST['top_speed'] ?? '');
        $accel    = trim($_POST['acceleration'] ?? '');
        $power    = trim($_POST['max_power'] ?? '');
        $engine   = trim($_POST['engine'] ?? '');
        $featured = isset($_POST['is_featured']) ? 1 : 0;

        if (!$name || !$price) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Name and price are required.'];
        } else {
            $imageFile = 'hero-porsche.jpg';
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $imageFile = 'car_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/images/' . $imageFile);
                }
            }
            $stmt = db()->prepare("INSERT INTO cars (name,subtitle,price_per_day,tag,image_file,top_speed,acceleration,max_power,engine,description1,description2,available,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)");
            $stmt->execute([$name,$subtitle,$price,$tag,$imageFile,$topspeed,$accel,$power,$engine,$desc1,$desc2,$featured]);
            $newId = (int)db()->lastInsertId();
            localAuditLog("vehicle_added", "car", $newId, "Added: {$name}");
            $_SESSION['flash'] = ['type'=>'success','msg'=>"Vehicle '{$name}' added successfully."];
        }
        header('Location: admin.php?page=fleet'); exit;
    }

    if ($action === 'edit_vehicle') {
        $cid      = (int)$_POST['car_id'];
        $name     = trim($_POST['car_name'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $price    = (float)($_POST['price_per_day'] ?? 0);
        $tag      = trim($_POST['tag'] ?? '');
        $desc1    = trim($_POST['description1'] ?? '');
        $desc2    = trim($_POST['description2'] ?? '');
        $topspeed = trim($_POST['top_speed'] ?? '');
        $accel    = trim($_POST['acceleration'] ?? '');
        $power    = trim($_POST['max_power'] ?? '');
        $engine   = trim($_POST['engine'] ?? '');
        $featured = isset($_POST['is_featured']) ? 1 : 0;
        $avail    = isset($_POST['available']) ? 1 : 0;

        if (!$name || !$price) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Name and price are required.'];
        } else {
            $imgSql = '';
            $params = [$name,$subtitle,$price,$tag,$desc1,$desc2,$topspeed,$accel,$power,$engine,$featured,$avail];
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $imgFile = 'car_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/images/' . $imgFile);
                    $imgSql = ', image_file = ?';
                    $params[] = $imgFile;
                }
            }
            $params[] = $cid;
            db()->prepare("UPDATE cars SET name=?,subtitle=?,price_per_day=?,tag=?,description1=?,description2=?,top_speed=?,acceleration=?,max_power=?,engine=?,is_featured=?,available=? {$imgSql} WHERE id=?")->execute($params);
            localAuditLog("vehicle_edited", "car", $cid, "Edited: {$name}");
            $_SESSION['flash'] = ['type'=>'success','msg'=>"Vehicle '{$name}' updated."];
        }
        header('Location: admin.php?page=fleet'); exit;
    }

    if ($action === 'delete_vehicle') {
        $cid = (int)$_POST['car_id'];
        $active = db()->prepare("SELECT COUNT(*) FROM bookings WHERE car_id = ? AND status IN ('pending','confirmed')");
        $active->execute([$cid]);
        if ($active->fetchColumn() > 0) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Cannot delete: vehicle has active bookings. Cancel them first.'];
        } else {
            $carRow = db()->prepare("SELECT name FROM cars WHERE id = ?");
            $carRow->execute([$cid]);
            $carName = ($carRow->fetch())['name'] ?? 'unknown';
            db()->prepare("DELETE FROM cars WHERE id = ?")->execute([$cid]);
            localAuditLog("vehicle_deleted", "car", $cid, "Deleted: {$carName}");
            $_SESSION['flash'] = ['type'=>'success','msg'=>"Vehicle deleted."];
        }
        header('Location: admin.php?page=fleet'); exit;
    }
}

if (empty($flash['msg']) && !empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$page = $_GET['page'] ?? 'dashboard';

// Data queries
$totalBookings = db()->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalRevenue  = db()->query("SELECT COALESCE(SUM(grand_total),0) FROM bookings WHERE status != 'cancelled'")->fetchColumn();
$totalUsers    = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pendingCount  = db()->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$confirmedCount= db()->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn();
$totalCars     = db()->query("SELECT COUNT(*) FROM cars")->fetchColumn();
$availCars     = db()->query("SELECT COUNT(*) FROM cars WHERE available = 1")->fetchColumn();

$bookings = db()->query("
    SELECT b.*, c.name AS car_name, c.image_file, COALESCE(u.name, b.guest_name) AS customer, COALESCE(u.email, b.guest_email) AS customer_email
    FROM bookings b JOIN cars c ON c.id = b.car_id LEFT JOIN users u ON u.id = b.user_id
    ORDER BY b.created_at DESC LIMIT 50
")->fetchAll();

$cars = db()->query("SELECT * FROM cars ORDER BY id ASC")->fetchAll();

$activePerCar = [];
foreach (db()->query("SELECT car_id, COUNT(*) AS cnt FROM bookings WHERE status IN ('pending','confirmed') GROUP BY car_id")->fetchAll() as $r) {
    $activePerCar[$r['car_id']] = (int)$r['cnt'];
}

$users = db()->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50")->fetchAll();

$auditLogs = [];
try { $auditLogs = db()->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50")->fetchAll(); } catch(Exception $e){}

// Revenue per day for mini chart (last 7 days)
$revenueByDay = [];
try {
    $rows = db()->query("SELECT DATE(created_at) as d, SUM(grand_total) as rev FROM bookings WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll();
    foreach ($rows as $r) $revenueByDay[$r['d']] = $r['rev'];
} catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>TD Admin — <?= ucfirst($page) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d0d0d;
  --bg2:#161616;
  --bg3:#1e1e1e;
  --bg4:#252525;
  --border:#2a2a2a;
  --border2:#333;
  --fg:#f0f0f0;
  --fg2:#999;
  --fg3:#666;
  --red:#e03535;
  --red2:#c02020;
  --green:#22c55e;
  --yellow:#f59e0b;
  --blue:#3b82f6;
  --font:'Inter',sans-serif;
  --display:'Bebas Neue',sans-serif;
  --sidebar:220px;
  --radius:6px;
}
html,body{height:100%;background:var(--bg);color:var(--fg);font-family:var(--font);font-size:14px;line-height:1.5}
a{color:inherit;text-decoration:none}
img{display:block;max-width:100%}
button{font-family:var(--font);cursor:pointer}

/* LAYOUT */
.shell{display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{width:var(--sidebar);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50}
.sb-brand{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid var(--border)}
.sb-brand-name{font-family:var(--display);font-size:1.4rem;letter-spacing:.05em;color:var(--fg)}
.sb-brand-sub{font-size:.65rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg3);margin-top:2px}
.sb-nav{flex:1;padding:1rem 0}
.sb-item{display:flex;align-items:center;gap:.75rem;padding:.65rem 1.25rem;font-size:.75rem;font-weight:500;letter-spacing:.04em;text-transform:uppercase;color:var(--fg2);transition:.15s;border-left:3px solid transparent;cursor:pointer}
.sb-item:hover{color:var(--fg);background:var(--bg3)}
.sb-item.active{color:var(--fg);background:var(--bg3);border-left-color:var(--red)}
.sb-item svg{width:16px;height:16px;flex-shrink:0}
.sb-bottom{padding:1rem 1.25rem;border-top:1px solid var(--border)}
.sb-add{display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.75rem;background:var(--red);color:#fff;border:none;border-radius:var(--radius);font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;transition:.15s;margin-bottom:1rem}
.sb-add:hover{background:var(--red2)}
.sb-user{display:flex;align-items:center;gap:.75rem}
.sb-avatar{width:36px;height:36px;border-radius:50%;background:var(--bg4);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--fg2);flex-shrink:0}
.sb-uname{font-size:.8rem;font-weight:600}
.sb-urole{font-size:.65rem;color:var(--fg3);text-transform:uppercase;letter-spacing:.06em}
.sb-link{display:flex;align-items:center;gap:.5rem;font-size:.7rem;color:var(--fg3);padding:.35rem 0;transition:.15s}
.sb-link:hover{color:var(--fg2)}
.sb-link svg{width:13px;height:13px}

/* MAIN */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}

/* TOPBAR */
.topbar{display:flex;align-items:center;justify-content:space-between;padding:1rem 2rem;border-bottom:1px solid var(--border);background:var(--bg);position:sticky;top:0;z-index:40}
.topbar-left h1{font-family:var(--display);font-size:1.6rem;letter-spacing:.04em}
.topbar-left p{font-size:.65rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg3);margin-top:1px}
.topbar-right{display:flex;align-items:center;gap:1rem}
.search-box{display:flex;align-items:center;gap:.5rem;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:.5rem .85rem;width:260px}
.search-box input{background:none;border:none;outline:none;color:var(--fg);font-size:.8rem;width:100%}
.search-box input::placeholder{color:var(--fg3)}
.search-box svg{width:14px;height:14px;color:var(--fg3);flex-shrink:0}
.icon-btn{background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:.5rem;display:flex;align-items:center;justify-content:center;transition:.15s}
.icon-btn:hover{background:var(--bg4)}
.icon-btn svg{width:16px;height:16px;color:var(--fg2)}

/* PAGE CONTENT */
.content{padding:2rem;flex:1}

/* FLASH */
.flash{padding:.75rem 1rem;border-radius:var(--radius);font-size:.8rem;margin-bottom:1.5rem;font-weight:500}
.flash.ok{background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.25)}
.flash.err{background:rgba(224,53,53,.12);color:#f87171;border:1px solid rgba(224,53,53,.25)}

/* STAT CARDS */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem}
.stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem 1.5rem;position:relative;overflow:hidden}
.stat-card.accent{border-color:var(--red);background:rgba(224,53,53,.07)}
.stat-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg3);margin-bottom:.5rem;display:flex;align-items:center;justify-content:space-between}
.stat-badge{font-size:.6rem;color:var(--green);background:rgba(34,197,94,.12);padding:2px 6px;border-radius:3px;font-weight:600}
.stat-badge.warn{color:var(--red);background:rgba(224,53,53,.12)}
.stat-value{font-family:var(--display);font-size:2.4rem;letter-spacing:.02em;line-height:1}
.stat-sub{font-size:.7rem;color:var(--fg3);margin-top:.4rem}

/* SECTION CARD */
.sec{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:1.5rem}
.sec-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border)}
.sec-title{font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg2);font-weight:600}
.sec-body{padding:1.25rem}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:.4rem;border:none;border-radius:var(--radius);font-family:var(--font);font-weight:600;cursor:pointer;transition:.15s;font-size:.72rem;letter-spacing:.05em;text-transform:uppercase}
.btn-red{background:var(--red);color:#fff;padding:.45rem 1rem}
.btn-red:hover{background:var(--red2)}
.btn-ghost{background:transparent;color:var(--fg2);border:1px solid var(--border2);padding:.45rem 1rem}
.btn-ghost:hover{background:var(--bg4);color:var(--fg)}
.btn-sm{padding:.3rem .75rem;font-size:.65rem}
.btn-outline-red{background:transparent;color:var(--red);border:1px solid var(--red);padding:.3rem .75rem;font-size:.65rem}
.btn-outline-red:hover{background:rgba(224,53,53,.1)}

/* TABLE */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.78rem}
th{font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;color:var(--fg3);padding:.6rem 1rem;border-bottom:1px solid var(--border);text-align:left;font-weight:500;white-space:nowrap}
td{padding:.75rem 1rem;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border:none}
tr:hover td{background:rgba(255,255,255,.02)}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;font-size:.6rem;font-weight:700;padding:3px 8px;border-radius:3px;text-transform:uppercase;letter-spacing:.06em}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor}
.b-confirmed{background:rgba(34,197,94,.12);color:#4ade80}
.b-pending{background:rgba(245,158,11,.12);color:#fbbf24}
.b-cancelled{background:rgba(224,53,53,.12);color:#f87171}
.b-completed{background:rgba(59,130,246,.12);color:#60a5fa}
.b-active{background:rgba(34,197,94,.12);color:#4ade80}
.b-upcoming{background:rgba(245,158,11,.12);color:#fbbf24}
.b-available{background:rgba(34,197,94,.12);color:#4ade80}
.b-hidden{background:rgba(224,53,53,.12);color:#f87171}
.b-featured{background:rgba(59,130,246,.12);color:#60a5fa}
.b-verified{background:rgba(34,197,94,.12);color:#4ade80}
.b-rejected{background:rgba(224,53,53,.12);color:#f87171}

/* MINI BAR CHART */
.mini-chart{display:flex;align-items:flex-end;gap:3px;height:50px;margin-top:.75rem}
.mini-bar{flex:1;background:var(--bg4);border-radius:2px 2px 0 0;min-height:4px;transition:.3s}
.mini-bar.hi{background:var(--red)}

/* VEHICLE CARD (fleet) */
.fleet-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}
.fleet-card{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;position:relative}
.fleet-card-img{height:160px;background:var(--bg4);position:relative;overflow:hidden}
.fleet-card-img img{width:100%;height:100%;object-fit:cover}
.fleet-card-img .status-pip{position:absolute;top:.75rem;left:.75rem}
.fleet-card-body{padding:1rem}
.fleet-card-cat{font-size:.6rem;text-transform:uppercase;letter-spacing:.12em;color:var(--red);font-weight:600;margin-bottom:.2rem}
.fleet-card-name{font-family:var(--display);font-size:1.5rem;letter-spacing:.03em;margin-bottom:.75rem}
.fleet-specs{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:1rem}
.spec-item label{font-size:.55rem;text-transform:uppercase;letter-spacing:.1em;color:var(--fg3);display:block;margin-bottom:2px}
.spec-item span{font-size:.8rem;font-weight:600}
.fleet-card-actions{display:flex;gap:.5rem;border-top:1px solid var(--border);padding-top:.75rem}
.fleet-add-card{background:var(--bg3);border:2px dashed var(--border2);border-radius:var(--radius);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.75rem;padding:2rem;min-height:300px;cursor:pointer;transition:.15s}
.fleet-add-card:hover{border-color:var(--fg3)}
.fleet-add-icon{width:52px;height:52px;background:var(--bg4);border-radius:50%;display:flex;align-items:center;justify-content:center}
.fleet-add-title{font-weight:600;font-size:.9rem}
.fleet-add-sub{font-size:.75rem;color:var(--fg3);text-align:center}

/* CUSTOMER */
.customer-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
.cust-stat{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.25rem}
.cust-stat.accent{border-color:var(--red);background:rgba(224,53,53,.07)}
.cust-stat-icon{font-size:1.2rem;margin-bottom:.5rem}
.cust-stat-label{font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--fg3);margin-bottom:.25rem}
.cust-stat-badge{font-size:.6rem;color:var(--green);float:right}
.cust-stat-value{font-family:var(--display);font-size:1.8rem}
.cust-stat-sub{font-size:.65rem;color:var(--fg3);margin-top:2px}
.avatar-circle{width:34px;height:34px;border-radius:50%;background:var(--bg4);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--fg2);flex-shrink:0}
.freq-dots{display:flex;gap:2px;align-items:center}
.dot{width:8px;height:8px;border-radius:50%;background:var(--bg4)}
.dot.on{background:var(--red)}
.geo-bar{margin-bottom:.75rem}
.geo-label{display:flex;justify-content:space-between;font-size:.75rem;margin-bottom:.3rem}
.geo-track{background:var(--bg4);height:4px;border-radius:2px}
.geo-fill{background:var(--red);height:4px;border-radius:2px}
.review-card{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;margin-bottom:.75rem}
.review-stars{color:var(--yellow);font-size:.85rem;margin-bottom:.5rem}
.review-text{font-size:.8rem;color:var(--fg2);line-height:1.6;margin-bottom:.5rem}
.review-author{font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;color:var(--fg3)}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}

/* RESERVATIONS */
.res-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem}
.res-stat{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem 1.5rem;position:relative;overflow:hidden}
.res-stat.accent{border-color:var(--red)}
.res-stat-label{font-size:.6rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg3);margin-bottom:.5rem}
.res-stat-value{font-family:var(--display);font-size:2rem;line-height:1}
.res-stat-value.red{color:var(--red)}
.filter-row{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
.filter-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.1em;color:var(--fg3)}
.filter-tabs{display:flex;gap:.35rem}
.filter-tab{background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);padding:.35rem .85rem;font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;cursor:pointer;color:var(--fg2);transition:.15s}
.filter-tab:hover{color:var(--fg)}
.filter-tab.on{background:var(--red);border-color:var(--red);color:#fff}
.action-icon{background:none;border:none;color:var(--fg3);cursor:pointer;padding:.2rem;transition:.15s;display:inline-flex}
.action-icon:hover{color:var(--fg)}
.action-icon svg{width:15px;height:15px}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:200;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal-box{background:var(--bg2);border:1px solid var(--border2);border-radius:8px;padding:2rem;width:100%;max-width:560px;max-height:90vh;overflow-y:auto}
.modal-title{font-family:var(--display);font-size:1.8rem;margin-bottom:1.5rem;letter-spacing:.03em}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.fg{margin-bottom:.85rem}
.fg label{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--fg3);display:block;margin-bottom:.35rem}
.fg input,.fg textarea,.fg select{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--fg);font-family:var(--font);font-size:.85rem;padding:.55rem .85rem;outline:none;transition:.15s}
.fg input:focus,.fg textarea:focus,.fg select:focus{border-color:var(--red)}
.fg textarea{resize:vertical;min-height:70px}
.chk-row{display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--fg2)}
.modal-footer{display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem}

/* SELECT status */
.status-sel{background:var(--bg3);border:1px solid var(--border2);color:var(--fg);border-radius:4px;padding:.25rem .5rem;font-size:.72rem}

/* FOOTER */
.admin-footer{border-top:1px solid var(--border);padding:1.25rem 2rem;font-size:.7rem;color:var(--fg3);text-align:center}

/* DASHBOARD SPECIAL */
.dash-grid{display:grid;grid-template-columns:1fr 340px;gap:1.5rem;margin-bottom:1.5rem}
.fleet-overview-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;position:relative;overflow:hidden}
.fleet-overview-card h2{font-size:.65rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg3);margin-bottom:.75rem}
.fleet-big-num{font-family:var(--display);font-size:5rem;line-height:1;margin-bottom:.25rem}
.fleet-big-label{font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;color:var(--red);font-weight:600}
.fleet-sub-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border)}
.fleet-sub-item label{font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--fg3);display:block;margin-bottom:4px}
.fleet-sub-item span{font-family:var(--display);font-size:1.8rem}
.fleet-sub-bar{height:3px;border-radius:2px;margin-top:.35rem}
.revenue-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem}
.rev-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg3);display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}
.rev-badge{font-size:.65rem;color:var(--green);background:rgba(34,197,94,.12);padding:2px 6px;border-radius:3px;font-weight:600}
.rev-value{font-family:var(--display);font-size:2.2rem;margin-bottom:.75rem}
.verif-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;margin-top:1.5rem}
.verif-item{display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--border)}
.verif-item:last-child{border:none}
.verif-avatar{width:34px;height:34px;border-radius:50%;background:var(--bg4);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0}
.verif-name{font-size:.82rem;font-weight:600}
.verif-role{font-size:.65rem;color:var(--fg3);text-transform:uppercase;letter-spacing:.06em}
.verif-action{margin-left:auto;font-size:.65rem;font-weight:700;letter-spacing:.06em;color:var(--red);background:none;border:none;cursor:pointer;text-transform:uppercase;transition:.15s}
.verif-action:hover{color:var(--fg)}
.verif-all{width:100%;padding:.6rem;background:none;border:1px solid var(--border2);border-radius:var(--radius);font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--fg3);cursor:pointer;transition:.15s;margin-top:.75rem}
.verif-all:hover{color:var(--fg);border-color:var(--fg3)}
</style>
</head>
<body>
<div class="shell">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-brand-name">TD ADMIN</div>
    <div class="sb-brand-sub">Fleet Management</div>
  </div>
  <nav class="sb-nav">
    <a href="admin.php?page=dashboard" class="sb-item <?= $page==='dashboard'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="admin.php?page=reservations" class="sb-item <?= $page==='reservations'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Reservations
    </a>
    <a href="admin.php?page=fleet" class="sb-item <?= $page==='fleet'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="9" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
      Vehicles
    </a>
    <a href="admin.php?page=customers" class="sb-item <?= $page==='customers'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      Customers
    </a>
    <a href="admin.php?page=audit" class="sb-item <?= $page==='audit'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Analytics
    </a>
  </nav>
  <div class="sb-bottom">
    <button class="sb-add" onclick="openModal('addModal')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Vehicle
    </button>
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
      <div>
        <div class="sb-uname"><?= htmlspecialchars($user['name']) ?></div>
        <div class="sb-urole">Chief Operator</div>
      </div>
    </div>
    <div style="margin-top:.75rem;display:flex;gap:1rem">
      <a href="index.php" class="sb-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>
        Site
      </a>
      <a href="logout.php" class="sb-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
    </div>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

<?php if ($flash['msg']): ?>
<div style="padding:1rem 2rem 0">
  <div class="flash <?= $flash['type']==='success'?'ok':'err' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
</div>
<?php endif; ?>

<?php /* ===== DASHBOARD ===== */ if ($page === 'dashboard'): ?>
<div class="topbar">
  <div class="topbar-left">
    <h1>OPERATIONS CONTROL</h1>
    <p>Status: All Systems Functional</p>
  </div>
  <div class="topbar-right">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search VIN, user, or ID..."/>
    </div>
    <button class="icon-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg></button>
    <button class="icon-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></button>
  </div>
</div>
<div class="content">
  <div class="dash-grid">
    <div class="fleet-overview-card">
      <h2>Fleet Overview</h2>
      <div class="fleet-big-num"><?= $totalCars ?></div>
      <div class="fleet-big-label">Vehicles Active</div>
      <div class="fleet-sub-stats">
        <div class="fleet-sub-item">
          <label>Maintenance</label>
          <span><?= $totalCars - $availCars ?></span>
          <div class="fleet-sub-bar" style="background:var(--blue);width:<?= $totalCars>0?round(($totalCars-$availCars)/$totalCars*100):0 ?>%"></div>
        </div>
        <div class="fleet-sub-item">
          <label>Available</label>
          <span><?= $availCars ?></span>
          <div class="fleet-sub-bar" style="background:var(--red);width:<?= $totalCars>0?round($availCars/$totalCars*100):0 ?>%"></div>
        </div>
        <div class="fleet-sub-item">
          <label>Utilization</label>
          <span><?= $totalCars>0?round($confirmedCount/$totalCars*100).'%':'0%' ?></span>
          <div class="fleet-sub-bar" style="background:var(--fg3);width:<?= $totalCars>0?min(100,round($confirmedCount/$totalCars*100)):0 ?>%"></div>
        </div>
      </div>
    </div>
    <div>
      <div class="revenue-card">
        <div class="rev-label">
          Daily Revenue
          <span class="rev-badge">+12.4%</span>
        </div>
        <div class="rev-value">$<?= number_format($totalRevenue,0) ?></div>
        <div class="mini-chart">
          <?php
          $vals = array_values($revenueByDay);
          if(count($vals)<7) $vals = array_merge(array_fill(0,7-count($vals),0),$vals);
          $max = max($vals)?:1;
          foreach($vals as $i=>$v):
            $h = max(4,round($v/$max*100));
          ?>
          <div class="mini-bar <?= $i===count($vals)-1?'hi':'' ?>" style="height:<?= $h ?>%"></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="verif-card">
        <div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg3);margin-bottom:.75rem">Verification Queue</div>
        <?php foreach(array_slice($users,0,2) as $u): ?>
        <div class="verif-item">
          <div class="verif-avatar"><?= strtoupper(substr($u['name'],0,1)) ?></div>
          <div>
            <div class="verif-name"><?= htmlspecialchars($u['name']) ?></div>
            <div class="verif-role"><?= $u['is_elite']?'Pro Membership':'Standard' ?></div>
          </div>
          <button class="verif-action">Review</button>
        </div>
        <?php endforeach; ?>
        <button class="verif-all">View All (<?= $pendingCount ?> Pending)</button>
      </div>
    </div>
  </div>

  <!-- RECENT BOOKINGS -->
  <div class="sec">
    <div class="sec-head">
      <span class="sec-title">Recent Global Bookings</span>
      <div style="display:flex;gap:.5rem">
        <button class="btn btn-ghost btn-sm">Filter by Status</button>
        <button class="btn btn-ghost btn-sm">Export CSV</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Vehicle</th><th>Client</th><th>Duration</th><th>Status</th><th>Revenue</th>
        </tr></thead>
        <tbody>
        <?php foreach(array_slice($bookings,0,6) as $b): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.75rem">
              <div style="width:44px;height:32px;background:var(--bg4);border-radius:4px;overflow:hidden;flex-shrink:0">
                <img src="images/<?= htmlspecialchars($b['image_file']??'hero-porsche.jpg') ?>" style="width:100%;height:100%;object-fit:cover" onerror="this.parentNode.style.background='var(--bg4)'"/>
              </div>
              <div>
                <div style="font-weight:600;font-size:.8rem"><?= htmlspecialchars($b['car_name']) ?></div>
                <div style="font-size:.65rem;color:var(--fg3)">ID #<?= $b['id'] ?></div>
              </div>
            </div>
          </td>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($b['customer']??'—') ?></div>
            <div style="font-size:.65rem;color:var(--fg3)"><?= htmlspecialchars($b['customer_email']??'') ?></div>
          </td>
          <td><?= (int)$b['days'] ?> Day<?= $b['days']!=1?'s':'' ?></td>
          <td><span class="badge b-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
          <td style="font-weight:600">$<?= number_format($b['grand_total'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php /* ===== FLEET ===== */ elseif ($page === 'fleet'): ?>
<div class="topbar">
  <div class="topbar-left">
    <h1>FLEET INVENTORY</h1>
    <p>
      <span style="color:var(--red);font-weight:600"><?= $totalCars ?></span> Total Vehicles &nbsp;·&nbsp;
      <?= $totalCars-$availCars ?> In Maintenance &nbsp;·&nbsp;
      <span style="color:var(--red)"><?= $availCars ?></span> Active
    </p>
  </div>
  <div class="topbar-right">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search VIN, Model or Plate..."/>
    </div>
    <button class="btn btn-ghost btn-sm">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
      Filter
    </button>
  </div>
</div>
<div class="content">
  <div class="fleet-grid">
    <?php foreach($cars as $c):
      $ac = $activePerCar[$c['id']] ?? 0;
      $statusLabel = !$c['available'] ? 'Hidden' : ($ac>0 ? 'Reserved' : 'Available');
      $statusClass = !$c['available'] ? 'b-hidden' : ($ac>0 ? 'b-upcoming' : 'b-available');
    ?>
    <div class="fleet-card">
      <div class="fleet-card-img">
        <img src="images/<?= htmlspecialchars($c['image_file']) ?>" alt="" onerror="this.style.display='none'"/>
        <div class="status-pip"><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></div>
      </div>
      <div class="fleet-card-body">
        <div class="fleet-card-cat"><?= htmlspecialchars($c['tag']) ?></div>
        <div class="fleet-card-name"><?= htmlspecialchars($c['name']) ?>
          <?php if($c['is_featured']): ?><span class="badge b-featured" style="font-size:.55rem;vertical-align:middle;margin-left:.5rem">Featured</span><?php endif; ?>
        </div>
        <div class="fleet-specs">
          <div class="spec-item"><label>0–100</label><span><?= htmlspecialchars($c['acceleration']??'—') ?>s</span></div>
          <div class="spec-item"><label>Engine</label><span style="font-size:.7rem"><?= htmlspecialchars($c['engine']??'—') ?></span></div>
          <div class="spec-item"><label>Status</label><span style="font-size:.75rem"><?= $c['available']?'Active':'Hidden' ?></span></div>
        </div>
        <div style="font-size:.8rem;color:var(--fg3);margin-bottom:.75rem">$<?= number_format($c['price_per_day'],0) ?>/day <?php if($ac>0): ?>· <span style="color:var(--yellow)"><?= $ac ?> active booking<?= $ac>1?'s':'' ?></span><?php endif; ?></div>
        <div class="fleet-card-actions">
          <button class="btn btn-ghost btn-sm" style="flex:1" onclick='openEditModal(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            Edit Specs
          </button>
          <button class="btn btn-outline-red" style="flex:1" <?= $ac>0?'disabled title="Cancel active bookings first"':'' ?> onclick="confirmDelete(<?= $c['id'] ?>,'<?= htmlspecialchars($c['name'],ENT_QUOTES) ?>')">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?= $ac>0?'Has Bookings':'Delete' ?>
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <!-- Add new card -->
    <div class="fleet-add-card" onclick="openModal('addModal')">
      <div class="fleet-add-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </div>
      <div class="fleet-add-title">Expand Your Fleet</div>
      <div class="fleet-add-sub">Add a new high-performance<br>machine to the inventory.</div>
      <button class="btn btn-ghost btn-sm">Start Entry</button>
    </div>
  </div>
</div>

<?php /* ===== CUSTOMERS ===== */ elseif ($page === 'customers'): ?>
<div class="topbar">
  <div class="topbar-left">
    <h1>CUSTOMER INSIGHTS</h1>
    <p>Registered Drivers &amp; Behavioral Data</p>
  </div>
  <div class="topbar-right">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search customer records..."/>
    </div>
    <button class="icon-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
  </div>
</div>
<div class="content">
  <div class="customer-stats">
    <div class="cust-stat">
      <div class="cust-stat-icon">💳</div>
      <div class="cust-stat-label">Avg LTV <span class="cust-stat-badge">+12.4%</span></div>
      <div class="cust-stat-value">$<?= number_format($totalRevenue/max(1,$totalUsers),0) ?></div>
      <div class="cust-stat-sub">from last quarter</div>
    </div>
    <div class="cust-stat">
      <div class="cust-stat-icon">🔄</div>
      <div class="cust-stat-label">Rental Freq</div>
      <div class="cust-stat-value"><?= $totalUsers>0?number_format($totalBookings/$totalUsers,1):0 ?>x</div>
      <div class="cust-stat-sub">Annual avg per user</div>
    </div>
    <div class="cust-stat">
      <div class="cust-stat-icon">✅</div>
      <div class="cust-stat-label">Approval Rate</div>
      <div class="cust-stat-value"><?= $totalBookings>0?round($confirmedCount/$totalBookings*100):92 ?>%</div>
      <div class="cust-stat-sub">Verified high-net users</div>
    </div>
    <div class="cust-stat accent">
      <div class="cust-stat-icon">👥</div>
      <div class="cust-stat-label">Total Active</div>
      <div class="cust-stat-value" style="color:var(--fg)"><?= $totalUsers ?></div>
      <div class="cust-stat-sub">Elite membership tier</div>
    </div>
  </div>

  <div class="sec">
    <div class="sec-head">
      <span class="sec-title">Customer Directory</span>
      <div style="display:flex;gap:.5rem">
        <button class="btn btn-ghost btn-sm">Export CSV</button>
        <button class="btn btn-ghost btn-sm">Filters</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Customer Details</th><th>Status</th><th>Frequency</th><th>Lifetime Value</th><th>Action</th>
        </tr></thead>
        <tbody>
        <?php foreach($users as $u):
          $uBookings = array_filter($bookings, fn($b) => $b['customer_email'] === $u['email']);
          $uCount = count($uBookings);
          $uRevenue = array_sum(array_column(array_filter($uBookings, fn($b)=>$b['status']!=='cancelled'),'grand_total'));
          $statusClass = $u['is_elite'] ? 'b-verified' : 'b-pending';
          $statusLabel = $u['is_elite'] ? 'Verified' : 'Standard';
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.75rem">
              <div class="avatar-circle"><?= strtoupper(substr($u['name'],0,2)) ?></div>
              <div>
                <div style="font-weight:600"><?= htmlspecialchars($u['name']) ?></div>
                <div style="font-size:.7rem;color:var(--fg3)"><?= htmlspecialchars($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:.5rem">
              <div class="freq-dots">
                <?php for($i=0;$i<5;$i++): ?><div class="dot <?= $i<min(5,$uCount)?'on':'' ?>"></div><?php endfor; ?>
              </div>
              <span style="font-size:.72rem;color:var(--fg3)"><?= $uCount ?> Rental<?= $uCount!=1?'s':'' ?></span>
            </div>
          </td>
          <td style="font-weight:600">$<?= number_format($uRevenue,2) ?></td>
          <td>
            <button class="action-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:.75rem 1rem;font-size:.7rem;color:var(--fg3);border-top:1px solid var(--border)">
      Showing <?= count($users) ?> of <?= $totalUsers ?> users
    </div>
  </div>

  <div class="two-col">
    <div class="sec">
      <div class="sec-head"><span class="sec-title">Geographic Hubs</span><a href="#" style="font-size:.65rem;color:var(--fg3)">View Heatmap →</a></div>
      <div class="sec-body">
        <?php
        $hubs = [['Los Angeles',42],['New York',28],['Miami',18],['Las Vegas',12]];
        foreach($hubs as $h): ?>
        <div class="geo-bar">
          <div class="geo-label"><span><?= $h[0] ?></span><span style="color:var(--fg3)"><?= $h[1] ?>% Activity</span></div>
          <div class="geo-track"><div class="geo-fill" style="width:<?= $h[1] ?>%"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="sec">
      <div class="sec-head"><span class="sec-title">Client Sentiment</span></div>
      <div class="sec-body">
        <div class="review-card">
          <div class="review-stars">★★★★★</div>
          <div class="review-text">"The GT3 RS is a beast. TD Rentals delivered it straight to my hotel in Beverly Hills. Pure perfection."</div>
          <div class="review-author">James Harrison — 2 Days Ago</div>
        </div>
        <div class="review-card">
          <div class="review-stars">★★★★<span style="color:var(--fg3)">★</span></div>
          <div class="review-text">"Excellent fleet selection, though concierge response was slightly delayed during peak season."</div>
          <div class="review-author">Sarah K. — 1 Week Ago</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php /* ===== RESERVATIONS ===== */ elseif ($page === 'reservations'): ?>
<div class="topbar">
  <div class="topbar-left">
    <h1>RESERVATIONS</h1>
  </div>
  <div class="topbar-right">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search Bookings..."/>
    </div>
    <button class="icon-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="12" y1="18" x2="12" y2="18"/></svg></button>
  </div>
</div>
<div class="content">
  <div class="res-stats">
    <div class="res-stat">
      <div class="res-stat-label">Total Active</div>
      <div class="res-stat-value"><?= $confirmedCount ?></div>
    </div>
    <div class="res-stat accent">
      <div class="res-stat-label">Pending Review</div>
      <div class="res-stat-value red"><?= $pendingCount ?></div>
    </div>
    <div class="res-stat">
      <div class="res-stat-label">Revenue Forecast (M-T-D)</div>
      <div class="res-stat-value">$<?= number_format($totalRevenue,2) ?></div>
    </div>
  </div>

  <div class="filter-row">
    <span class="filter-label">Booking Status</span>
    <div class="filter-tabs">
      <button class="filter-tab on" onclick="filterBookings('all',this)">All</button>
      <button class="filter-tab" onclick="filterBookings('confirmed',this)">Confirmed</button>
      <button class="filter-tab" onclick="filterBookings('pending',this)">Pending</button>
      <button class="filter-tab" onclick="filterBookings('completed',this)">Completed</button>
    </div>
  </div>

  <div class="sec">
    <div class="tbl-wrap">
      <table id="bookings-table">
        <thead><tr>
          <th style="width:100px">Booking ID</th>
          <th>Customer</th>
          <th>Vehicle</th>
          <th>Dates</th>
          <th>Status</th>
          <th>Total</th>
          <th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach($bookings as $b): ?>
        <tr data-status="<?= $b['status'] ?>">
          <td style="color:var(--red);font-weight:700;font-size:.8rem">#TD-<?= str_pad($b['id'],4,'0',STR_PAD_LEFT) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:.6rem">
              <div class="avatar-circle" style="width:30px;height:30px;font-size:.65rem"><?= strtoupper(substr($b['customer']??'?',0,2)) ?></div>
              <span style="font-weight:500"><?= htmlspecialchars($b['customer']??'—') ?></span>
            </div>
          </td>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($b['car_name']) ?></div>
            <div style="font-size:.65rem;color:var(--fg3)"><?= (int)$b['days'] ?> day<?= $b['days']!=1?'s':'' ?></div>
          </td>
          <td style="font-size:.75rem">
            <?= htmlspecialchars($b['pickup_date']) ?> →<br>
            <?= htmlspecialchars($b['dropoff_date']) ?>
          </td>
          <td><span class="badge b-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
          <td style="font-weight:700">$<?= number_format($b['grand_total'],2) ?></td>
          <td>
            <div style="display:flex;gap:.35rem;align-items:center">
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="update_status"/>
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>"/>
                <select name="status" class="status-sel" onchange="this.form.submit()">
                  <?php foreach(['pending','confirmed','cancelled','completed'] as $s): ?>
                  <option value="<?= $s ?>" <?= $b['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:.75rem 1rem;font-size:.7rem;color:var(--fg3);border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <span>Showing <?= count($bookings) ?> of <?= $totalBookings ?> entries</span>
    </div>
  </div>
</div>

<?php /* ===== AUDIT ===== */ elseif ($page === 'audit'): ?>
<div class="topbar">
  <div class="topbar-left"><h1>ANALYTICS &amp; AUDIT LOG</h1><p>System activity and admin actions</p></div>
</div>
<div class="content">
  <div class="stats-row">
    <div class="stat-card"><div class="stat-label">Total Bookings</div><div class="stat-value"><?= $totalBookings ?></div><div class="stat-sub">All time</div></div>
    <div class="stat-card"><div class="stat-label">Total Revenue</div><div class="stat-value" style="font-size:1.8rem">$<?= number_format($totalRevenue,0) ?></div><div class="stat-sub">Excl. cancelled</div></div>
    <div class="stat-card accent"><div class="stat-label">Pending Actions <span class="stat-badge warn"><?= $pendingCount ?></span></div><div class="stat-value"><?= $pendingCount ?></div><div class="stat-sub">Need review</div></div>
  </div>
  <div class="sec">
    <div class="sec-head"><span class="sec-title">Audit Log</span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Time</th><th>Action</th><th>Admin</th><th>Target</th><th>Detail</th></tr></thead>
        <tbody>
        <?php if($auditLogs): foreach($auditLogs as $log): ?>
        <tr>
          <td style="font-size:.72rem;color:var(--fg3);white-space:nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
          <td><span style="font-size:.72rem;font-weight:600;color:var(--red);text-transform:uppercase;letter-spacing:.04em"><?= htmlspecialchars(str_replace('_',' ',$log['action'])) ?></span></td>
          <td style="font-size:.78rem"><?= htmlspecialchars($log['admin_name']??'—') ?></td>
          <td style="font-size:.72rem;color:var(--fg3)"><?= htmlspecialchars($log['target_type']??'') ?> <?= $log['target_id']?'#'.$log['target_id']:'' ?></td>
          <td style="font-size:.72rem;color:var(--fg3)"><?= htmlspecialchars($log['detail']??'') ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" style="text-align:center;color:var(--fg3);padding:2rem">No audit entries yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<footer class="admin-footer">TD RENTALS &copy; 2024 — Engineered for Performance</footer>
</main>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-title">ADD VEHICLE</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_vehicle"/>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
      <div class="fg"><label>Name *</label><input type="text" name="car_name" required placeholder="e.g. FERRARI SF90"/></div>
      <div class="fg"><label>Subtitle / Tagline</label><input type="text" name="subtitle" placeholder="e.g. Maranello's Finest"/></div>
      <div class="form-row">
        <div class="fg"><label>Price / Day (USD) *</label><input type="number" name="price_per_day" min="1" step="0.01" required placeholder="1500"/></div>
        <div class="fg"><label>Tag / Badge</label><input type="text" name="tag" placeholder="HYBRID V6"/></div>
      </div>
      <div class="form-row">
        <div class="fg"><label>Top Speed (km/h)</label><input type="text" name="top_speed" placeholder="320"/></div>
        <div class="fg"><label>0–100 (sec)</label><input type="text" name="acceleration" placeholder="3.0"/></div>
      </div>
      <div class="form-row">
        <div class="fg"><label>Max Power (PS)</label><input type="text" name="max_power" placeholder="600"/></div>
        <div class="fg"><label>Engine</label><input type="text" name="engine" placeholder="4.0 FLAT-6"/></div>
      </div>
      <div class="fg"><label>Description 1</label><textarea name="description1"></textarea></div>
      <div class="fg"><label>Description 2</label><textarea name="description2"></textarea></div>
      <div class="fg"><label>Photo (JPG/PNG/WebP)</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"/></div>
      <div class="chk-row" style="margin-bottom:.85rem"><input type="checkbox" name="is_featured" id="aFeat" value="1"/><label for="aFeat">Mark as Featured (shown on homepage)</label></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-red">Add Vehicle</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-title">EDIT VEHICLE</div>
    <form method="POST" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action" value="edit_vehicle"/>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
      <input type="hidden" name="car_id" id="eId"/>
      <div class="fg"><label>Name *</label><input type="text" name="car_name" id="eName" required/></div>
      <div class="fg"><label>Subtitle</label><input type="text" name="subtitle" id="eSub"/></div>
      <div class="form-row">
        <div class="fg"><label>Price / Day (USD) *</label><input type="number" name="price_per_day" id="ePrice" min="1" step="0.01" required/></div>
        <div class="fg"><label>Tag / Badge</label><input type="text" name="tag" id="eTag"/></div>
      </div>
      <div class="form-row">
        <div class="fg"><label>Top Speed (km/h)</label><input type="text" name="top_speed" id="eSpeed"/></div>
        <div class="fg"><label>0–100 (sec)</label><input type="text" name="acceleration" id="eAccel"/></div>
      </div>
      <div class="form-row">
        <div class="fg"><label>Max Power (PS)</label><input type="text" name="max_power" id="ePower"/></div>
        <div class="fg"><label>Engine</label><input type="text" name="engine" id="eEngine"/></div>
      </div>
      <div class="fg"><label>Description 1</label><textarea name="description1" id="eDesc1"></textarea></div>
      <div class="fg"><label>Description 2</label><textarea name="description2" id="eDesc2"></textarea></div>
      <div class="fg"><label>Replace Photo (optional)</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"/></div>
      <div style="display:flex;gap:1.5rem;margin-bottom:.85rem">
        <div class="chk-row"><input type="checkbox" name="is_featured" id="eFeat" value="1"/><label for="eFeat">Featured</label></div>
        <div class="chk-row"><input type="checkbox" name="available" id="eAvail" value="1"/><label for="eAvail">Available (visible)</label></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-red">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-title">DELETE VEHICLE?</div>
    <p id="deleteMsg" style="color:var(--fg2);font-size:.85rem;margin-bottom:1.5rem;line-height:1.7"></p>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="action" value="delete_vehicle"/>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
      <input type="hidden" name="car_id" id="deleteCarId"/>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
        <button type="submit" class="btn btn-red">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-overlay').forEach(m=>{
  m.addEventListener('click',function(e){if(e.target===this)closeModal(this.id)});
});
function openEditModal(car){
  document.getElementById('eId').value=car.id;
  document.getElementById('eName').value=car.name||'';
  document.getElementById('eSub').value=car.subtitle||'';
  document.getElementById('ePrice').value=car.price_per_day||'';
  document.getElementById('eTag').value=car.tag||'';
  document.getElementById('eSpeed').value=car.top_speed||'';
  document.getElementById('eAccel').value=car.acceleration||'';
  document.getElementById('ePower').value=car.max_power||'';
  document.getElementById('eEngine').value=car.engine||'';
  document.getElementById('eDesc1').value=car.description1||'';
  document.getElementById('eDesc2').value=car.description2||'';
  document.getElementById('eFeat').checked=car.is_featured==1;
  document.getElementById('eAvail').checked=car.available==1;
  openModal('editModal');
}
function confirmDelete(id,name){
  document.getElementById('deleteCarId').value=id;
  document.getElementById('deleteMsg').textContent='Are you sure you want to permanently delete "'+name+'"? This cannot be undone.';
  openModal('deleteModal');
}
function filterBookings(status,btn){
  document.querySelectorAll('.filter-tab').forEach(t=>t.classList.remove('on'));
  btn.classList.add('on');
  document.querySelectorAll('#bookings-table tbody tr').forEach(tr=>{
    tr.style.display=(status==='all'||tr.dataset.status===status)?'':'none';
  });
}
</script>
</body>
</html>
