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

// Fetch Unread Live Messages Count for Tab
$unreadLiveCount = 0;
$resUnread = $conn->query("SELECT COUNT(*) FROM messages WHERE is_read = 0 AND receiver_id = " . (int)$_SESSION['user_id']);
if ($resUnread) $unreadLiveCount = $resUnread->fetch_row()[0];

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

    /* Live Chat Styles in Admin */
    .live-chat-layout {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 1rem;
        height: 600px;
        background: rgba(0,0,0,0.2);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }
    .conv-sidebar {
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .conv-item {
        padding: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        cursor: pointer;
        transition: 0.2s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .conv-item:hover { background: rgba(255,255,255,0.03); }
    .conv-item.active { background: rgba(192, 57, 43, 0.1); border-left: 4px solid var(--red); }
    .conv-user-name { font-weight: 600; font-size: 0.9rem; }
    .conv-car { font-size: 0.75rem; color: var(--fg3); }
    .unread-dot {
        width: 8px; height: 8px; background: var(--red); border-radius: 50%;
    }

    .chat-main {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .chat-history {
        flex-grow: 1;
        padding: 1.5rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }
    .msg-bubble {
        max-width: 70%;
        padding: 0.7rem 1rem;
        border-radius: 12px;
        font-size: 0.85rem;
        position: relative;
    }
    .msg-bubble.sent { align-self: flex-end; background: var(--red); color: #fff; border-bottom-right-radius: 2px; }
    .msg-bubble.received { align-self: flex-start; background: #333; color: #eee; border-bottom-left-radius: 2px; }
    .msg-time { display: block; font-size: 0.65rem; margin-top: 4px; opacity: 0.6; }

    .chat-input-row {
        padding: 1rem;
        background: rgba(0,0,0,0.3);
        display: flex;
        gap: 10px;
        border-top: 1px solid var(--border-color);
    }
    .chat-input-row input {
        flex-grow: 1;
        background: #111;
        border: 1px solid #444;
        padding: 0.7rem 1rem;
        border-radius: 8px;
        color: #fff;
    }
</style>

<div class="admin-wrapper">
  <div class="main">
    <div class="topbar">
      <div class="topbar-left"><h1>ADMIN INBOX</h1><p>Centralized communication hub</p></div>
    </div>
    
    <div class="content">
      <div class="inbox-tabs">
        <button class="inbox-tab active" onclick="switchTab('live')">Live Messages (<?= $unreadLiveCount ?>+)</button>
        <button class="inbox-tab" onclick="switchTab('contacts')">App Recommendations (<?= count($contacts) ?>)</button>
        <button class="inbox-tab" onclick="switchTab('appeals')">Account Appeals (<?= count($appeals) ?>)</button>
      </div>

      <!-- Live Messages Section -->
      <div id="live-section" class="inbox-section active">
        <div class="live-chat-layout">
            <div class="conv-sidebar" id="convSidebar">
                <!-- Conversations load here -->
            </div>
            <div class="chat-main">
                <div class="chat-history" id="adminChatHistory">
                    <div style="text-align:center; padding-top:10rem; color:#555;">Select a conversation to start chatting</div>
                </div>
                <div class="chat-input-row" id="adminChatInputRow" style="display:none;">
                    <input type="text" id="adminChatInput" placeholder="Type a message...">
                    <button class="btn btn-red" id="adminSendBtn" style="padding: 0 1.5rem;">Send</button>
                </div>
            </div>
        </div>
      </div>

      <!-- General Contacts Section -->
      <div id="contacts-section" class="inbox-section">
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
let currentBookingId = null;
let liveInterval = null;

function switchTab(tab) {
    document.querySelectorAll('.inbox-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.inbox-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(tab + '-section').classList.add('active');
    event.currentTarget.classList.add('active');

    if (tab === 'live') {
        loadConversations();
        if (liveInterval) clearInterval(liveInterval);
        liveInterval = setInterval(() => {
            loadConversations();
            if (currentBookingId) loadAdminMessages();
        }, 3000);
    } else {
        clearInterval(liveInterval);
    }
}

function loadConversations() {
    fetch('../api/messages_action.php?action=counts')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const sidebar = document.getElementById('convSidebar');
                sidebar.innerHTML = data.conversations.map(c => `
                    <div class="conv-item ${currentBookingId == c.booking_id ? 'active' : ''}" onclick="selectConv(${c.booking_id})">
                        <div class="conv-info">
                            <div class="conv-user-name">${c.user_name} ${c.user_last_name}</div>
                            <div class="conv-car">${c.vehicle_model} (#${c.booking_id})</div>
                        </div>
                        ${c.unread_count > 0 ? `<span class="unread-badge">${c.unread_count}</span>` : ''}
                    </div>
                `).join('');
            }
        });
}

function selectConv(bookingId) {
    currentBookingId = bookingId;
    document.getElementById('adminChatInputRow').style.display = 'flex';
    loadAdminMessages();
    loadConversations(); // Refresh sidebar to clear unread badge locally
}

function loadAdminMessages() {
    if (!currentBookingId) return;
    fetch(`../api/messages_action.php?action=get&booking_id=${currentBookingId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('adminChatHistory');
                const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
                
                container.innerHTML = data.messages.map(m => `
                    <div class="msg-bubble ${m.sender_is_admin ? 'sent' : 'received'}">
                        ${m.message}
                        <span class="msg-time">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `).join('');
                
                if (atBottom) {
                    container.scrollTop = container.scrollHeight;
                }
            }
        });
}

document.getElementById('adminSendBtn')?.addEventListener('click', () => {
    const input = document.getElementById('adminChatInput');
    const msg = input.value.trim();
    if (!msg || !currentBookingId) return;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('booking_id', currentBookingId);
    formData.append('message', msg);
    
    fetch('../api/messages_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadAdminMessages();
        }
    });
});

// Initialize live tab on load if active
if (document.querySelector('.inbox-tab.active').innerText.includes('Live')) {
    loadConversations();
    liveInterval = setInterval(() => {
        loadConversations();
        if (currentBookingId) loadAdminMessages();
    }, 3000);
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
