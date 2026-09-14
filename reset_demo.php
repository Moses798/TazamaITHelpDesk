<?php
require_once __DIR__ . '/includes/config.php';
td_require_role('admin');
td_reset_store();
header('Location: settings.php');
exit;
