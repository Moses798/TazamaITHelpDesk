<?php
/**
 * TazamaDesk — core config, session bootstrap, and data access helpers.
 * Data is stored in a single JSON file (data/store.json) so the app runs
 * with zero database setup. Every request reads the file, and any writes
 * are saved back with an exclusive lock.
 */

session_start();

define('BASE_PATH', dirname(__DIR__));
define('DATA_FILE', BASE_PATH . '/data/store.json');
define('SEED_FILE', BASE_PATH . '/data/seed.json');

/* ------------------------------------------------------------------ */
/* Data store                                                          */
/* ------------------------------------------------------------------ */

/** Build the working store from the seed file on first run, or recover if the store file is corrupt. */
function td_init_store() {
    $needs_init = !file_exists(DATA_FILE);

    if (!$needs_init) {
        $raw = @file_get_contents(DATA_FILE);
        $decoded = $raw === false ? null : json_decode($raw, true);
        $needs_init = !is_array($decoded) || !isset($decoded['accounts']) || !isset($decoded['tickets']);
    }

    if (!$needs_init) return;

    if (!file_exists(SEED_FILE)) {
        return;
    }

    $seed = json_decode(file_get_contents(SEED_FILE), true);
    if (!is_array($seed)) {
        return;
    }

    $now = time();

    foreach ($seed['tickets'] as &$t) {
        $t['created'] = $now - (int) round($t['created_offset_h'] * 3600);
        unset($t['created_offset_h']);

        if (isset($t['closed_offset_h'])) {
            $t['closed_at'] = $now - (int) round($t['closed_offset_h'] * 3600);
            unset($t['closed_offset_h']);
        } else {
            $t['closed_at'] = null;
        }

        foreach ($t['history'] as &$h) {
            $h['at'] = $now - (int) round($h['offset_h'] * 3600);
            unset($h['offset_h']);
        }
        unset($h);
    }
    unset($t);

    td_save_store($seed);
}

function td_load_store() {
    td_init_store();
    $raw = @file_get_contents(DATA_FILE);
    if ($raw === false) {
        return null;
    }
    return json_decode($raw, true);
}

function td_save_store($store) {
    $fp = fopen(DATA_FILE, 'c+');
    if ($fp === false) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function td_reset_store() {
    if (file_exists(DATA_FILE)) unlink(DATA_FILE);
    td_init_store();
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

function td_current_user() {
    return $_SESSION['user'] ?? null;
}

function td_require_login() {
    if (!td_current_user()) {
        header('Location: index.php');
        exit;
    }
}

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

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

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

function td_sla_breached($ticket, $priorities) {
    if (in_array($ticket['status'], ['Resolved', 'Closed'], true)) return false;
    $hours = $priorities[$ticket['priority']]['hours'];
    $elapsedH = (time() - $ticket['created']) / 3600;
    return $elapsedH > $hours;
}

function td_priority_colors($priority) {
    $map = [
        'Critical' => ['color' => '#A8281F', 'soft' => '#FBE9E7'],
        'High'     => ['color' => '#C9670A', 'soft' => '#FDEEDD'],
        'Medium'   => ['color' => '#2B2622', 'soft' => '#EDECEA'],
        'Low'      => ['color' => '#5C5854', 'soft' => '#EEF0F4'],
    ];
    return $map[$priority] ?? ['color' => '#5C5854', 'soft' => '#EEF0F4'];
}

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
