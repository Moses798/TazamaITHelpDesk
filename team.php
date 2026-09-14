<?php
require_once __DIR__ . '/includes/config.php';
td_require_role('admin');

$store = td_load_store();
$tickets = $store['tickets'];

$page_title = 'Team & Assignments';
$active_nav = 'team';
require __DIR__ . '/includes/layout_top.php';
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px;">
  <?php foreach ($store['agents'] as $agent):
        $active = count(array_filter($tickets, fn($t) => ($t['assignee'] ?? null) === $agent && !in_array($t['status'], ['Resolved', 'Closed'], true)));
        $resolved = count(array_filter($tickets, fn($t) => ($t['assignee'] ?? null) === $agent && $t['status'] === 'Resolved'));
        $ac = td_avatar_colors($agent);
  ?>
    <div class="card card-hover" style="padding:20px;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <div class="avatar" style="width:42px;height:42px;font-size:15px;background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;"><?= h(td_initials($agent)) ?></div>
        <div>
          <div style="font-size:14.5px;font-weight:700;"><?= h($agent) ?></div>
          <div style="font-size:12px;color:var(--text-faint);">IT Support Agent</div>
        </div>
      </div>
      <div style="display:flex;gap:10px;">
        <div style="flex:1;background:var(--bg-app);border-radius:12px;padding:12px;text-align:center;">
          <div class="display" style="font-size:20px;font-weight:700;"><?= $active ?></div>
          <div style="font-size:11px;color:var(--text-faint);font-weight:600;">Active</div>
        </div>
        <div style="flex:1;background:var(--bg-app);border-radius:12px;padding:12px;text-align:center;">
          <div class="display" style="font-size:20px;font-weight:700;"><?= $resolved ?></div>
          <div style="font-size:11px;color:var(--text-faint);font-weight:600;">Resolved</div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
