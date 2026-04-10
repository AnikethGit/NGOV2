<?php
/**
 * CSRF Token Generator - Session-based Version
 * Generates tokens using Security helper and PHP sessions
 */

header('Content-Type: application/json');

require_once '../includes/security.php';

try {
    $token = Security::generateCSRFToken();

    echo json_encode([
        'success' => true,
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