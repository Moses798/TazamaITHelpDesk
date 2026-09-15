<?php
/**
 * Tazai REST API — entry point / router
 * Provides read/write access to TazamaDesk for Tazai AI agent.
 *
 * Auth: X-Tazai-Token header must match token in .env
 *
 * Routes:
 *   GET    /api/tickets              — list all tickets
 *   GET    /api/tickets/{id}         — get one ticket
 *   POST   /api/tickets              — create ticket
 *   PATCH  /api/tickets/{id}         — update ticket (status, assignee, note)
 *   GET    /api/employees/lookup     — ?phone= lookup by phone number
 *   GET    /api/health               — health check (no auth required)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Tazai-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Load .env token ──────────────────────────────────────────────────────────
$envFile = __DIR__ . '/.env';
$apiToken = null;
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, 'TAZAI_API_TOKEN=') === 0) {
            $apiToken = trim(substr($line, strlen('TAZAI_API_TOKEN=')));
        }
    }
}

// ── Health check (no auth) ───────────────────────────────────────────────────
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^.*?/api#', '', $path); // strip prefix up to /api

if ($path === '/health' || $path === '/health/') {
    echo json_encode(['status' => 'ok', 'service' => 'Tazai API', 'version' => '1.0']);
    exit;
}

// ── Auth ─────────────────────────────────────────────────────────────────────
$incomingToken = $_SERVER['HTTP_X_TAZAI_TOKEN'] ?? '';
if (!$apiToken || !hash_equals($apiToken, $incomingToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Route ────────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$segments = explode('/', trim($path, '/'));
// $segments[0] = resource (tickets|employees)
// $segments[1] = id or sub-resource

$resource = $segments[0] ?? '';
$id       = $segments[1] ?? null;

require_once __DIR__ . '/tickets.php';
require_once __DIR__ . '/employees.php';
require_once __DIR__ . '/chat.php';

if ($resource === 'tickets') {
    handle_tickets($method, $id);
} elseif ($resource === 'employees') {
    handle_employees($method, $id);
} elseif ($resource === 'chat') {
    handle_chat($method);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
