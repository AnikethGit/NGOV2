<?php
/**
 * Razorpay Payment Verification
 * Called by donation-handler.js after the Razorpay checkout modal closes successfully.
 * Verifies HMAC-SHA256 signature, updates donation status, redirects to payment-success.html
 * Paytm files are NOT touched by this file.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$razorpay_order_id   = trim($_POST['razorpay_order_id']   ?? '');
$razorpay_payment_id = trim($_POST['razorpay_payment_id'] ?? '');
$razorpay_signature  = trim($_POST['razorpay_signature']  ?? '');
$transaction_id      = trim($_POST['transaction_id']      ?? '');

if (!$razorpay_order_id || !$razorpay_payment_id || !$razorpay_signature || !$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'Missing payment parameters']);
    exit;
}

// Verify HMAC-SHA256 signature
$payload_str       = $razorpay_order_id . '|' . $razorpay_payment_id;
$expected_signature = hash_hmac('sha256', $payload_str, RAZORPAY_KEY_SECRET);

if (!hash_equals($expected_signature, $razorpay_signature)) {
    // Signature mismatch — possible tampering
    try {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE donations SET status = ? WHERE transaction_id = ?');
        $stmt->execute(['failed', $transaction_id]);
    } catch (Exception $e) { /* ignore */ }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment signature verification failed']);
    exit;
}

// Signature valid — update donation as successful
try {
    $pdo  = Database::getConnection();
    $stmt = $pdo->prepare(
        'UPDATE donations
         SET status = ?, payment_gateway = ?, razorpay_payment_id = ?, updated_at = NOW()
         WHERE transaction_id = ?'
    );
    $stmt->execute(['completed', 'razorpay', $razorpay_payment_id, $transaction_id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB update failed: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'success'            => true,
    'transaction_id'     => $transaction_id,
    'razorpay_payment_id'=> $razorpay_payment_id,
    'redirect'           => 'payment-success.html?txn=' . urlencode($transaction_id) . '&gateway=razorpay',
]);
