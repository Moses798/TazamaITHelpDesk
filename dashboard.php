<?php
require_once __DIR__ . '/includes/config.php';
td_require_login();

$user = td_current_user();
$role = $user['role'];
$store = td_load_store();
$tickets = $store['tickets'];
$priorities = $store['priorities'];

$page_title = 'Dashboard';
$active_nav = 'dashboard';

function td_render_ticket_rows($rows, $priorities, $show_assignee = true) {
    if (empty($rows)) {
        echo '<div style="padding:48px;text-align:center;color:var(--text-faint);font-size:13px;">No tickets to show.</div>';
        return;
    }
    ?>
    <table>
      <thead>
        <tr>
          <th>Ticket</th><th>Priority</th><th>Status</th><th>Requester</th>
          <th><?= $show_assignee ? 'Assignee' : 'Department' ?></th><th>SLA</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $t):
              $pct = td_sla_pct($t, $priorities);
              $breached = td_sla_breached($t, $priorities);
              $done = in_array($t['status'], ['Resolved', 'Closed'], true);
              $pc = td_priority_colors($t['priority']);
              $sc = td_status_colors($t['status']);
        ?>
        <tr class="ticket-row" onclick="window.location='ticket.php?id=<?= (int) $t['id'] ?>'">
          <td>
            <div style="font-weight:600;color:var(--text-dark);"><?= h($t['subject']) ?></div>
            <div class="mono" style="font-size:11px;color:var(--text-faint);margin-top:2px;">#<?= (int) $t['id'] ?> · <?= h($t['cat']) ?></div>
          </td>
          <td><span class="badge" style="background:<?= $pc['soft'] ?>;color:<?= $pc['color'] ?>;"><?= h($t['priority']) ?></span></td>
          <td><span class="badge" style="background:<?= $sc['soft'] ?>;color:<?= $sc['color'] ?>;"><?= h($t['status']) ?></span></td>
          <td>
            <?php $ac = td_avatar_colors($t['requester']); ?>
            <div style="display:flex;align-items:center;gap:7px;">
              <div class="avatar" style="width:22px;height:22px;font-size:10px;background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;"><?= h(td_initials($t['requester'])) ?></div>
              <span style="font-size:12.5px;"><?= h($t['requester']) ?></span>
            </div>
          </td>
          <td>
            <?php if ($show_assignee): ?>
              <?php if ($t['assignee']): $ac2 = td_avatar_colors($t['assignee']); ?>
                <div style="display:flex;align-items:center;gap:7px;">
                  <div class="avatar" style="width:22px;height:22px;font-size:10px;background:<?= $ac2[0] ?>;color:<?= $ac2[1] ?>;"><?= h(td_initials($t['assignee'])) ?></div>
                  <span style="font-size:12.5px;"><?= h($t['assignee']) ?></span>
                </div>
              <?php else: ?>
                <span style="font-size:12.5px;color:var(--text-faint);">Unassigned</span>
              <?php endif; ?>
            <?php else: ?>
              <span style="font-size:12.5px;color:var(--text-mid);"><?= h($t['dept']) ?></span>
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
    <?php
}

require __DIR__ . '/includes/layout_top.php';

if ($role === 'employee'):
    $mine = array_slice($tickets, 0, 6);
    $open = count(array_filter($mine, fn($t) => !in_array($t['status'], ['Resolved', 'Closed'], true)));
    $resolved = count(array_filter($mine, fn($t) => $t['status'] === 'Resolved'));
    $closed = count(array_filter($mine, fn($t) => $t['status'] === 'Closed'));
    $firstName = explode(' ', $user['name'])[0];
    ?>
    <div class="hero">
      <div>
        <div class="display" style="font-size:21px;font-weight:700;margin-bottom:6px;">Hi <?= h($firstName) ?>, how can we help?</div>
        <div style="opacity:.85;font-size:13.5px;">Report a new issue and our team will pick it up right away.</div>
      </div>
      <a href="#report-issue" class="btn" style="background:#fff;color:var(--brand-dark);font-weight:700;" onclick="openReportIssue(event)">+ Report an issue</a>
    </div>

    <div class="grid-stats" style="margin-bottom:24px;">
      <div class="card card-pad"><div class="stat-label">Open tickets</div><div class="stat-value"><?= $open ?></div></div>
      <div class="card card-pad"><div class="stat-label">Resolved</div><div class="stat-value"><?= $resolved ?></div></div>
      <div class="card card-pad"><div class="stat-label">Closed this month</div><div class="stat-value"><?= $closed ?></div></div>
      <div class="card card-pad"><div class="stat-label">Avg. response time</div><div class="stat-value">3.2h</div></div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <div class="display" style="font-size:15.5px;font-weight:700;">Your recent tickets</div>
      <a href="tickets.php" style="font-size:12.5px;color:var(--brand);font-weight:600;">View all &rarr;</a>
    </div>
    <div class="card"><?php td_render_ticket_rows($mine, $priorities, true) ?></div>

    <div id="report-issue" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="report-issue-title" onclick="closeReportIssueOnOverlay(event)">
      <div class="modal-box">
        <div class="modal-head">
          <div>
            <div id="report-issue-title" class="display" style="font-size:17px;font-weight:700;">Report an issue</div>
            <div style="font-size:12.5px;color:var(--text-mid);margin-top:2px;">Tell us what's going wrong — we'll route it to the right team.</div>
          </div>
          <button type="button" class="btn btn-ghost" aria-label="Close report issue form" onclick="closeReportIssue()" style="font-size:22px;padding:4px 8px;">&times;</button>
        </div>
        <form method="post" action="new_ticket.php" class="modal-body">
          <div>
            <label class="field-label" for="report-subject">Subject</label>
            <input id="report-subject" type="text" name="subject" placeholder="e.g. Cannot connect to office Wi-Fi" required>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
              <label class="field-label" for="report-dept">Department</label>
              <select id="report-dept" name="dept">
                <?php foreach ($store['departments'] as $d): ?><option value="<?= h($d) ?>"><?= h($d) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="field-label" for="report-cat">Category</label>
              <select id="report-cat" name="cat">
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
            <label class="field-label" for="report-desc">Description</label>
            <textarea id="report-desc" name="desc" rows="4" placeholder="Describe the issue, what you expected, and any error messages..."></textarea>
          </div>

          <div class="modal-foot" style="margin:0 -26px -26px;">
            <button type="button" class="btn btn-ghost" onclick="closeReportIssue()">Cancel</button>
            <button type="submit" class="btn btn-primary">Submit ticket</button>
          </div>
        </form>
      </div>
    </div>

