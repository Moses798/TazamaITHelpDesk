<?php
/** MySQL storage adapter.  It preserves the legacy array shape used by pages. */

function td_db() {
    static $pdo;
    if ($pdo) return $pdo;
    $settings = [];
    $envFile = BASE_PATH . '/.env';
    if (is_readable($envFile)) foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $match)) $settings[$match[1]] = trim($match[2], " \t\"'");
    }
    $setting = fn($key, $default) => getenv($key) ?: ($settings[$key] ?? $default);
    $host = $setting('TAZAMADESK_DB_HOST', '127.0.0.1');
    $port = $setting('TAZAMADESK_DB_PORT', '3306');
    $name = $setting('TAZAMADESK_DB_NAME', 'tazama_helpdesk');
    $user = $setting('TAZAMADESK_DB_USER', 'root');
    $pass = $setting('TAZAMADESK_DB_PASSWORD', '');
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException("MySQL connection failed. Check TAZAMADESK_DB_* settings and import database/tazama_helpdesk_schema.sql. Details: " . $e->getMessage(), 0, $e);
    }
    return $pdo;
}

function td_db_lookup($table, $key = 'name') {
    $rows = td_db()->query("SELECT id, {$key} AS value FROM {$table} ORDER BY id")->fetchAll();
    return array_column($rows, 'id', 'value');
}

function td_db_user_id($name, $role = 'agent') {
    static $ids = [];
    if (isset($ids[$name])) return $ids[$name];
    $pdo = td_db();
    $q = $pdo->prepare('SELECT id FROM users WHERE full_name = ?'); $q->execute([$name]);
    if ($id = $q->fetchColumn()) return $ids[$name] = (int)$id;
    $q = $pdo->prepare('INSERT INTO users (full_name, role) VALUES (?, ?)');
    $q->execute([$name ?: 'Unknown user', in_array($role, ['employee','agent','admin'], true) ? $role : 'agent']);
    return $ids[$name] = (int)$pdo->lastInsertId();
}

