<?php
require_once __DIR__ . '/includes/config.php';
td_require_login();

$user = td_current_user();
$role = $user['role'];
$store = td_load_store();
$priorities = $store['priorities'];
$statuses = $store['statuses'];

$statusFilter = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$view = $_GET['view'] ?? 'list';

$tickets = array_values(array_filter($store['tickets'], function ($t) use ($statusFilter, $priorityFilter) {
    if ($statusFilter !== 'all' && $t['status'] !== $statusFilter) return false;
    if ($priorityFilter !== 'all' && $t['priority'] !== $priorityFilter) return false;
    return true;
}));

$page_title = $role === 'admin' ? ($view === 'board' ? 'Board View' : 'All Tickets') : 'My Tickets';
$active_nav = $role === 'admin' ? ($view === 'board' ? 'kanban' : 'tickets') : 'my-tickets';

require __DIR__ . '/includes/layout_top.php';
?>

<form method="get" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:16px;">
  <div style="font-size:12.5px;color:var(--text-mid);font-weight:600;">Filter:</div>
  <select name="status" onchange="this.form.submit()" style="width:150px;">
    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
    <?php foreach ($statuses as $s): ?>
      <option value="<?= h($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= h($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="priority" onchange="this.form.submit()" style="width:150px;">
    <option value="all" <?= $priorityFilter === 'all' ? 'selected' : '' ?>>All priorities</option>
    <?php foreach (array_keys($priorities) as $p): ?>
      <option value="<?= h($p) ?>" <?= $priorityFilter === $p ? 'selected' : '' ?>><?= h($p) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($role === 'admin'): ?>
    <div style="margin-left:auto;display:flex;background:var(--bg-app);border-radius:10px;padding:3px;gap:2px;border:1px solid var(--border);">
      <a href="?status=<?= h($statusFilter) ?>&priority=<?= h($priorityFilter) ?>&view=list"
         class="btn <?= $view === 'list' ? 'btn-secondary' : 'btn-ghost' ?>" style="padding:6px 10px;">List</a>
      <a href="?status=<?= h($statusFilter) ?>&priority=<?= h($priorityFilter) ?>&view=board"
         class="btn <?= $view === 'board' ? 'btn-secondary' : 'btn-ghost' ?>" style="padding:6px 10px;">Board</a>
    </div>
  <?php endif; ?>
</form>

<?php if ($view === 'board' && $role === 'admin'):
    $cols = ['New', 'Assigned', 'In Progress', 'On Hold', 'Resolved'];
    ?>
    <div class="kanban-wrap">
      <?php foreach ($cols as $col):
            $colTickets = array_values(array_filter($tickets, fn($t) => $t['status'] === $col));
            $sc = td_status_colors($col);
      ?>
        <div class="kanban-col">
          <div class="kanban-col-head">
            <span style="width:8px;height:8px;border-radius:99px;background:<?= $sc['color'] ?>;"></span>
            <span style="font-size:13px;font-weight:700;"><?= h($col) ?></span>
            <span class="badge" style="background:var(--bg-app);color:var(--text-faint);margin-left:auto;"><?= count($colTickets) ?></span>
          </div>
          <div class="kanban-drop" data-status="<?= h($col) ?>">
            <?php if (empty($colTickets)): ?>
              <div class="kanban-empty">No tickets here</div>
            <?php endif; ?>
            <?php foreach ($colTickets as $t):
                  $pct = td_sla_pct($t, $priorities); $breached = td_sla_breached($t, $priorities);
                  $done = in_array($t['status'], ['Resolved', 'Closed'], true);
                  $pc = td_priority_colors($t['priority']);
                  $ac = $t['assignee'] ? td_avatar_colors($t['assignee']) : ['#EDECEA', '#8D8985'];
            ?>
              <div class="kanban-card" draggable="true" data-id="<?= (int) $t['id'] ?>" onclick="if(!window.__dragged) location='ticket.php?id=<?= (int) $t['id'] ?>'">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                  <span class="mono" style="font-size:10.5px;color:var(--text-faint);">#<?= (int) $t['id'] ?></span>
                  <?= td_sla_ring($pct, $breached, $done, 22, 3) ?>
                </div>
                <div style="font-size:13px;font-weight:600;color:var(--text-dark);margin-bottom:10px;line-height:1.35;"><?= h($t['subject']) ?></div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <span class="badge" style="background:<?= $pc['soft'] ?>;color:<?= $pc['color'] ?>;"><?= h($t['priority']) ?></span>
                  <div class="avatar" style="width:22px;height:22px;font-size:10px;background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;"><?= $t['assignee'] ? h(td_initials($t['assignee'])) : '' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <script>
    (function () {
      let dragId = null;
      document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', e => {
          dragId = card.dataset.id;
          window.__dragged = true;
          card.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
          card.classList.remove('dragging');
          setTimeout(() => { window.__dragged = false; }, 50);
        });
      });
      document.querySelectorAll('.kanban-drop').forEach(col => {
        col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', e => {
          e.preventDefault();
          col.classList.remove('drag-over');
          const status = col.dataset.status;
          if (!dragId) return;
          fetch('update_ticket_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(dragId) + '&status=' + encodeURIComponent(status)
          }).then(() => location.reload());
        });
      });
    })();
    </script>

