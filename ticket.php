<?php
require_once __DIR__ . '/includes/config.php';
td_require_login();

$user = td_current_user();
$role = $user['role'];
$id = (int) ($_GET['id'] ?? 0);

$store = td_load_store();
$priorities = $store['priorities'];
$statuses = $store['statuses'];
$agents = $store['agents'];

$idx = td_find_ticket_index($store, $id);
if ($idx === null || ($role === 'employee' && ($store['tickets'][$idx]['requester'] ?? '') !== ($user['name'] ?? ''))) {
    header('Location: tickets.php');
    exit;
}

$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'message') {
        $text = trim($_POST['text'] ?? '');
        if ($text !== '') {
            $store['tickets'][$idx]['history'][] = [
                'who' => $user['name'], 'role' => $role, 'action' => 'sent a message',
                'text' => $text, 'is_message' => true,
                'seen_by_admin' => $role !== 'employee',
                'seen_by_requester' => $role === 'employee',
                'at' => time(),
            ];
            td_save_store($store);
        }
        header('Location: ticket.php?id=' . $id);
        exit;
    }

    if ($role === 'admin' && $store['tickets'][$idx]['status'] !== 'Closed') {
        if ($action === 'assign') {
            $assignee = trim($_POST['assignee'] ?? '');
            $wasUnassigned = empty($store['tickets'][$idx]['assignee']);
            $store['tickets'][$idx]['assignee'] = $assignee !== '' ? $assignee : null;
            if ($wasUnassigned && $assignee !== '' && $store['tickets'][$idx]['status'] === 'New') {
                $store['tickets'][$idx]['status'] = 'Assigned';
            }
            $store['tickets'][$idx]['history'][] = [
                'who' => $user['name'], 'role' => 'admin', 'at' => time(),
                'action' => $assignee !== '' ? "assigned this ticket to {$assignee}" : 'unassigned this ticket',
            ];
            td_save_store($store);
            header('Location: ticket.php?id=' . $id);
            exit;
        }

        if ($action === 'status') {
            $newStatus = $_POST['status'] ?? '';
            if (in_array($newStatus, $statuses, true) && $newStatus !== 'Closed') {
                $store['tickets'][$idx]['status'] = $newStatus;
                $store['tickets'][$idx]['history'][] = ['who' => $user['name'], 'role' => 'admin', 'at' => time(), 'action' => "changed status to {$newStatus}"];
                td_save_store($store);
            }
            header('Location: ticket.php?id=' . $id);
            exit;
        }

        if ($action === 'close') {
            $note = trim($_POST['resolution_note'] ?? '');
            if ($note === '') {
                $formError = 'Add a short resolution summary before closing.';
            } else {
                $store['tickets'][$idx]['status'] = 'Closed';
                $store['tickets'][$idx]['closed_at'] = time();
                $store['tickets'][$idx]['closed_by'] = $user['name'];
                $store['tickets'][$idx]['resolution_note'] = $note;
                $store['tickets'][$idx]['history'][] = ['who' => $user['name'], 'role' => 'admin', 'at' => time(), 'action' => 'closed this ticket', 'note' => $note];
                td_save_store($store);
                header('Location: ticket.php?id=' . $id);
                exit;
            }
        }
    }

    if ($role === 'admin' && $action === 'reopen') {
        $store['tickets'][$idx]['status'] = 'In Progress';
        $store['tickets'][$idx]['closed_at'] = null;
        $store['tickets'][$idx]['closed_by'] = null;
        $store['tickets'][$idx]['resolution_note'] = null;
        $store['tickets'][$idx]['history'][] = ['who' => $user['name'], 'role' => 'admin', 'at' => time(), 'action' => 'reopened this ticket'];
        td_save_store($store);
        header('Location: ticket.php?id=' . $id);
        exit;
    }
}

