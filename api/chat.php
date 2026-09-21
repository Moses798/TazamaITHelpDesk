<?php
/**
 * Tazai API — Chat endpoint
 * Receives a message from the website widget and returns Tazai's response.
 *
 * Enforcement order (all must pass before AI is invoked):
 *   1. TAZAI_AI_ENABLED flag (config.php) — global kill switch
 *   2. PHP session — authenticated website user required
 *   3. Employee registry (SQLite) — user must be an authorized Tazama employee
 */

/** Validate a chat request and return a safe support response. */
function handle_chat($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // ── 1. Global enable/disable switch ──────────────────────────────────────
    // Require config.php so TAZAI_AI_ENABLED and TAZAI_DB_PATH are available.
    // config.php also calls session_start() — guard against double-start.
    if (!defined('TAZAI_AI_ENABLED')) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        require_once __DIR__ . '/../includes/config.php';
    }

    if (!TAZAI_AI_ENABLED) {
        http_response_code(503);
        echo json_encode([
            'error'   => 'unavailable',
            'message' => 'Tazai is currently unavailable while the system is being developed.',
        ]);
        return;
    }

    // ── 2. Session authentication ─────────────────────────────────────────────
    // $_SESSION['user'] is set by index.php login and contains the account object.
    $sessionUser = $_SESSION['user'] ?? null;
    if (!$sessionUser || empty($sessionUser['email'])) {
        http_response_code(401);
        echo json_encode([
            'error'   => 'unauthorized',
            'message' => 'Tazai is available only to authorized Tazama employees.',
        ]);
        return;
    }

    // ── 3. Employee registry check (server-side, SQLite) ─────────────────────
    // Never trust a client-supplied flag. Look up the session email in the DB.
    $userEmail = strtolower(trim($sessionUser['email']));

    if (!tazai_is_authorized_employee($userEmail)) {
        http_response_code(403);
        echo json_encode([
            'error'   => 'unauthorized',
            'message' => 'Tazai is available only to authorized Tazama employees.',
        ]);
        return;
    }

    // ── 4. All checks passed — handle the request ─────────────────────────────
    $body    = json_decode(file_get_contents('php://input'), true);
    $message = trim($body['message'] ?? '');
    $history = $body['history'] ?? [];

    if (!$message) {
        http_response_code(400);
        echo json_encode(['error' => 'message required']);
        return;
    }

    // V1: Rule-based responses covering common IT support scenarios.
    // When Tazai WhatsApp/Hermes AI is fully connected, swap this for an API call.
    $reply = tazai_respond($message, $history);

    echo json_encode(['reply' => $reply, 'source' => 'tazai-v1']);
}

/**
 * Look up an email address in the Tazai employee registry.
 * Returns true only if the employee exists AND authorized = 1.
 * No employee data is ever returned to the caller.
 */
