<?php
/**
 * CSRF Token Generator
 *
 * CRITICAL: session_name() and ALL session_set_cookie_params() values
 * MUST be identical to auth.php, otherwise PHP creates a second session
 * and the token stored here is never visible in auth.php.
 */

// ── 0. No output before headers ────────────────────────────────────────────
if (ob_get_level() === 0) ob_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
// Allow the browser to send the session cookie on the follow-up POST
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// ── 1. Start session with params that exactly match auth.php ───────────────
if (session_status() === PHP_SESSION_NONE) {
    // Use a fixed session name so both files share the same cookie
    session_name('NGOV2_SESSION');

    // On Hostinger the site is always HTTPS — hard-code true instead of
    // relying on $_SERVER['HTTPS'] which can be absent behind a proxy.
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'   // Lax (not Strict) so the cookie is sent on
                               // same-site POST requests (login form submit)
    ]);
    session_start();
}

try {
    // Generate a fresh token and store it in the session
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token']      = $token;
    $_SESSION['csrf_token_time'] = time();

    // Flush any accidental output before our JSON
    ob_end_clean();

    echo json_encode([
        'success'    => true,
        'csrf_token' => $token
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate security token'
    ]);
    exit;
}
