<?php
require_once __DIR__ . '/includes/config.php';
td_require_login();

$user = td_current_user();
$store = td_load_store();
$error = '';

/* ── Priority Matrix ──────────────────────────────────────────────────
 * Department × Category → Priority (server-side enforcement).
 * Client-supplied priority values are IGNORED.
 * Keys must exactly match the values stored in data/store.json.
 * ─────────────────────────────────────────────────────────────────── */
$priorityMatrix = [
    'Administration' => [
        'Hardware'       => 'Medium',
        'Software'       => 'Medium',
        'Network'        => 'Medium',
        'Account Access' => 'Medium',
        'Email'          => 'Medium',
        'Printer'        => 'Low',
        'Security'       => 'High',
    ],
    'Finance' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'Critical',
        'Account Access' => 'High',
        'Email'          => 'High',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Human Resources' => [
        'Hardware'       => 'Medium',
        'Software'       => 'Medium',
        'Network'        => 'High',
        'Account Access' => 'High',
        'Email'          => 'High',
        'Printer'        => 'Low',
        'Security'       => 'Critical',
    ],
    'Security' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'Critical',
        'Account Access' => 'Critical',
        'Email'          => 'High',
        'Printer'        => 'Low',
        'Security'       => 'Critical',
    ],
    'Operations' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'Critical',
        'Account Access' => 'High',
        'Email'          => 'High',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Maintenance' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'High',
        'Account Access' => 'Medium',
        'Email'          => 'Medium',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Boomgate' => [
        'Hardware'       => 'Critical',
        'Software'       => 'Critical',
        'Network'        => 'Critical',
        'Account Access' => 'Critical',
        'Email'          => 'High',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Commercial' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'Critical',
        'Account Access' => 'High',
        'Email'          => 'High',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Dispatch' => [
        'Hardware'       => 'Critical',
        'Software'       => 'Critical',
        'Network'        => 'Critical',
        'Account Access' => 'Critical',
        'Email'          => 'High',
        'Printer'        => 'High',
        'Security'       => 'Critical',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $dept    = $_POST['dept'] ?? $store['departments'][0];
    $cat     = $_POST['cat']  ?? $store['categories'][0];
    $desc    = trim($_POST['desc'] ?? '');

    // Server-side priority: always calculated from matrix, never from client input
    $priority = isset($priorityMatrix[$dept][$cat])
        ? $priorityMatrix[$dept][$cat]
        : 'Medium'; // safe fallback

    if ($subject === '') {
        $error = 'Please enter a subject for the issue.';
    } else {
        $newId = td_next_ticket_id($store);
        $store['tickets'][] = [
            'id'       => $newId,
            'subject'  => $subject,
            'dept'     => $dept,
            'cat'      => $cat,
            'priority' => $priority,
            'status'   => 'New',
            'requester'=> $user['name'],
            'assignee' => null,
            'created'  => time(),
            'desc'     => $desc !== '' ? $desc : 'No additional details provided.',
            'closed_at'=> null,
            'history'  => [['who' => $user['name'], 'role' => $user['role'], 'action' => 'created this ticket', 'at' => time()]],
        ];
        td_save_store($store);
        header('Location: ticket.php?id=' . $newId);
        exit;
    }
}

