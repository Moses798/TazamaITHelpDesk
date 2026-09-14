<?php
require_once __DIR__ . '/includes/config.php';
td_require_login();

$user = td_current_user();
$store = td_load_store();
$priorities = $store['priorities'];
$q = trim($_GET['q'] ?? '');
$needle = strtolower($q);

$ticketMatches = $q === '' ? [] : array_values(array_filter($store['tickets'], function ($t) use ($needle) {
    return str_contains(strtolower($t['subject']), $needle) || str_contains((string) $t['id'], $needle);
}));
$articleMatches = $q === '' ? [] : array_values(array_filter($store['kb_articles'], function ($a) use ($needle) {
    return str_contains(strtolower($a['title']), $needle) || str_contains(strtolower($a['cat']), $needle);
}));

$page_title = 'Search results';
$active_nav = '';
require __DIR__ . '/includes/layout_top.php';
?>

<div style="font-size:13px;color:var(--text-mid);margin-bottom:16px;">Results for "<?= h($q) ?>"</div>

<?php if ($q === '' || (empty($ticketMatches) && empty($articleMatches))): ?>
  <div class="card" style="padding:40px;text-align:center;color:var(--text-faint);font-size:13px;">No results found.</div>
<?php endif; ?>

<?php if (!empty($ticketMatches)): ?>
  <div style="font-size:12px;font-weight:700;color:var(--text-faint);letter-spacing:.4px;margin-bottom:10px;">TICKETS</div>
  <div class="card" style="margin-bottom:24px;">
    <?php foreach ($ticketMatches as $t):
          $pct = td_sla_pct($t, $priorities); $breached = td_sla_breached($t, $priorities);
          $done = in_array($t['status'], ['Resolved', 'Closed'], true);
          $sc = td_status_colors($t['status']);
    ?>
      <div class="ticket-row" style="display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid var(--border);" onclick="window.location='ticket.php?id=<?= (int) $t['id'] ?>'">
        <?= td_sla_ring($pct, $breached, $done, 26, 3) ?>
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:600;"><?= h($t['subject']) ?></div>
          <div class="mono" style="font-size:11px;color:var(--text-faint);">#<?= (int) $t['id'] ?></div>
        </div>
        <span class="badge" style="background:<?= $sc['soft'] ?>;color:<?= $sc['color'] ?>;"><?= h($t['status']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!empty($articleMatches)): ?>
  <div style="font-size:12px;font-weight:700;color:var(--text-faint);letter-spacing:.4px;margin-bottom:10px;">ARTICLES</div>
  <div class="card">
    <?php foreach ($articleMatches as $a): ?>
      <a href="kb.php?q=<?= urlencode($a['title']) ?>" style="display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid var(--border);color:inherit;">
        <div style="font-size:13px;font-weight:600;flex:1;"><?= h($a['title']) ?></div>
        <span class="badge" style="background:var(--bg-app);color:var(--text-mid);"><?= h($a['cat']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
