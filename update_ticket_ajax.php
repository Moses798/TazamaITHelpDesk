<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

$user = td_current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$validStatuses = ['New', 'Assigned', 'In Progress', 'On Hold', 'Resolved'];

if (!$id || !in_array($status, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$store = td_load_store();
$idx = td_find_ticket_index($store, $id);
if ($idx === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Ticket not found']);
    exit;
}

if ($store['tickets'][$idx]['status'] !== $status) {
    $store['tickets'][$idx]['status'] = $status;
    $store['tickets'][$idx]['history'][] = ['who' => $user['name'], 'role' => 'admin', 'action' => "moved this ticket to {$status}", 'at' => time()];
    td_save_store($store);
}

echo json_encode(['ok' => true]);
