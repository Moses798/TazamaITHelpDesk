<?php
/**
 * TazamaDesk — core config, session bootstrap, and data access helpers.
 * Data is stored in MySQL. Configure TAZAMADESK_DB_HOST, _PORT, _NAME, _USER,
 * and _PASSWORD in the web-server environment when XAMPP defaults do not fit.
 */

session_start();

define('BASE_PATH', dirname(__DIR__));
define('SEED_FILE', BASE_PATH . '/data/seed.json');
require_once __DIR__ . '/priority.php';
require_once __DIR__ . '/database.php';

// ── Tazai AI Enable Switch ────────────────────────────────────────────────────
// Set to true ONLY when development is complete and Tazai is ready for employees.
// This is the ONE authoritative switch — no other file should hardcode the enabled state.
define('TAZAI_AI_ENABLED', false);

// Path to the Tazai employee registry (SQLite)
define('TAZAI_DB_PATH', '/home/shadrickvidmar/Projects/vidmarholdings/Businesses/vidmar.ai/assets/clients/Tazama Pipelines LTD/Tazai/database/tazai.db');

/* ------------------------------------------------------------------ */
/* Data store                                                          */
/* ------------------------------------------------------------------ */

/** Verify the imported MySQL schema is reachable. */
function td_init_store() {
    td_db()->query('SELECT 1 FROM tickets LIMIT 1');
}

/** Load the initialized JSON store for the current request. */
function td_load_store() {
    td_init_store();
    return td_db_store_load();
}

/** Write the JSON store atomically with an exclusive lock. */
function td_save_store($store) {
    return td_db_store_save($store);
}

/** Rebuild the runtime store from seed data. */
function td_reset_store() {
    $seed = json_decode(file_get_contents(SEED_FILE), true);
    if (!is_array($seed)) throw new RuntimeException('The JSON seed file is unavailable.');
    $now = time();
    foreach ($seed['tickets'] as &$ticket) {
        $ticket['created'] = $now - (int)round(($ticket['created_offset_h'] ?? 0) * 3600);
        unset($ticket['created_offset_h']);
        $ticket['closed_at'] = isset($ticket['closed_offset_h']) ? $now - (int)round($ticket['closed_offset_h'] * 3600) : null;
        unset($ticket['closed_offset_h']);
        foreach ($ticket['history'] as &$event) { $event['at'] = $now - (int)round(($event['offset_h'] ?? 0) * 3600); unset($event['offset_h']); }
        unset($event);
    }
    unset($ticket);
    td_save_store($seed);
}

/** Find one ticket by id from a store array (read-only copy). Returns null if missing. */
function td_find_ticket($store, $id) {
    foreach ($store['tickets'] as $t) {
        if ((int) $t['id'] === (int) $id) return $t;
    }
    return null;
}

/** Find the array index of a ticket by id, for direct mutation of $store['tickets'][$idx]. */
function td_find_ticket_index($store, $id) {
    foreach ($store['tickets'] as $i => $t) {
        if ((int) $t['id'] === (int) $id) return $i;
    }
    return null;
}

/* ------------------------------------------------------------------ */
/* Auth                                                                 */
/* ------------------------------------------------------------------ */

/** Return the signed-in user's session record, if present. */
function td_current_user() {
    return $_SESSION['user'] ?? null;
}

/** Redirect anonymous visitors to the login page. */
function td_require_login() {
    if (!td_current_user()) {
        header('Location: index.php');
        exit;
    }
}

/** Permit only the requested role to continue to the current page. */
function td_require_role($role) {
    td_require_login();
    if (td_current_user()['role'] !== $role) {
        header('Location: dashboard.php');
        exit;
    }
}

/* ------------------------------------------------------------------ */
/* Small helpers                                                       */
/* ------------------------------------------------------------------ */

/** Escape text before placing it in HTML. */
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/** Format a Unix timestamp for display in the interface. */
function td_time_ago_label($ts) {
    return date('M j, g:i A', (int) $ts);
}

/** % of SLA window remaining, clamped 0-100. */
function td_sla_pct($ticket, $priorities) {
    $hours = $priorities[$ticket['priority']]['hours'];
    $elapsedH = (time() - $ticket['created']) / 3600;
    $remaining = max(0, $hours - $elapsedH);
    return max(0, min(100, ($remaining / $hours) * 100));
}