/* Mark employee messages as seen once the admin views this ticket. */
if ($role === 'admin') {
    $changed = false;
    foreach ($store['tickets'][$idx]['history'] as &$hh) {
        if (!empty($hh['is_message']) && ($hh['role'] ?? '') === 'employee' && empty($hh['seen_by_admin'])) {
            $hh['seen_by_admin'] = true;
            $changed = true;
        }
    }
    unset($hh);
    if ($changed) td_save_store($store);
} else {
    $changed = false;
    foreach ($store['tickets'][$idx]['history'] as &$hh) {
        if (!empty($hh['is_message']) && ($hh['role'] ?? '') === 'admin' && empty($hh['seen_by_requester'])) {
            $hh['seen_by_requester'] = true;
            $changed = true;
        }
    }
    unset($hh);
    if ($changed) td_save_store($store);
}

$ticket = $store['tickets'][$idx];

$pct = td_sla_pct($ticket, $priorities);
$breached = td_sla_breached($ticket, $priorities);
$isClosed = $ticket['status'] === 'Closed';
$isDone = in_array($ticket['status'], ['Resolved', 'Closed'], true);
$pc = td_priority_colors($ticket['priority']);
$sc = td_status_colors($ticket['status']);

$steps = ['New', 'Assigned', 'In Progress', 'Resolved', 'Closed'];
$effectiveStatus = $ticket['status'] === 'On Hold' ? 'In Progress' : $ticket['status'];
$currentIdx = array_search($effectiveStatus, $steps, true);
if ($currentIdx === false) $currentIdx = 0;

$history = $ticket['history'];
usort($history, fn($a, $b) => $b['at'] <=> $a['at']);

$page_title = 'Ticket #' . $ticket['id'];
$active_nav = $role === 'admin' ? 'tickets' : 'my-tickets';

require __DIR__ . '/includes/layout_top.php';
?>

