<?php
/**
 * Shared page chrome (sidebar + topbar). Include AFTER config.php and
 * AFTER any POST handling, with these variables already set:
 *   $page_title  - string shown in the topbar
 *   $active_nav  - key of the current nav item (see nav arrays below)
 */
$user = td_current_user();
$role = $user['role'] ?? 'employee';
$store = td_load_store();
$is_dashboard = basename($_SERVER['PHP_SELF']) === 'dashboard.php';

$unseen_bubbles = [];
if ($role === 'admin') {
    foreach ($store['tickets'] as $t) {
        if (($t['assignee'] ?? null) !== $user['name']) continue;
        $unseen = array_values(array_filter($t['history'], function ($hh) use ($t) {
            return !empty($hh['is_message']) && ($hh['role'] ?? '') === 'employee'
                && ($hh['who'] ?? '') === $t['requester'] && empty($hh['seen_by_admin']);
        }));
        if (count($unseen) > 0) {
            $last = end($unseen);
            $unseen_bubbles[] = ['ticket' => $t, 'count' => count($unseen), 'last_from' => $last['who'], 'last_text' => $last['text']];
        }
    }
}

$employee_nav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'my-tickets', 'label' => 'My Tickets', 'href' => 'tickets.php'],
    ['key' => 'new-ticket', 'label' => 'Report an Issue', 'href' => $is_dashboard ? '#report-issue' : 'new_ticket.php'],
];
$admin_nav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'tickets', 'label' => 'All Tickets', 'href' => 'tickets.php'],
    ['key' => 'kanban', 'label' => 'Board View', 'href' => 'kanban.php'],
    ['key' => 'team', 'label' => 'Team & Assignments', 'href' => 'team.php'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php'],
    ['key' => 'kb', 'label' => 'Knowledge Base', 'href' => 'kb.php'],
    ['key' => 'settings', 'label' => 'Settings', 'href' => 'settings.php'],
];
$nav = $role === 'admin' ? $admin_nav : $employee_nav;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?> · TazamaDesk</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand-row">
      <div class="brand-icon">T</div>
      <div>
        <div class="brand-name display">TazamaDesk</div>
        <div class="brand-sub">IT Service Management</div>
      </div>
    </div>
    <div class="nav-label">MENU</div>
    <nav>
      <?php foreach ($nav as $item): ?>
        <a class="nav-item <?= $active_nav === $item['key'] ? 'active' : '' ?>" href="<?= h($item['href']) ?>"<?= $item['key'] === 'new-ticket' && $is_dashboard && $role === 'employee' ? ' onclick="openReportIssue(event)"' : '' ?>><?= h($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="status-card">
        All systems operational
        <div class="sub">Last checked 2 min ago</div>
      </div>
      <a class="nav-item" href="logout.php" style="color:rgba(255,255,255,.55)">Sign out</a>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <button class="btn btn-ghost mobile-toggle" onclick="document.getElementById('sidebar').style.display = document.getElementById('sidebar').style.display === 'block' ? 'none' : 'block'">&#9776;</button>
      <div class="page-title display"><?= h($page_title) ?></div>

      <form class="search hide-mobile" action="search.php" method="get">
        <span class="icon">&#128269;</span>
        <input type="text" name="q" placeholder="Search tickets and articles..." value="<?= h($_GET['q'] ?? '') ?>">
      </form>

      <div class="right">
        <span class="badge <?= $role === 'admin' ? 'role-badge-admin' : 'role-badge-employee' ?>">
          <?= $role === 'admin' ? 'IT Administrator' : 'Employee' ?>
        </span>
        <?php if ($role === 'employee' && $is_dashboard): ?>
          <a href="#report-issue" class="btn btn-primary hide-mobile" onclick="openReportIssue(event)">+ New ticket</a>
        <?php else: ?>
          <a href="new_ticket.php" class="btn btn-primary hide-mobile">+ New ticket</a>
        <?php endif; ?>
        <div style="width:1px;height:26px;background:var(--border)" class="hide-mobile"></div>
        <div class="hide-mobile" style="display:flex;align-items:center;gap:9px;">
          <?php $ac = td_avatar_colors($user['name'] ?? ''); ?>
          <div class="avatar" style="width:32px;height:32px;font-size:12.5px;background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;"><?= h(td_initials($user['name'] ?? '')) ?></div>
          <div>
            <div style="font-size:13px;font-weight:600;line-height:1.2;"><?= h($user['name'] ?? '') ?></div>
            <div style="font-size:11px;color:var(--text-faint)"><?= h($user['title'] ?? '') ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
