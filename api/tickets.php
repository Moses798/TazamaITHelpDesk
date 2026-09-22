<?php
/**
 * Tazai API — Tickets resource
 * CRUD operations on the TazamaDesk MySQL database.
 */
require_once __DIR__ . '/../includes/config.php';
function td_api_load() { return td_load_store(); }
function td_api_save($store) { return td_save_store($store); }

/** Return the next numeric ticket ID after the current highest ID. */
function td_next_id($store) {
    return td_next_ticket_id($store);
}

/** Dispatch ticket list, detail, create, and update operations. */
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

        // Reject incomplete tickets before adding them to the shared store.
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
                // Restrict PATCH requests to fields the API is allowed to change.
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
                    // Closing statuses receive a timestamp and optional resolution note.
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