/** Determine whether an unresolved ticket has exceeded its SLA window. */
function td_sla_breached($ticket, $priorities) {
    if (in_array($ticket['status'], ['Resolved', 'Closed'], true)) return false;
    $hours = $priorities[$ticket['priority']]['hours'];
    $elapsedH = (time() - $ticket['created']) / 3600;
    return $elapsedH > $hours;
}

/** Map a priority name to its display colors. */
function td_priority_colors($priority) {
    $map = [
        'Critical' => ['color' => '#A8281F', 'soft' => '#FBE9E7'],
        'High'     => ['color' => '#C9670A', 'soft' => '#FDEEDD'],
        'Medium'   => ['color' => '#2B2622', 'soft' => '#EDECEA'],
        'Low'      => ['color' => '#5C5854', 'soft' => '#EEF0F4'],
    ];
    return $map[$priority] ?? ['color' => '#5C5854', 'soft' => '#EEF0F4'];
}

/** Map a ticket status to its display colors. */
function td_status_colors($status) {
    $map = [
        'New'          => ['color' => '#3E6FA8', 'soft' => '#E8EFF6'],
        'Assigned'     => ['color' => '#2B2622', 'soft' => '#EDECEA'],
        'In Progress'  => ['color' => '#B4720E', 'soft' => '#FDF1DD'],
        'On Hold'      => ['color' => '#5C5854', 'soft' => '#EEF0F4'],
        'Resolved'     => ['color' => '#12875A', 'soft' => '#E4F6EE'],
        'Closed'       => ['color' => '#3E4658', 'soft' => '#EAEBEF'],
    ];
    return $map[$status] ?? ['color' => '#5C5854', 'soft' => '#EEF0F4'];
}

/** Build up to two initials for an avatar label. */
function td_initials($name) {
    if (!$name) return '?';
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) $initials .= strtoupper(substr($p, 0, 1));
    return $initials;
}

/** Small deterministic avatar color from a name, for visual variety. */
function td_avatar_colors($name) {
    $palette = [
        ['#FBE4E3', '#A8141B'],
        ['#EDECEA', '#2B2622'],
        ['#E4F6EE', '#12875A'],
    ];
    $sum = 0;
    foreach (str_split((string) $name) as $c) $sum += ord($c);
    return $palette[$sum % 3];
}

/** Return the next available ID in the local ticket store. */
function td_next_ticket_id($store) {
    $max = 0;
    foreach ($store['tickets'] as $t) $max = max($max, (int) $t['id']);
    return $max + 1;
}

/** Renders the SLA countdown ring as inline SVG. Returns HTML string. */
function td_sla_ring($pct, $breached, $done, $size = 40, $stroke = 4) {
    $r = ($size - $stroke) / 2;
    $c = 2 * M_PI * $r;
    if ($done) $color = '#12875A';
    elseif ($breached) $color = '#A8281F';
    elseif ($pct < 25) $color = '#A8281F';
    elseif ($pct < 55) $color = '#B4720E';
    else $color = '#3E6FA8';
    $displayPct = $done ? 100 : $pct;
    $offset = $c - ($c * $displayPct / 100);
    $half = $size / 2;

    $icon = '';
    if ($done) {
        $s = $size * 0.42;
        $icon = "<div style='position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#12875A;font-size:{$s}px;font-weight:700;'>&#10003;</div>";
    } elseif ($breached) {
        $s = $size * 0.42;
        $icon = "<div style='position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#A8281F;font-size:{$s}px;font-weight:700;'>!</div>";
    }

    return "<div style='position:relative;width:{$size}px;height:{$size}px;flex-shrink:0;display:inline-block;'>"
        . "<svg class='sla-ring' width='{$size}' height='{$size}'>"
        . "<circle cx='{$half}' cy='{$half}' r='{$r}' fill='none' stroke='#E4E3E0' stroke-width='{$stroke}'/>"
        . "<circle cx='{$half}' cy='{$half}' r='{$r}' fill='none' stroke='{$color}' stroke-width='{$stroke}' "
        . "stroke-dasharray='{$c}' stroke-dashoffset='{$offset}' stroke-linecap='round'/>"
        . "</svg>{$icon}</div>";
}
