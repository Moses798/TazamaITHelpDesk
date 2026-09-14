<?php
require_once __DIR__ . '/includes/config.php';
td_require_login();

$user = td_current_user();
$store = td_load_store();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $dept = $_POST['dept'] ?? $store['departments'][0];
    $cat = $_POST['cat'] ?? $store['categories'][0];
    $priority = $_POST['priority'] ?? 'Medium';
    $desc = trim($_POST['desc'] ?? '');

    if ($subject === '') {
        $error = 'Please enter a subject for the issue.';
    } else {
        $newId = td_next_ticket_id($store);
        $store['tickets'][] = [
            'id' => $newId, 'subject' => $subject, 'dept' => $dept, 'cat' => $cat, 'priority' => $priority,
            'status' => 'New', 'requester' => $user['name'], 'assignee' => null,
            'created' => time(), 'desc' => $desc !== '' ? $desc : 'No additional details provided.',
            'closed_at' => null,
            'history' => [['who' => $user['name'], 'role' => $user['role'], 'action' => 'created this ticket', 'at' => time()]],
        ];
        td_save_store($store);
        header('Location: ticket.php?id=' . $newId);
        exit;
    }
}

$page_title = 'Report an Issue';
$active_nav = $user['role'] === 'admin' ? 'tickets' : 'new-ticket';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card" style="max-width:640px;margin:0 auto;">
  <div style="padding:20px 26px;border-bottom:1px solid var(--border);">
    <div class="display" style="font-size:17px;font-weight:700;">Report an issue</div>
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
        <select name="dept">
          <?php foreach ($store['departments'] as $d): ?><option value="<?= h($d) ?>"><?= h($d) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="field-label">Category</label>
        <select name="cat">
          <?php foreach ($store['categories'] as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="field-label">Priority</label>
      <div style="display:flex;gap:8px;">
        <?php foreach (array_keys($store['priorities']) as $i => $p): ?>
          <label class="priority-btn <?= $i === 2 ? 'selected' : '' ?>" style="display:flex;align-items:center;justify-content:center;">
            <input type="radio" name="priority" value="<?= h($p) ?>" <?= $i === 2 ? 'checked' : '' ?> style="width:auto;margin-right:6px;">
            <?= h($p) ?>
          </label>
        <?php endforeach; ?>
      </div>
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

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
