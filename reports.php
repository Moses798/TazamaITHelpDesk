<?php
require_once __DIR__ . '/includes/config.php';
td_require_role('admin');

$store = td_load_store();
$tickets = $store['tickets'];
$priorities = $store['priorities'];

$avgSla = 0;
if (count($tickets) > 0) {
    $sum = 0;
    foreach ($tickets as $t) $sum += td_sla_pct($t, $priorities);
    $avgSla = round($sum / count($tickets));
}
$closedCount = count(array_filter($tickets, fn($t) => in_array($t['status'], ['Resolved', 'Closed'], true)));
$closureRate = count($tickets) > 0 ? round(($closedCount / count($tickets)) * 100) : 0;

$byCat = [];
foreach ($tickets as $t) $byCat[$t['cat']] = ($byCat[$t['cat']] ?? 0) + 1;
arsort($byCat);
$maxCat = max(array_values($byCat) ?: [1]);

$page_title = 'Reports';
$active_nav = 'reports';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="grid-stats" style="margin-bottom:20px;">
  <div class="card card-pad"><div class="stat-label">Avg. SLA health</div><div class="stat-value"><?= $avgSla ?>%</div></div>
  <div class="card card-pad"><div class="stat-label">Total tickets</div><div class="stat-value"><?= count($tickets) ?></div></div>
  <div class="card card-pad"><div class="stat-label">Closure rate</div><div class="stat-value"><?= $closureRate ?>%</div></div>
</div>

<div class="card card-pad">
  <div class="display" style="font-size:15px;font-weight:700;margin-bottom:16px;">Tickets by category</div>
  <div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($byCat as $cat => $n): ?>
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:130px;font-size:12.5px;color:var(--text-mid);"><?= h($cat) ?></div>
        <div style="flex:1;background:var(--bg-app);border-radius:8px;height:10px;overflow:hidden;">
          <div style="width:<?= round(($n / $maxCat) * 100) ?>%;height:100%;background:var(--slate);border-radius:8px;"></div>
        </div>
        <div class="mono" style="font-size:12px;width:20px;text-align:right;"><?= $n ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
