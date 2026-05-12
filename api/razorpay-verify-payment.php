<?php
/**
 * Razorpay Payment Verification
 * Called by donation-handler.js after Razorpay checkout modal closes successfully.
 * Verifies HMAC-SHA256 signature, updates donation status, dispatches receipt email, returns redirect URL.
 */

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/receipt-service.php';

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
$payload_str        = $razorpay_order_id . '|' . $razorpay_payment_id;
$expected_signature = hash_hmac('sha256', $payload_str, RAZORPAY_KEY_SECRET);

if (!hash_equals($expected_signature, $razorpay_signature)) {
    try {
        $db = Database::getInstance();
        $db->query(
            'UPDATE donations SET status = ? WHERE transaction_id = ?',
            ['failed', $transaction_id]
        );
    } catch (Exception $e) { /* ignore */ }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment signature verification failed']);
    exit;
}

// Signature valid — mark donation as completed
try {
    $db = Database::getInstance();
    $db->query(
        'UPDATE donations
         SET payment_status = ?, payment_gateway = ?, razorpay_payment_id = ?, payment_mode = ?, updated_at = NOW()
         WHERE transaction_id = ?',
        ['completed', 'razorpay', $razorpay_payment_id, 'Online', $transaction_id]
    );
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB update failed: ' . $e->getMessage()]);
    exit;
}

// Dispatch donation receipt (email + SMS) after successful payment
try {
    $donation = $db->fetch('SELECT * FROM donations WHERE transaction_id = ? LIMIT 1', [$transaction_id]);

    if ($donation) {
        $user = [];
        if (!empty($donation['user_id'])) {
            $row = $db->fetch(
                'SELECT id, full_name, email, phone, address, pan_number FROM users WHERE id = ? LIMIT 1',
                [$donation['user_id']]
            );
            if ($row) {
                $user = $row;
                $user['name'] = $row['full_name'] ?? '';
            }
        }

        // Guest donation — use fields stored on the donation row
        if (empty($user)) {
            $user = [
                'name'       => $donation['donor_name']    ?? 'Donor',
                'full_name'  => $donation['donor_name']    ?? 'Donor',
                'email'      => $donation['donor_email']   ?? '',
                'phone'      => $donation['donor_phone']   ?? '',
                'pan_number' => $donation['donor_pan']     ?? '',
                'address'    => $donation['donor_address'] ?? '',
            ];
        }

        ReceiptService::dispatch($donation, $user);
    }
} catch (Throwable $e) {
    // Never let receipt dispatch block the response
    error_log('[razorpay-verify] ReceiptService error: ' . $e->getMessage());
}

echo json_encode([
    'success'             => true,
    'transaction_id'      => $transaction_id,
    'razorpay_payment_id' => $razorpay_payment_id,
    'redirect'            => 'payment-success.html?txn=' . urlencode($transaction_id) . '&gateway=razorpay',
]);
