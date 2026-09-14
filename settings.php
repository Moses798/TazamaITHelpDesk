<?php
require_once __DIR__ . '/includes/config.php';
td_require_role('admin');

$rows = [
    ['label' => 'Business hours', 'value' => 'Mon–Fri, 8:00–17:00 CAT'],
    ['label' => 'Default SLA policy', 'value' => 'Priority-based (2h / 8h / 24h / 72h)'],
    ['label' => 'Auto-assignment', 'value' => 'Round-robin by category'],
    ['label' => 'Email notifications', 'value' => 'Enabled for status changes'],
];

$page_title = 'Settings';
$active_nav = 'settings';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <?php foreach ($rows as $i => $r): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 18px;<?= $i < count($rows) - 1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
      <div>
        <div style="font-size:13.5px;font-weight:600;"><?= h($r['label']) ?></div>
        <div style="font-size:12px;color:var(--text-faint);margin-top:2px;"><?= h($r['value']) ?></div>
      </div>
      <button class="btn btn-secondary" disabled>Edit</button>
    </div>
  <?php endforeach; ?>
</div>

<div style="margin-top:20px;">
  <a href="reset_demo.php" class="btn btn-secondary" onclick="return confirm('Reset all demo data back to the original seed? This clears any tickets, messages, or changes you\'ve made.')">Reset demo data</a>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