function td_db_store_load() {
    $pdo = td_db();
    $store = ['accounts' => [], 'agents' => [], 'departments' => [], 'categories' => [], 'priorities' => [], 'statuses' => [], 'kb_articles' => [], 'tickets' => []];
    foreach ($pdo->query("SELECT full_name, email, password_hash, role, title FROM users WHERE email IS NOT NULL AND password_hash IS NOT NULL") as $u) {
        $store['accounts'][] = ['email' => $u['email'], 'password' => preg_replace('/^PLAINTEXT-DEMO:/', '', $u['password_hash']), 'role' => $u['role'], 'name' => $u['full_name'], 'title' => $u['title']];
    }
    $store['agents'] = $pdo->query("SELECT full_name FROM users WHERE role = 'agent' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $store['departments'] = $pdo->query('SELECT name FROM departments ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $store['categories'] = $pdo->query('SELECT name FROM categories ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pdo->query('SELECT name, sla_hours FROM priorities ORDER BY sort_order, id') as $r) $store['priorities'][$r['name']] = ['hours' => (int)$r['sla_hours']];
    $store['statuses'] = $pdo->query('SELECT name FROM statuses ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pdo->query('SELECT k.title, c.name AS cat, k.views FROM kb_articles k JOIN categories c ON c.id=k.category_id ORDER BY k.id') as $r) $store['kb_articles'][] = $r;
    $rows = $pdo->query('SELECT t.*, d.name dept, c.name cat, p.name priority, s.name status, rq.full_name requester, a.full_name assignee, cb.full_name closed_by, UNIX_TIMESTAMP(t.created_at) created, UNIX_TIMESTAMP(t.closed_at) closed_ts FROM tickets t LEFT JOIN departments d ON d.id=t.department_id JOIN categories c ON c.id=t.category_id JOIN priorities p ON p.id=t.priority_id JOIN statuses s ON s.id=t.status_id JOIN users rq ON rq.id=t.requester_id LEFT JOIN users a ON a.id=t.assignee_id LEFT JOIN users cb ON cb.id=t.closed_by_id ORDER BY t.id DESC')->fetchAll();
    $events = $pdo->query('SELECT e.*, u.full_name who, UNIX_TIMESTAMP(e.created_at) at_ts FROM ticket_events e JOIN users u ON u.id=e.user_id ORDER BY e.created_at, e.id')->fetchAll();
    $byTicket = [];
    foreach ($events as $e) {
        $h = ['who'=>$e['who'], 'role'=>$e['actor_role'], 'action'=>$e['action'], 'at'=>(int)$e['at_ts']];
        if ($e['note'] !== null) $h[$e['is_message'] ? 'text' : 'note'] = $e['note'];
        if ($e['is_message']) { $h['is_message'] = true; $h['seen_by_admin'] = (bool)$e['seen_by_admin']; $h['seen_by_requester'] = (bool)$e['seen_by_requester']; }
        $byTicket[$e['ticket_id']][] = $h;
    }
    foreach ($rows as $r) {
        $t = ['id'=>(int)$r['id'], 'subject'=>$r['subject'], 'dept'=>$r['dept'] ?? 'Unknown', 'cat'=>$r['cat'], 'priority'=>$r['priority'], 'status'=>$r['status'], 'requester'=>$r['requester'], 'assignee'=>$r['assignee'], 'created'=>(int)$r['created'], 'closed_at'=>$r['closed_ts'] === null ? null : (int)$r['closed_ts'], 'desc'=>$r['description'], 'history'=>$byTicket[$r['id']] ?? []];
        foreach (['closed_by','resolution_note','source','phone'] as $key) if ($r[$key] !== null) $t[$key] = $r[$key];
        $t['internal_notes'] = $r['internal_notes'] ? json_decode($r['internal_notes'], true) : [];
        $store['tickets'][] = $t;
    }
    return $store;
}

function td_db_named_id($table, $name) {
    $pdo = td_db(); $q = $pdo->prepare("SELECT id FROM {$table} WHERE name=?"); $q->execute([$name]);
    if ($id = $q->fetchColumn()) return (int)$id;
    if ($table === 'priorities') $pdo->prepare('INSERT INTO priorities (name,sla_hours) VALUES (?,24)')->execute([$name]);
    elseif ($table === 'statuses') $pdo->prepare('INSERT INTO statuses (name) VALUES (?)')->execute([$name]);
    else $pdo->prepare("INSERT INTO {$table} (name) VALUES (?)")->execute([$name]);
    return (int)$pdo->lastInsertId();
}

function td_db_store_save($store) {
    $pdo = td_db(); $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM ticket_events'); $pdo->exec('DELETE FROM tickets');
        $ins = $pdo->prepare('INSERT INTO tickets (id,subject,department_id,category_id,priority_id,status_id,requester_id,assignee_id,description,created_at,closed_at,closed_by_id,resolution_note,source,phone,internal_notes) VALUES (?,?,?,?,?,?,?,?,?,FROM_UNIXTIME(?),FROM_UNIXTIME(?),?,?,?,?,?)');
        $event = $pdo->prepare('INSERT INTO ticket_events (ticket_id,user_id,actor_role,action,note,is_message,seen_by_admin,seen_by_requester,created_at) VALUES (?,?,?,?,?,?,?,?,FROM_UNIXTIME(?))');
        foreach ($store['tickets'] as $t) {
            $dept = empty($t['dept']) ? null : td_db_named_id('departments', $t['dept']);
            $cat = td_db_named_id('categories', $t['cat'] ?? 'General'); $pri = td_db_named_id('priorities', $t['priority'] ?? 'Medium'); $status = td_db_named_id('statuses', $t['status'] ?? 'New');
            $requester = td_db_user_id($t['requester'] ?? 'Unknown', 'employee'); $assignee = empty($t['assignee']) ? null : td_db_user_id($t['assignee'], 'agent'); $closer = empty($t['closed_by']) ? null : td_db_user_id($t['closed_by'], 'admin');
            $ins->execute([(int)$t['id'], $t['subject'], $dept, $cat, $pri, $status, $requester, $assignee, $t['desc'], (int)$t['created'], empty($t['closed_at']) ? null : (int)$t['closed_at'], $closer, $t['resolution_note'] ?? null, $t['source'] ?? null, $t['phone'] ?? null, json_encode($t['internal_notes'] ?? [])]);
            foreach ($t['history'] ?? [] as $h) {
                $role = $h['role'] ?? 'agent'; $note = $h['text'] ?? $h['note'] ?? null;
                $event->execute([(int)$t['id'], td_db_user_id($h['who'] ?? 'Unknown', $role), in_array($role, ['employee','agent','admin','system'], true) ? $role : 'agent', $h['action'] ?? 'updated this ticket', $note, !empty($h['is_message']), isset($h['seen_by_admin']) ? (int)(bool)$h['seen_by_admin'] : null, isset($h['seen_by_requester']) ? (int)(bool)$h['seen_by_requester'] : null, (int)($h['at'] ?? time())]);
            }
        }
        $max = max(array_map(fn($t) => (int)$t['id'], $store['tickets'] ?: [['id'=>0]])); $pdo->exec('ALTER TABLE tickets AUTO_INCREMENT = ' . ($max + 1));
        $pdo->commit(); return true;
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