function tazai_is_authorized_employee($email) {
    if (!defined('TAZAI_DB_PATH') || !file_exists(TAZAI_DB_PATH)) {
        // If we can't reach the DB, fail closed (deny by default).
        return false;
    }

    try {
        $pdo = new PDO('sqlite:' . TAZAI_DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $stmt = $pdo->prepare(
            'SELECT authorized FROM employees WHERE LOWER(email) = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row && (int) $row['authorized'] === 1;
    } catch (Exception $e) {
        // Fail closed — DB errors deny access, never expose exception detail.
        return false;
    }
}

/** Match a support message to a rule-based response; history is reserved for AI use. */
function tazai_respond($msg, $history) {
    $m = strtolower($msg);

    // Greetings
    if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)/i', $m)) {
        return "Hi there! I'm Tazai, your IT support assistant — think of me as the department's eager intern who actually reads the manual. 😄 What IT issue can I help you with today?";
    }

    // Password issues
    if (preg_match('/password|login|locked out|can\'t log|cannot log|access denied/i', $m)) {
        return "Password issues — I've got you. Let me check a few things first:\n\n1. Which system are you trying to access? (Email, Windows login, a specific app?)\n2. Are you seeing a specific error message?\n3. Have you tried the 'Forgot Password' option if available?\n\nIf it's a Windows domain account lockout, I'll need to escalate to the IT team to unlock it. Let me know the details and I'll get this sorted!";
    }

    // Network/internet/WiFi
    if (preg_match('/wifi|internet|network|connected but|no internet|slow connection|vpn/i', $m)) {
        return "Network issues — let's troubleshoot step by step!\n\n1. Are other devices on the same WiFi also affected?\n2. Can you try disconnecting from WiFi and reconnecting?\n3. Is your VPN running? Try turning it off temporarily.\n4. If on a laptop, try plugging in via ethernet cable.\n\nIf none of those work, I'll log a ticket for the IT team to check the network equipment. What does your situation look like?";
    }

    // Email issues
    if (preg_match('/email|outlook|mail|sync|not receiving|inbox/i', $m)) {
        return "Email troubles — let me walk you through this!\n\n1. Is Outlook showing an error at the bottom of the screen?\n2. Try: File → Account Settings → Test Email Account Settings\n3. Check if you can access your email via webmail (browser) — if yes, it's likely a local Outlook issue.\n\nWhat version of Outlook are you using, and what exactly is it doing (or not doing)?";
    }

    // Printer
    if (preg_match('/print|printer|scanner|scan|paper jam|not printing/i', $m)) {
        return "Printer problems — classic! Let's go through the checklist:\n\n1. Is the printer showing as online in Windows? (Control Panel → Devices and Printers)\n2. Try deleting the print queue: open the printer → see pending jobs → clear them all\n3. Restart the Print Spooler: search 'Services' → find 'Print Spooler' → Restart\n4. Paper jam? Open all the printer doors and gently remove any stuck paper.\n\nWhich printer model is it and where is it located?";
    }

    // Laptop/computer hardware
    if (preg_match('/laptop|computer|slow|freezing|not turning|black screen|blue screen|crash/i', $m)) {
        return "Hardware issue — I want to make sure I get this right before logging a ticket.\n\n1. When did this start? After an update, after a fall, or randomly?\n2. Is it completely unresponsive or just slow?\n3. Any error messages or error codes on screen?\n4. Have you tried a full shutdown and restart (not just sleep)?\n\nDepending on the answers, this might need the IT team hands-on — but let's rule out the easy fixes first!";
    }

    // Software installation
    if (preg_match('/install|software|application|app|download|update/i', $m)) {
        return "Software installation requests need to go through the IT team for approval — we want to make sure everything installed is licensed and safe for the network.\n\nLet me log a formal request for you. I'll need:\n1. What software do you need installed?\n2. What's it for? (Brief description)\n3. Is it urgent?\n\nI'll create a ticket and the IT team will review and install it for you.";
    }

    // Access/permissions
    if (preg_match('/access|permission|folder|shared drive|can\'t open|denied/i', $m)) {
        return "Access permission issues — these usually need IT team intervention since they involve Active Directory or file server settings.\n\nTo log this properly, I need:\n1. What exactly are you trying to access? (Folder path, system name)\n2. What error message are you seeing?\n3. Did you previously have access, or is this a new request?\n\nI'll get a ticket raised with the right details so the team can sort it quickly!";
    }

    // Ticket creation request
    if (preg_match('/ticket|log|raise|report an? issue|help me/i', $m)) {
        return "I'll log a ticket for you right away! To create it properly, I just need a few details:\n\n1. What's the issue? (Brief description)\n2. How urgent is it? (Blocking your work / slowing you down / general inquiry)\n3. Your name and department\n\nOnce I have those, I'll submit it and you'll get a ticket number to track progress.";
    }

    // Navision/ERP
    if (preg_match('/navision|nav|erp|dynamics/i', $m)) {
        return "Microsoft Navision issues — I want to be upfront: I'm still learning the full Navision setup here at Tazama. Let me log this for the senior IT team who knows the system well.\n\nCan you describe:\n1. What were you trying to do in Navision?\n2. What error or problem occurred?\n3. Is it affecting just you or others too?\n\nI'll escalate this as a priority ticket!";
    }

    // Catch-all with empathy
    $responses = [
        "Got it — I want to make sure I help you properly. Can you tell me a bit more about what's happening? Specifically: what were you doing when the issue started, and what does the error or problem look like?",
        "I'm on it! To make sure I either solve this or log the right ticket for the IT team, can you give me a few more details about the issue?",
        "Let me look into this for you. I'm relatively new but I take every issue seriously! Can you describe the problem in a bit more detail — what system, what error, and when it started?",
    ];

    return $responses[array_rand($responses)];
}
