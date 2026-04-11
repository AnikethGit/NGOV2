<?php
/**
 * CSRF Token Generator
 * IMPORTANT: Session cookie params MUST match auth.php exactly,
 * otherwise a new session is created and the token never validates.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Start session with same params as auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

try {
    // Generate and store token in the shared session
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token']      = $token;
    $_SESSION['csrf_token_time'] = time();

    echo json_encode([
        'success'    => true,
        'csrf_token' => $token
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate security token'
    ]);
    exit;
}
?>