<div class="side-panel-overlay" onclick="window.location='<?= $role === 'admin' ? 'tickets.php' : 'tickets.php' ?>'"></div>
<div class="side-panel" onclick="event.stopPropagation()">
  <div class="side-panel-head">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
      <div>
        <div class="mono" style="font-size:12px;color:var(--text-faint);margin-bottom:4px;">TICKET-<?= (int) $ticket['id'] ?></div>
        <div class="display" style="font-size:16.5px;font-weight:700;line-height:1.3;"><?= h($ticket['subject']) ?></div>
      </div>
      <a href="tickets.php" style="border:none;background:var(--bg-app);border-radius:10px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-dark);">&times;</a>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
      <span class="badge" style="background:<?= $pc['soft'] ?>;color:<?= $pc['color'] ?>;"><?= h($ticket['priority']) ?></span>
      <span class="badge" style="background:<?= $sc['soft'] ?>;color:<?= $sc['color'] ?>;"><?= h($ticket['status']) ?></span>
      <span class="badge" style="background:var(--bg-app);color:var(--text-mid);"><?= h($ticket['cat']) ?></span>
    </div>
  </div>

  <div class="side-panel-body">

    <?php if ($isClosed): ?>
      <div style="display:flex;gap:12px;align-items:flex-start;background:var(--success-soft);border:1px solid rgba(18,135,90,.2);border-radius:14px;padding:14px;">
        <div style="font-size:20px;color:var(--success);line-height:1;">&#10003;</div>
        <div style="flex:1;">
          <div style="font-size:13px;font-weight:700;color:var(--success);">Ticket closed<?= $ticket['closed_by'] ? ' by ' . h($ticket['closed_by']) : '' ?></div>
          <?php if ($ticket['closed_at']): ?><div style="font-size:11.5px;color:var(--text-faint);margin-top:2px;"><?= td_time_ago_label($ticket['closed_at']) ?></div><?php endif; ?>
          <?php if ($ticket['resolution_note']): ?><div style="font-size:12.5px;color:var(--text-dark);margin-top:8px;line-height:1.5;"><?= h($ticket['resolution_note']) ?></div><?php endif; ?>
          <?php if ($role === 'admin'): ?>
            <form method="post" action="ticket.php?id=<?= (int) $ticket['id'] ?>" style="margin-top:12px;">
              <input type="hidden" name="action" value="reopen">
              <button type="submit" class="btn btn-secondary" style="padding:7px 12px;font-size:12.5px;">&#8635; Reopen ticket</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:14px;background:var(--bg-app);border-radius:14px;padding:14px;">
      <?= td_sla_ring($pct, $breached, $isDone, 48, 5) ?>
      <div>
        <div style="font-size:12.5px;font-weight:600;color:<?= $isDone ? 'var(--success)' : ($breached ? 'var(--danger)' : 'var(--text-dark)') ?>;">
          <?php if ($isDone): ?>
            <?= $ticket['status'] === 'Closed' ? 'Ticket closed' : 'Ticket resolved' ?>
          <?php else: ?>
            <?= $breached ? 'SLA breached' : round($pct) . '% of SLA window remaining' ?>
          <?php endif; ?>
        </div>
        <div style="font-size:11.5px;color:var(--text-faint);margin-top:2px;">
          Target resolution: <span class="mono"><?= $priorities[$ticket['priority']]['hours'] ?>h</span> · opened <?= td_time_ago_label($ticket['created']) ?>
        </div>
      </div>
    </div>

    <div class="stepper">
      <?php foreach ($steps as $i => $s):
            $complete = $i < $currentIdx || ($i === $currentIdx && $ticket['status'] === 'Closed');
            $active = $i <= $currentIdx;
      ?>
        <div class="step">
          <?php if ($i > 0): ?><div class="line <?= $active ? 'done' : '' ?>"></div><?php endif; ?>
          <div class="dot <?= $active ? 'done' : '' ?>"><?= $complete ? '&#10003;' : ($i === $currentIdx ? '&bull;' : '') ?></div>
          <div class="label <?= $active ? 'done' : '' ?>"><?= h($s) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div>
      <div style="font-size:12px;font-weight:700;color:var(--text-mid);letter-spacing:.4px;margin-bottom:8px;">DESCRIPTION</div>
      <div style="font-size:13.5px;color:var(--text-dark);line-height:1.6;background:var(--bg-app);padding:14px;border-radius:12px;"><?= nl2br(h($ticket['desc'])) ?></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <div style="font-size:11.5px;color:var(--text-faint);font-weight:600;margin-bottom:6px;">REQUESTER</div>
        <?php $rac = td_avatar_colors($ticket['requester']); ?>
        <div style="display:flex;align-items:center;gap:8px;">
          <div class="avatar" style="width:26px;height:26px;font-size:11px;background:<?= $rac[0] ?>;color:<?= $rac[1] ?>;"><?= h(td_initials($ticket['requester'])) ?></div>
          <span style="font-size:13px;"><?= h($ticket['requester']) ?></span>
        </div>
      </div>
      <div>
        <div style="font-size:11.5px;color:var(--text-faint);font-weight:600;margin-bottom:6px;">DEPARTMENT</div>
        <div style="font-size:13px;"><?= h($ticket['dept']) ?></div>
      </div>
    </div>

    <?php if ($role === 'admin' && !$isClosed): ?>
      <div style="border-top:1px solid var(--border);padding-top:18px;">
        <div style="font-size:12px;font-weight:700;color:var(--text-mid);letter-spacing:.4px;margin-bottom:10px;">ADMIN CONTROLS</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <form method="post" action="ticket.php?id=<?= (int) $ticket['id'] ?>">
            <input type="hidden" name="action" value="assign">
            <label class="field-label">Assigned to</label>
            <select name="assignee" onchange="this.form.submit()">
              <option value="">Unassigned</option>
              <?php foreach ($agents as $a): ?>
                <option value="<?= h($a) ?>" <?= $ticket['assignee'] === $a ? 'selected' : '' ?>><?= h($a) ?></option>
              <?php endforeach; ?>
            </select>
          </form>

          <form method="post" action="ticket.php?id=<?= (int) $ticket['id'] ?>" id="statusForm">
            <input type="hidden" name="action" value="status">
            <label class="field-label">Status</label>
            <select name="status" id="statusSelect" onchange="handleStatusChange(this)">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= h($s) ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>

        <div id="closePanel" style="display:none;margin-top:14px;padding:14px;border-radius:12px;background:var(--danger-soft);border:1px solid rgba(168,40,31,.2);">
          <div style="display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;color:var(--danger);margin-bottom:8px;">&#9888; Closing this ticket</div>
          <div style="font-size:12px;color:var(--text-mid);margin-bottom:10px;line-height:1.45;">Add a short resolution summary — this will be visible to the requester.</div>
          <form method="post" action="ticket.php?id=<?= (int) $ticket['id'] ?>">
            <input type="hidden" name="action" value="close">
            <textarea name="resolution_note" rows="3" placeholder="e.g. Replaced the faulty network cable and confirmed the connection is stable." style="margin-bottom:10px;"><?= h($_POST['resolution_note'] ?? '') ?></textarea>
            <?php if ($formError): ?><div class="flash flash-error"><?= h($formError) ?></div><?php endif; ?>
            <div style="display:flex;gap:8px;">
              <button type="button" class="btn btn-secondary" style="flex:1;justify-content:center;" onclick="cancelClose()">Cancel</button>
              <button type="submit" class="btn" style="flex:1;justify-content:center;background:var(--danger);color:#fff;">Confirm &amp; close ticket</button>
            </div>
          </form>
        </div>

        <script>
        function handleStatusChange(sel) {
          if (sel.value === 'Closed') {
            document.getElementById('closePanel').style.display = 'block';
          } else {
            document.getElementById('statusForm').submit();
          }
        }
        function cancelClose() {
          document.getElementById('closePanel').style.display = 'none';
          document.getElementById('statusSelect').value = '<?= h($ticket['status']) ?>';
        }
        <?php if ($formError): ?>
        document.getElementById('closePanel').style.display = 'block';
        <?php endif; ?>
        </script>
      </div>
    <?php endif; ?>

    <div style="border-top:1px solid var(--border);padding-top:18px;">
      <div style="font-size:12px;font-weight:700;color:var(--text-mid);letter-spacing:.4px;margin-bottom:10px;">ACTIVITY</div>
      <div style="display:flex;flex-direction:column;gap:14px;font-size:12.5px;">
        <?php foreach ($history as $hh):
              $fromEmployee = ($hh['role'] ?? '') === 'employee';
              $hac = td_avatar_colors($hh['who'] ?? '');
        ?>
          <?php if (!empty($hh['is_message'])): ?>
            <div style="display:flex;gap:10px;">
              <div class="avatar" style="width:26px;height:26px;font-size:11px;background:<?= $hac[0] ?>;color:<?= $hac[1] ?>;flex-shrink:0;"><?= h(td_initials($hh['who'] ?? '')) ?></div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:baseline;gap:6px;">
                  <span style="font-weight:600;font-size:12.5px;"><?= h($hh['who'] ?? 'Unknown') ?></span>
                  <?php if ($fromEmployee): ?><span class="badge" style="background:var(--info-soft);color:var(--info);font-size:10px;">Employee</span><?php endif; ?>
                  <span style="color:var(--text-faint);font-size:10.5px;"><?= td_time_ago_label($hh['at']) ?></span>
                </div>
                <div class="chat-bubble <?= $fromEmployee ? 'employee' : 'admin' ?>" style="margin-top:5px;"><?= h($hh['text'] ?? '') ?></div>
              </div>
            </div>
          <?php else: ?>
            <div style="display:flex;gap:10px;">
              <div style="color:var(--text-faint);margin-top:2px;flex-shrink:0;">&#9679;</div>
              <div>
                <span style="font-weight:600;"><?= h($hh['who'] ?? 'System') ?></span>
                <span style="color:var(--text-mid);"> <?= h($hh['action'] ?? '') ?><?= isset($hh['note']) ? ' — "' . h($hh['note']) . '"' : '' ?></span>
                <div style="color:var(--text-faint);font-size:11px;margin-top:1px;"><?= td_time_ago_label($hh['at']) ?></div>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <form method="post" action="ticket.php?id=<?= (int) $ticket['id'] ?>" style="display:flex;gap:8px;margin-top:16px;">
          <input type="hidden" name="action" value="message">
          <input type="text" name="text" placeholder="<?= $role === 'employee' ? 'Message IT about this issue...' : 'Message the requester...' ?>" required>
          <button type="submit" class="btn btn-primary" style="padding:9px 12px;">&#9658;</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
