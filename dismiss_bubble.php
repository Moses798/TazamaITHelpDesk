<?php
require_once __DIR__ . '/includes/config.php';
td_require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$store = td_load_store();
$idx = td_find_ticket_index($store, $id);
if ($idx !== null) {
    foreach ($store['tickets'][$idx]['history'] as &$hh) {
        if (!empty($hh['is_message']) && ($hh['role'] ?? '') === 'employee') $hh['seen_by_admin'] = true;
    }
    unset($hh);
    td_save_store($store);
}
$back = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header('Location: ' . $back);
exit;