<?php else: ?>
  <div class="card">
    <?php if (empty($tickets)): ?>
      <div style="padding:48px;text-align:center;color:var(--text-faint);font-size:13px;">No tickets match these filters.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Ticket</th><th>Priority</th><th>Status</th><th>Requester</th><th>Assignee</th><th>SLA</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $t):
                $pct = td_sla_pct($t, $priorities); $breached = td_sla_breached($t, $priorities);
                $done = in_array($t['status'], ['Resolved', 'Closed'], true);
                $pc = td_priority_colors($t['priority']); $sc = td_status_colors($t['status']);
                $ac = td_avatar_colors($t['requester']);
          ?>
          <tr class="ticket-row" onclick="window.location='ticket.php?id=<?= (int) $t['id'] ?>'">
            <td>
              <div style="font-weight:600;"><?= h($t['subject']) ?></div>
              <div class="mono" style="font-size:11px;color:var(--text-faint);margin-top:2px;">#<?= (int) $t['id'] ?> · <?= h($t['cat']) ?></div>
            </td>
            <td><span class="badge" style="background:<?= $pc['soft'] ?>;color:<?= $pc['color'] ?>;"><?= h($t['priority']) ?></span></td>
            <td><span class="badge" style="background:<?= $sc['soft'] ?>;color:<?= $sc['color'] ?>;"><?= h($t['status']) ?></span></td>
            <td>
              <div style="display:flex;align-items:center;gap:7px;">
                <div class="avatar" style="width:22px;height:22px;font-size:10px;background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;"><?= h(td_initials($t['requester'])) ?></div>
                <span style="font-size:12.5px;"><?= h($t['requester']) ?></span>
              </div>
            </td>
            <td>
              <?php if ($t['assignee']): $ac2 = td_avatar_colors($t['assignee']); ?>
                <div style="display:flex;align-items:center;gap:7px;">
                  <div class="avatar" style="width:22px;height:22px;font-size:10px;background:<?= $ac2[0] ?>;color:<?= $ac2[1] ?>;"><?= h(td_initials($t['assignee'])) ?></div>
                  <span style="font-size:12.5px;"><?= h($t['assignee']) ?></span>
                </div>
              <?php else: ?>
                <span style="font-size:12.5px;color:var(--text-faint);">Unassigned</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <?= td_sla_ring($pct, $breached, $done, 26, 3) ?>
                <span class="mono" style="font-size:11px;color:<?= $breached ? 'var(--danger)' : ($done ? 'var(--success)' : 'var(--text-faint)') ?>;">
                  <?= $done ? h($t['status']) : ($breached ? 'Overdue' : round($pct) . '%') ?>
                </span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
