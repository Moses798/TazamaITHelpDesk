<?php
require_once __DIR__ . '/includes/config.php';
td_require_login();

$user = td_current_user();
$store = td_load_store();
$q = trim($_GET['q'] ?? '');

$articles = array_values(array_filter($store['kb_articles'], function ($a) use ($q) {
    if ($q === '') return true;
    $needle = strtolower($q);
    return str_contains(strtolower($a['title']), $needle) || str_contains(strtolower($a['cat']), $needle);
}));

$page_title = 'Knowledge Base';
$active_nav = 'kb';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card" style="padding:24px;margin-bottom:20px;text-align:center;">
  <div class="display" style="font-size:18px;font-weight:700;margin-bottom:8px;">How can we help?</div>
  <form method="get" action="kb.php" style="max-width:420px;margin:0 auto;">
    <input type="text" name="q" placeholder="Search articles, guides, and FAQs..." value="<?= h($q) ?>" style="height:42px;">
  </form>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;">
  <?php foreach ($articles as $a): ?>
    <div class="card card-hover" style="padding:18px;cursor:pointer;">
      <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
        <span class="badge" style="background:var(--brand-soft);color:var(--brand-dark);"><?= h($a['cat']) ?></span>
        <span style="color:var(--text-faint);">&#9733;</span>
      </div>
      <div style="font-size:14px;font-weight:600;color:var(--text-dark);margin-bottom:8px;line-height:1.4;"><?= h($a['title']) ?></div>
      <div style="font-size:11.5px;color:var(--text-faint);"><?= number_format($a['views']) ?> views</div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (empty($articles)): ?>
  <div class="card" style="padding:40px;text-align:center;color:var(--text-faint);font-size:13px;">No articles match "<?= h($q) ?>".</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
