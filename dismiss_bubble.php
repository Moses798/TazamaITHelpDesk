<?php
require_once __DIR__ . '/includes/config.php';
td_require_login();

$user = td_current_user();
$id = (int) ($_GET['id'] ?? 0);
$store = td_load_store();
$idx = td_find_ticket_index($store, $id);
if ($idx !== null && ($user['role'] === 'admin' || ($store['tickets'][$idx]['requester'] ?? '') === ($user['name'] ?? ''))) {
    foreach ($store['tickets'][$idx]['history'] as &$hh) {
        if (!empty($hh['is_message']) && $user['role'] === 'admin' && ($hh['role'] ?? '') === 'employee') {
            $hh['seen_by_admin'] = true;
        }
        if (!empty($hh['is_message']) && $user['role'] === 'employee' && ($hh['role'] ?? '') === 'admin') {
            $hh['seen_by_requester'] = true;
        }
    }
    unset($hh);
    td_save_store($store);
}
$back = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header('Location: ' . $back);
exit;
