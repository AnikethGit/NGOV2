<?php
/**
 * CSRF Token Generator - Stateless Version
 * Generates tokens without relying on sessions
 */

header('Content-Type: application/json');

// Generate a simple token
$token = bin2hex(random_bytes(32));

// Store in a file for this session (simple approach)
$tokenDir = sys_get_temp_dir() . '/csrf_tokens/';
if (!is_dir($tokenDir)) {
    @mkdir($tokenDir, 0755, true);
}

$tokenFile = $tokenDir . session_id() . '.token';
file_put_contents($tokenFile, $token, LOCK_EX);

echo json_encode([
    'success' => true,
    'csrf_token' => $token
]);
exit;
?>
