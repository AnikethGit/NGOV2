<?php
/**
 * Contact Form API
 * Session config MUST match csrf-token.php exactly so both files
 * share the same PHP session (and therefore the same csrf_token value).
 */

// ── 0. No output before headers ────────────────────────────────────────────
if (ob_get_level() === 0) ob_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';

// ── 1. Start session with IDENTICAL params as csrf-token.php ───────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('NGOV2_SESSION');

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // ── 2. CSRF check ──────────────────────────────────────────────────────
    $csrfToken    = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $tokenTime    = $_SESSION['csrf_token_time'] ?? null;

    if (empty($csrfToken) || empty($sessionToken)) {
        error_log('Contact CSRF: token missing. POST=' . strlen($csrfToken) . ' SESSION=' . strlen((string)$sessionToken));
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    if ($tokenTime && (time() - $tokenTime > 3600)) {
        throw new Exception('Security token expired. Please refresh the page and try again.');
    }

    if (!hash_equals($sessionToken, $csrfToken)) {
        error_log('Contact CSRF mismatch. Expected=' . substr($sessionToken, 0, 10) . ' Got=' . substr($csrfToken, 0, 10));
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    // ── 3. Collect and validate fields ────────────────────────────────────
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $subject   = trim($_POST['subject']    ?? '');
    $message   = trim($_POST['message']    ?? '');

    if (empty($firstName) || strlen($firstName) < 2) {
        throw new Exception('First name must be at least 2 characters');
    }
    if (empty($lastName) || strlen($lastName) < 2) {
        throw new Exception('Last name must be at least 2 characters');
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }
    if (!empty($phone)) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) !== 10 || !preg_match('/^[6-9]/', $phone)) {
            throw new Exception('Phone must be a valid 10-digit Indian mobile number');
        }
    } else {
        $phone = '';
    }
    if (empty($subject)) {
        throw new Exception('Please select a subject');
    }
    if (empty($message) || strlen($message) < 10) {
        throw new Exception('Message must be at least 10 characters');
    }
    if (strlen($message) > 5000) {
        throw new Exception('Message is too long (max 5000 characters)');
    }

    // ── 4. Save to database ───────────────────────────────────────────────
    $subjectMap = [
        'general'     => 'General Inquiry',
        'donation'    => 'Donation Related',
        'volunteer'   => 'Volunteer Opportunities',
        'partnership' => 'Partnership',
        'support'     => 'Support/Help',
        'other'       => 'Other'
    ];

    $db        = Database::getInstance();
    $contactId = $db->insert('contact_messages', [
        'name'       => $firstName . ' ' . $lastName,
        'email'      => $email,
        'phone'      => $phone,
        'subject'    => $subjectMap[$subject] ?? 'General Inquiry',
        'message'    => $message,
        'status'     => 'new',
        'priority'   => 'normal',
        'ip_address' => $_SERVER['REMOTE_ADDR']    ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    if (!$contactId) {
        throw new Exception('Failed to save message to database');
    }

    // Invalidate the used token so it cannot be replayed
    unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);

    ob_end_clean();
    echo json_encode([
        'success'    => true,
        'message'    => 'Thank you for contacting us, ' . htmlspecialchars($firstName) . '! We will get back to you soon.',
        'contact_id' => $contactId
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>