<?php
require_once 'admin_functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    $resolve_id = (int)$_POST['resolve_id'];
    $stmt = $conn->prepare("UPDATE user_inquiries SET status = 'resolved' WHERE id = ?");
    $stmt->bind_param("i", $resolve_id);
    $stmt->execute();
    header("Location: inquiries.php");
    exit;
}

$inquiries = [];
$res = $conn->query("
    SELECT i.*, u.first_name, u.last_name, u.email, u.status as user_status, u.ban_expires_at 
    FROM user_inquiries i 
    JOIN users u ON u.id = i.user_id 
    ORDER BY i.created_at DESC
");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $inquiries[] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../../assets/css/admin.css">

<div class="admin-wrapper">
  <div class="main">
    <div class="topbar">
      <div class="topbar-left"><h1>USER APPEALS & INQUIRIES</h1><p>Review messages from restricted accounts</p></div>
    </div>
    
    <div class="content">
      <div class="sec">
        <div class="sec-head"><span class="sec-title">Inquiry Inbox</span></div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Date</th><th>User</th><th>Current Status</th><th>Message</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($inquiries)): ?>
            <tr><td colspan="5" style="text-align:center; padding: 2rem; color: #888;">No inquiries found.</td></tr>
            <?php else: ?>
            <?php foreach ($inquiries as $i): 
                if ($i['user_status'] === 'banned') {
                    $statusClass = 'b-cancelled';
                } elseif ($i['user_status'] === 'timeout') {
                    $statusClass = 'b-pending';
                } else {
                    $statusClass = 'b-verified';
                }
            ?>
            <tr style="opacity: <?= $i['status'] === 'resolved' ? '0.5' : '1' ?>;">
              <td style="white-space: nowrap;"><?= date('M d, Y H:i', strtotime($i['created_at'])) ?></td>
              <td>
                <div style="font-weight:600"><?= htmlspecialchars($i['first_name'] . ' ' . $i['last_name']) ?></div>
                <div style="font-size:.7rem;color:var(--fg3)"><?= htmlspecialchars($i['email']) ?></div>
              </td>
              <td>
                  <span class="badge <?= $statusClass ?>"><?= strtoupper($i['user_status']) ?></span>
                  <?php if ($i['user_status'] !== 'active' && $i['ban_expires_at']): ?>
                  <div style="font-size: 0.7rem; color: #aaa; margin-top: 4px;">Until: <?= date('M d, Y', strtotime($i['ban_expires_at'])) ?></div>
                  <?php endif; ?>
              </td>
              <td style="max-width: 300px;">
                  <div style="background: rgba(0,0,0,0.2); padding: 0.8rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; line-height: 1.4;">
                      <?= nl2br(htmlspecialchars($i['message'])) ?>
                  </div>
              </td>
              <td>
                <div style="display:flex; flex-direction:column; gap:0.5rem">
                    <?php if ($i['status'] === 'pending'): ?>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="resolve_id" value="<?= $i['id'] ?>">
                        <button class="btn btn-outline" style="width:100%; padding: 4px 8px; font-size: 0.7rem; border-color: #888; color: #aaa;">Mark Resolved</button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:0.7rem; color:#4ade80; text-align:center;">RESOLVED</span>
                    <?php endif; ?>

                    <?php if ($i['user_status'] !== 'active'): ?>
                    <button class="btn btn-outline" style="padding: 4px 8px; font-size: 0.7rem; border-color: #4ade80; color: #4ade80;" onclick="unbanUser(<?= $i['user_id'] ?>)">Restore User</button>
                    <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function unbanUser(id) {
    if (confirm('Restore this user to active status?')) {
        const formData = new FormData();
        formData.append('user_id', id);
        formData.append('action', 'unban');
        formData.append('days', 0);

        fetch('user_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('An unexpected error occurred.');
        });
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