<?php else: /* admin */
    $open = count(array_filter($tickets, fn($t) => !in_array($t['status'], ['Resolved', 'Closed'], true)));
    $unassigned = count(array_filter($tickets, fn($t) => empty($t['assignee']) && !in_array($t['status'], ['Resolved', 'Closed'], true)));
    $breaching = count(array_filter($tickets, fn($t) => td_sla_breached($t, $priorities)));
    $resolvedToday = count(array_filter($tickets, fn($t) => $t['status'] === 'Resolved'));

    $byDept = [];
    foreach ($tickets as $t) $byDept[$t['dept']] = ($byDept[$t['dept']] ?? 0) + 1;
    arsort($byDept);
    $maxDept = max(array_values($byDept) ?: [1]);

    $needsAttention = array_values(array_filter($tickets, fn($t) => td_sla_breached($t, $priorities) || empty($t['assignee'])));
    $needsAttention = array_slice($needsAttention, 0, 6);
    ?>
    <div class="grid-stats" style="margin-bottom:22px;">
      <div class="card card-pad"><div class="stat-label">Open tickets</div><div class="stat-value"><?= $open ?></div></div>
      <div class="card card-pad"><div class="stat-label">Unassigned</div><div class="stat-value"><?= $unassigned ?></div></div>
      <div class="card card-pad"><div class="stat-label">Breaching SLA</div><div class="stat-value"><?= $breaching ?></div></div>
      <div class="card card-pad"><div class="stat-label">Resolved</div><div class="stat-value"><?= $resolvedToday ?></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-bottom:24px;align-items:start;">
      <div class="card card-pad">
        <div class="display" style="font-size:15px;font-weight:700;margin-bottom:16px;">Tickets by department</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($byDept as $dept => $n): ?>
            <div style="display:flex;align-items:center;gap:12px;">
              <div style="width:110px;font-size:12.5px;color:var(--text-mid);"><?= h($dept) ?></div>
              <div style="flex:1;background:var(--bg-app);border-radius:8px;height:10px;overflow:hidden;">
                <div style="width:<?= round(($n / $maxDept) * 100) ?>%;height:100%;background:var(--brand);border-radius:8px;"></div>
              </div>
              <div class="mono" style="font-size:12px;width:20px;text-align:right;"><?= $n ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card card-pad">
        <div class="display" style="font-size:15px;font-weight:700;margin-bottom:16px;">Team workload</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($store['agents'] as $agent):
                $n = count(array_filter($tickets, fn($t) => ($t['assignee'] ?? null) === $agent && !in_array($t['status'], ['Resolved', 'Closed'], true)));
                $ac = td_avatar_colors($agent);
          ?>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="avatar" style="width:28px;height:28px;font-size:11px;background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;"><?= h(td_initials($agent)) ?></div>
              <div style="flex:1;font-size:12.5px;font-weight:600;"><?= h($agent) ?></div>
              <span class="badge" style="background:var(--bg-app);color:var(--text-mid);"><?= $n ?> active</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <div class="display" style="font-size:15.5px;font-weight:700;">Needs attention</div>
      <a href="tickets.php" style="font-size:12.5px;color:var(--brand);font-weight:600;">View all tickets &rarr;</a>
    </div>
    <div class="card"><?php td_render_ticket_rows($needsAttention, $priorities, true) ?></div>
<?php endif; ?>

<script>
function openReportIssue(event) {
  if (event) event.preventDefault();
  var modal = document.getElementById('report-issue');
  if (!modal) return;
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  var subject = document.getElementById('report-subject');
  if (subject) subject.focus();
}

function closeReportIssue() {
  var modal = document.getElementById('report-issue');
  if (!modal) return;
  modal.style.display = 'none';
  document.body.style.overflow = '';
}

function closeReportIssueOnOverlay(event) {
  if (event.target === event.currentTarget) closeReportIssue();
}

document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') closeReportIssue();
});
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