$page_title = 'Raise a Ticket';
$active_nav = $user['role'] === 'admin' ? 'tickets' : 'new-ticket';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card" style="max-width:640px;margin:0 auto;">
  <div style="padding:20px 26px;border-bottom:1px solid var(--border);">
    <div class="display" style="font-size:17px;font-weight:700;">Raise a Ticket</div>
    <div style="font-size:12.5px;color:var(--text-mid);margin-top:2px;">Tell us what's going wrong — we'll route it to the right team.</div>
  </div>
  <form method="post" action="new_ticket.php" style="padding:26px;display:flex;flex-direction:column;gap:16px;">
    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>

    <div>
      <label class="field-label">Subject</label>
      <input type="text" name="subject" placeholder="e.g. Cannot connect to office Wi-Fi" value="<?= h($_POST['subject'] ?? '') ?>" required>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <label class="field-label">Department</label>
        <select name="dept" id="dept-select">
          <?php foreach ($store['departments'] as $d): ?>
            <option value="<?= h($d) ?>"><?= h($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="field-label">Category</label>
        <select name="cat" id="cat-select">
          <?php foreach ($store['categories'] as $c): ?>
            <option value="<?= h($c) ?>"><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Auto-calculated priority display — read-only, no user interaction -->
    <div id="priority-display">
      <label class="field-label">Priority</label>
      <div id="priority-indicator" style="
        display:inline-flex;align-items:center;gap:8px;
        padding:8px 16px;border-radius:8px;
        font-size:14px;font-weight:600;
        background:#f4f3f1;color:#5C5854;
        border:1.5px solid #e4e3e0;
        margin-top:2px;
        min-width:160px;
        user-select:none;
        pointer-events:none;
      ">
        Select department and category to determine priority.
      </div>
      <!-- Hidden field carries the calculated priority to the backend -->
      <input type="hidden" name="priority" id="priority-value" value="">
      <p id="priority-description" style="
        margin:6px 0 0;
        font-size:12px;
        color:var(--text-mid);
        line-height:1.5;
      "></p>
    </div>

    <div>
      <label class="field-label">Description</label>
      <textarea name="desc" rows="4" placeholder="Describe the issue, what you expected, and any error messages..."><?= h($_POST['desc'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px;">
      <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-primary">Submit ticket</button>
    </div>
  </form>
</div>

<script>
// Priority matrix (must mirror the PHP array above)
var PriorityMatrix = {
  'Administration': {
    'Hardware': 'Medium', 'Software': 'Medium', 'Network': 'Medium',
    'Account Access': 'Medium', 'Email': 'Medium', 'Printer': 'Low', 'Security': 'High'
  },
  'Finance': {
    'Hardware': 'High', 'Software': 'High', 'Network': 'Critical',
    'Account Access': 'High', 'Email': 'High', 'Printer': 'Medium', 'Security': 'Critical'
  },
  'Human Resources': {
    'Hardware': 'Medium', 'Software': 'Medium', 'Network': 'High',
    'Account Access': 'High', 'Email': 'High', 'Printer': 'Low', 'Security': 'Critical'
  },
  'Security': {
    'Hardware': 'High', 'Software': 'High', 'Network': 'Critical',
    'Account Access': 'Critical', 'Email': 'High', 'Printer': 'Low', 'Security': 'Critical'
  },
  'Operations': {
    'Hardware': 'High', 'Software': 'High', 'Network': 'Critical',
    'Account Access': 'High', 'Email': 'High', 'Printer': 'Medium', 'Security': 'Critical'
  },
  'Maintenance': {
    'Hardware': 'High', 'Software': 'High', 'Network': 'High',
    'Account Access': 'Medium', 'Email': 'Medium', 'Printer': 'Medium', 'Security': 'Critical'
  },
  'Boomgate': {
    'Hardware': 'Critical', 'Software': 'Critical', 'Network': 'Critical',
    'Account Access': 'Critical', 'Email': 'High', 'Printer': 'Medium', 'Security': 'Critical'
  },
  'Commercial': {
    'Hardware': 'High', 'Software': 'High', 'Network': 'Critical',
    'Account Access': 'High', 'Email': 'High', 'Printer': 'Medium', 'Security': 'Critical'
  },
  'Dispatch': {
    'Hardware': 'Critical', 'Software': 'Critical', 'Network': 'Critical',
    'Account Access': 'Critical', 'Email': 'High', 'Printer': 'High', 'Security': 'Critical'
  }
};

var PriorityColors = {
  'Critical': { bg: '#FEE2E2', color: '#dc2626', border: '#fca5a5', dot: '🔴' },
  'High':     { bg: '#FFEDD5', color: '#ea580c', border: '#fdba74', dot: '🟠' },
  'Medium':   { bg: '#FEF9C3', color: '#ca8a04', border: '#fde047', dot: '🟡' },
  'Low':      { bg: '#DCFCE7', color: '#16a34a', border: '#86efac', dot: '🟢' }
};

var PriorityDescriptions = {
  'Critical': 'Immediate operational, financial, security, access-control, or core-system impact. A Critical issue may significantly interrupt essential business operations and requires immediate attention.',
  'High':     'Significant disruption to an important business function, but operations can continue temporarily. A High-priority issue affects an important business activity and should be addressed promptly.',
  'Medium':   'Individual or team productivity impact with a reasonable workaround available. A Medium-priority issue affects normal work but does not normally stop critical business operations.',
  'Low':      'Minor inconvenience or issue involving a non-essential service, device, or function. A Low-priority issue has limited operational impact and can normally be addressed during routine support work.'
};

function updatePriority() {
  var dept = document.getElementById('dept-select').value;
  var cat  = document.getElementById('cat-select').value;
  var indicator = document.getElementById('priority-indicator');
  var descEl    = document.getElementById('priority-description');
  var hidden    = document.getElementById('priority-value');

  if (!dept || !cat || !PriorityMatrix[dept] || !PriorityMatrix[dept][cat]) {
    indicator.style.background = '#f4f3f1';
    indicator.style.color = '#5C5854';
    indicator.style.borderColor = '#e4e3e0';
    indicator.textContent = 'Select department and category to determine priority.';
    indicator.style.minWidth = '160px';
    descEl.textContent = '';
    hidden.value = '';
    return;
  }

  var priority = PriorityMatrix[dept][cat];
  var scheme   = PriorityColors[priority];

  indicator.style.background   = scheme.bg;
  indicator.style.color        = scheme.color;
  indicator.style.borderColor  = scheme.border;
  indicator.style.minWidth     = '120px';
  indicator.textContent        = scheme.dot + '  ' + priority;

  descEl.textContent = PriorityDescriptions[priority];
  hidden.value = priority;
}

document.getElementById('dept-select').addEventListener('change', updatePriority);
document.getElementById('cat-select').addEventListener('change', updatePriority);

// Run once on page load so initial selections show a priority
updatePriority();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
