<?php
require_once __DIR__ . '/includes/config.php';

if (td_current_user()) {
    header('Location: dashboard.php');
    exit;
}

$store = td_load_store();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $match = null;
    foreach ($store['accounts'] as $a) {
        if (strcasecmp($a['email'], $email) === 0 && $a['password'] === $password) { $match = $a; break; }
    }
    if ($match) {
        $_SESSION['user'] = $match;
        header('Location: dashboard.php');
        exit;
    }
    $error = "We couldn't verify those credentials. Check your email and password, or use a demo account below.";
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in · TazamaDesk</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:26px;">
      <div class="brand-icon" style="background:linear-gradient(135deg,var(--brand-dark),var(--brand));width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;">T</div>
      <div class="display" style="font-size:19px;font-weight:700;">TazamaDesk</div>
    </div>

    <div class="auth-box">
      <div style="margin-bottom:20px;">
        <div class="display" style="font-size:19px;font-weight:700;margin-bottom:5px;">Log in to your account</div>
        <div style="font-size:13px;color:var(--text-mid);">Your role and dashboard are determined automatically from your credentials.</div>
      </div>

      <?php if ($error): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="index.php" style="display:flex;flex-direction:column;gap:14px;">
        <div>
          <label class="field-label">Work email</label>
          <input type="email" name="email" placeholder="name@company.com" value="<?= h($_POST['email'] ?? '') ?>" required>
        </div>
        <div>
          <label class="field-label">Password</label>
          <input type="password" name="password" placeholder="Enter your password" required>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-mid);">
          <input type="checkbox" checked style="width:auto;"> Remember me on this device
        </label>
        <button type="submit" class="btn btn-primary" style="justify-content:center;padding:11px 16px;font-size:14px;">Log in</button>
      </form>

      <div style="margin-top:22px;background:var(--bg-app);border:1px solid var(--border);border-radius:14px;padding:14px;">
        <div style="font-size:11.5px;font-weight:700;color:var(--text-mid);letter-spacing:.3px;margin-bottom:10px;">DEMO ACCOUNTS — CLICK TO LOG IN INSTANTLY</div>
        <?php foreach ($store['accounts'] as $a): ?>
          <form method="post" action="index.php" style="margin:0;">
            <input type="hidden" name="email" value="<?= h($a['email']) ?>">
            <input type="hidden" name="password" value="<?= h($a['password']) ?>">
            <button type="submit" class="demo-account">
              <?php $ac = td_avatar_colors($a['name']); ?>
              <div class="avatar" style="width:28px;height:28px;font-size:11px;background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;"><?= h(td_initials($a['name'])) ?></div>
              <div style="flex:1;min-width:0;">
                <div style="font-size:12.5px;font-weight:600;color:var(--text-dark);"><?= h($a['name']) ?> · <?= h($a['title']) ?></div>
                <div class="mono" style="font-size:11px;color:var(--text-faint);"><?= h($a['email']) ?> / <?= h($a['password']) ?></div>
              </div>
              <span style="font-size:11px;font-weight:600;color:var(--brand);">Log in</span>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="text-align:center;font-size:11.5px;color:var(--text-faint);margin-top:20px;">
      Protected enterprise system · Contact IT if you need access
    </div>
  </div>
</div>
</body>
</html>
