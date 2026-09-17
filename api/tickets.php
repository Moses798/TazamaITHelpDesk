<?php
/**
 * Tazai API — Tickets resource
 * CRUD operations on TazamaDesk store.json
 */

define('STORE_FILE', __DIR__ . '/../data/store.json');

function td_api_load() {
    if (!file_exists(STORE_FILE)) {
        // Init from seed if store doesn't exist yet
        $seed = __DIR__ . '/../data/seed.json';
        if (file_exists($seed)) {
            $data = json_decode(file_get_contents($seed), true);
            $now = time();
            foreach ($data['tickets'] as &$t) {
                $t['created'] = $now - (int)round(($t['created_offset_h'] ?? 0) * 3600);
                unset($t['created_offset_h']);
                if (isset($t['closed_offset_h'])) {
                    $t['closed_at'] = $now - (int)round($t['closed_offset_h'] * 3600);
                    unset($t['closed_offset_h']);
                } else { $t['closed_at'] = null; }
                foreach ($t['history'] as &$h) {
                    $h['at'] = $now - (int)round(($h['offset_h'] ?? 0) * 3600);
                    unset($h['offset_h']);
                }
            }
            td_api_save($data);
            return $data;
        }
        return ['tickets' => [], 'accounts' => []];
    }
    return json_decode(file_get_contents(STORE_FILE), true);
}

function td_api_save($store) {
    $fp = fopen(STORE_FILE, 'c+');
    flock($fp, LOCK_EX);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
}

function td_next_id($store) {
    $max = 0;
    foreach ($store['tickets'] as $t) $max = max($max, (int)$t['id']);
    return $max + 1;
}

function handle_tickets($method, $id) {
    $store = td_api_load();

    // GET /api/tickets
    if ($method === 'GET' && $id === null) {
        $tickets = $store['tickets'];
        // Optional filters
        if (!empty($_GET['status']))   $tickets = array_values(array_filter($tickets, fn($t) => $t['status'] === $_GET['status']));
        if (!empty($_GET['priority'])) $tickets = array_values(array_filter($tickets, fn($t) => $t['priority'] === $_GET['priority']));
        if (!empty($_GET['dept']))     $tickets = array_values(array_filter($tickets, fn($t) => $t['dept'] === $_GET['dept']));
        // Strip history for list view
        $lite = array_map(function($t) {
            $copy = $t;
            unset($copy['history']);
            return $copy;
        }, $tickets);
        echo json_encode(['tickets' => $lite, 'count' => count($lite)]);
        return;
    }

    // GET /api/tickets/{id}
    if ($method === 'GET' && $id !== null) {
        foreach ($store['tickets'] as $t) {
            if ((int)$t['id'] === (int)$id) {
                echo json_encode($t);
                return;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found']);
        return;
    }

    // POST /api/tickets — create
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            return;
        }

        $required = ['subject', 'requester', 'desc'];
        foreach ($required as $f) {
            if (empty($body[$f])) {
                http_response_code(422);
                echo json_encode(['error' => "Missing required field: $f"]);
                return;
            }
        }

        $newId = td_next_id($store);
        $now   = time();

        $ticket = [
            'id'        => $newId,
            'subject'   => $body['subject'],
            'dept'      => $body['dept']      ?? 'Unknown',
            'cat'       => $body['cat']       ?? 'General',
            'priority'  => $body['priority']  ?? 'Medium',
            'status'    => 'New',
            'requester' => $body['requester'],
            'assignee'  => $body['assignee']  ?? null,
            'created'   => $now,
            'closed_at' => null,
            'desc'      => $body['desc'],
            'source'    => $body['source']    ?? 'whatsapp',
            'phone'     => $body['phone']     ?? null,
            'internal_notes' => [],   // IT-only, never sent to WhatsApp
            'history'   => [[
                'who'    => $body['requester'],
                'role'   => 'employee',
                'action' => 'created this ticket via ' . ($body['source'] ?? 'whatsapp'),
                'at'     => $now,
            ]],
        ];

        // Append internal note if provided (never exposed to employee)
        if (!empty($body['internal_note'])) {
            $ticket['internal_notes'][] = [
                'note' => $body['internal_note'],
                'at'   => $now,
                'by'   => 'Tazai',
            ];
        }

        $store['tickets'][] = $ticket;
        td_api_save($store);

        http_response_code(201);
        $response = $ticket;
        unset($response['internal_notes']); // never return internal notes
        echo json_encode(['ticket' => $response, 'id' => $newId]);
        return;
    }

    // PATCH /api/tickets/{id} — update status, assignee, add note
    if ($method === 'PATCH' && $id !== null) {
        $body = json_decode(file_get_contents('php://input'), true);
        $found = false;
        $now = time();

        foreach ($store['tickets'] as &$t) {
            if ((int)$t['id'] === (int)$id) {
                $found = true;
                $allowed = ['status', 'assignee', 'priority'];
                foreach ($allowed as $f) {
                    if (isset($body[$f])) $t[$f] = $body[$f];
                }
                if (!empty($body['status'])) {
                    $t['history'][] = [
                        'who'    => 'Tazai',
                        'role'   => 'system',
                        'action' => 'updated status to ' . $body['status'],
                        'at'     => $now,
                    ];
                    if (in_array($body['status'], ['Resolved', 'Closed'])) {
                        $t['closed_at'] = $now;
                        if (!empty($body['resolution_note'])) {
                            $t['resolution_note'] = $body['resolution_note'];
                        }
                    }
                }
                if (!empty($body['internal_note'])) {
                    if (!isset($t['internal_notes'])) $t['internal_notes'] = [];
                    $t['internal_notes'][] = [
                        'note' => $body['internal_note'],
                        'at'   => $now,
                        'by'   => 'Tazai',
                    ];
                }
                break;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => 'Ticket not found']);
            return;
        }

        td_api_save($store);
        echo json_encode(['success' => true, 'id' => (int)$id]);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
