<?php
/**
 * Tazai API — Employees resource
 * Phone-based lookup against tazai.db SQLite employee registry
 */

define('TAZAI_DB', __DIR__ . '/../../database/tazai.db');

function normalize_phone_api(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 12 && str_starts_with($digits, '260')) return "+$digits";
    if (strlen($digits) === 10 && str_starts_with($digits, '0'))   return '+260' . substr($digits, 1);
    if (strlen($digits) === 9)  return "+260$digits";
    return "+$digits";
}

function handle_employees($method, $sub) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // GET /api/employees/lookup?phone=
    if ($sub === 'lookup') {
        $phone = $_GET['phone'] ?? '';
        if (!$phone) {
            http_response_code(400);
            echo json_encode(['error' => 'phone parameter required']);
            return;
        }

        if (!file_exists(TAZAI_DB)) {
            http_response_code(503);
            echo json_encode(['error' => 'Employee registry unavailable']);
            return;
        }

        $normalized = normalize_phone_api($phone);

        try {
            $db  = new SQLite3(TAZAI_DB, SQLITE3_OPEN_READONLY);
            $stmt = $db->prepare(
                'SELECT id, name, title, department, position, phone_e164, email, role, authorized
                 FROM employees WHERE phone_e164 = ? AND authorized = 1'
            );
            $stmt->bindValue(1, $normalized, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row    = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();

            if ($row) {
                echo json_encode(['authorized' => true, 'employee' => $row]);
            } else {
                echo json_encode(['authorized' => false]);
            }
        } catch (Exception $e) {
            http_response_code(503);
            echo json_encode(['error' => 'Registry lookup failed']);
        }
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown employees endpoint']);
}
