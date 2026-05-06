<?php
require_once 'admin_functions.php';
requireAdmin();

// Resolve logic for both types
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resolve_appeal_id'])) {
        $id = (int)$_POST['resolve_appeal_id'];
        $stmt = $conn->prepare("UPDATE user_inquiries SET status = 'resolved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    } elseif (isset($_POST['resolve_contact_id'])) {
        $id = (int)$_POST['resolve_contact_id'];
        $stmt = $conn->prepare("UPDATE contact_messages SET status = 'resolved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: inquiries.php");
    exit;
}

// Fetch Appeals
$appeals = [];
$resAppeals = $conn->query("
    SELECT i.*, u.first_name, u.last_name, u.email, u.status as user_status, u.ban_expires_at 
    FROM user_inquiries i 
    JOIN users u ON u.id = i.user_id 
    ORDER BY i.created_at DESC
");
if ($resAppeals) {
    while($row = $resAppeals->fetch_assoc()) $appeals[] = $row;
}

// Fetch Contacts
$contacts = [];
$resContacts = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
if ($resContacts) {
    while($row = $resContacts->fetch_assoc()) $contacts[] = $row;
}

require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
    .inbox-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 1rem;
    }
    .inbox-tab {
        padding: 0.8rem 1.5rem;
        cursor: pointer;
        border-radius: 8px;
        font-weight: 600;
        transition: 0.3s;
        color: var(--text-secondary);
        background: transparent;
        border: none;
    }
    .inbox-tab.active {
        background: var(--red);
        color: #fff;
    }
    .inbox-section {
        display: none;
    }
    .inbox-section.active {
        display: block;
    }
</style>

<div class="admin-wrapper">
  <div class="main">
    <div class="topbar">
      <div class="topbar-left"><h1>ADMIN INBOX</h1><p>Centralized communication hub</p></div>
    </div>
    
    <div class="content">
      <div class="inbox-tabs">
        <button class="inbox-tab active" onclick="switchTab('contacts')">General Contacts (<?= count($contacts) ?>)</button>
        <button class="inbox-tab" onclick="switchTab('appeals')">Account Appeals (<?= count($appeals) ?>)</button>
      </div>

      <!-- General Contacts Section -->
      <div id="contacts-section" class="inbox-section active">
        <div class="sec">
          <div class="sec-head"><span class="sec-title">Recent Inquiries</span></div>
          <div class="tbl-wrap">
            <table>
              <thead><tr><th>Date</th><th>From</th><th>Message</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if(empty($contacts)): ?>
                  <tr><td colspan="4" style="text-align:center; padding: 2rem; color: #888;">No contact messages yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($contacts as $c): ?>
                  <tr style="opacity: <?= $c['status'] === 'resolved' ? '0.5' : '1' ?>;">
                    <td style="white-space: nowrap;"><?= date('M d, Y H:i', strtotime($c['created_at'])) ?></td>
                    <td>
                      <div style="font-weight:600">
                        <?= htmlspecialchars($c['name']) ?>
                        <?php if ($c['user_id']): ?>
                          <span style="font-size: 0.65rem; color: var(--red); font-weight: normal; margin-left: 4px;">(user)</span>
                        <?php endif; ?>
                      </div>
                      <div style="font-size:.7rem;color:var(--fg3)"><?= htmlspecialchars($c['email']) ?></div>
                    </td>
                    <td style="max-width: 400px;">
                        <div style="background: rgba(0,0,0,0.2); padding: 0.8rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem;">
                            <?= nl2br(htmlspecialchars($c['message'])) ?>
                        </div>
                    </td>
                    <td>
                      <?php if ($c['status'] === 'pending'): ?>
                        <form method="POST" style="margin:0;">
                          <input type="hidden" name="resolve_contact_id" value="<?= $c['id'] ?>">
                          <button class="btn btn-outline" style="width:100%; padding: 4px 8px; font-size: 0.7rem; border-color: #888; color: #aaa;">Mark Read</button>
                        </form>
                      <?php else: ?>
                        <span style="font-size:0.7rem; color:#4ade80; text-align:center;">READ</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Appeals Section -->
      <div id="appeals-section" class="inbox-section">
        <div class="sec">
          <div class="sec-head"><span class="sec-title">Restricted Account Appeals</span></div>
          <div class="tbl-wrap">
            <table>
              <thead><tr><th>Date</th><th>User</th><th>Status</th><th>Message</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if(empty($appeals)): ?>
                  <tr><td colspan="5" style="text-align:center; padding: 2rem; color: #888;">No active appeals.</td></tr>
                <?php else: ?>
                  <?php foreach ($appeals as $i): 
                      if ($i['user_status'] === 'timeout' && $i['ban_expires_at']) {
                          if (new DateTime() >= new DateTime($i['ban_expires_at'])) {
                              // Auto-restore in DB
                              $conn->query("UPDATE users SET status = 'active', ban_expires_at = NULL WHERE id = " . (int)$i['user_id']);
                              $i['user_status'] = 'active';
                              $i['ban_expires_at'] = null;
                          }
                      }

                      $statusClass = $i['user_status'] === 'banned' ? 'b-cancelled' : 'b-verified';
                      $statusLabel = strtoupper($i['user_status']);
                  ?>
                  <tr style="opacity: <?= $i['status'] === 'resolved' ? '0.5' : '1' ?>;">
                    <td style="white-space: nowrap;"><?= date('M d, Y H:i', strtotime($i['created_at'])) ?></td>
                    <td>
                      <div style="font-weight:600"><?= htmlspecialchars($i['first_name'] . ' ' . $i['last_name']) ?></div>
                      <div style="font-size:.7rem;color:var(--fg3)"><?= htmlspecialchars($i['email']) ?></div>
                    </td>
                    <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                    <td style="max-width: 300px;">
                        <div style="background: rgba(0,0,0,0.2); padding: 0.8rem; border-radius: 6px; font-size: 0.85rem;">
                            <?= nl2br(htmlspecialchars($i['message'])) ?>
                        </div>
                    </td>
                    <td>
                      <div style="display:flex; flex-direction:column; gap:0.5rem">
                          <?php if ($i['status'] === 'pending'): ?>
                          <form method="POST" style="margin:0;">
                              <input type="hidden" name="resolve_appeal_id" value="<?= $i['id'] ?>">
                              <button class="btn btn-outline" style="width:100%; padding: 4px 8px; font-size: 0.7rem; border-color: #888; color: #aaa;">Mark Resolved</button>
                          </form>
                          <?php endif; ?>
                          <?php if ($i['user_status'] !== 'active'): ?>
                              <button class="btn btn-outline" style="padding: 4px 8px; font-size: 0.7rem; border-color: #4ade80; color: #4ade80;" onclick="unbanUser(<?= $i['user_id'] ?>)">Restore Account</button>
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
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.inbox-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.inbox-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(tab + '-section').classList.add('active');
    event.currentTarget.classList.add('active');
}

function unbanUser(id) {
    if (confirm('Restore this user to active status?')) {
        const formData = new FormData();
        formData.append('user_id', id);
        formData.append('action', 'unban');
        formData.append('days', 0);

        fetch('user_actions.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { if (data.success) location.reload(); else alert('Error: ' + data.message); });
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
