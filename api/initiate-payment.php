<?php
/**
 * api/initiate-payment.php
 * Initiates a Paytm payment after donation record is created.
 * Called by donation-handler.js after successful form submission.
 */

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/PaytmChecksum.php';

header('Content-Type: application/json');

// NOTE: Do NOT call session_start() here.
// Security::validateCSRFToken() will internally start the shared
// NGOV2_SESSION (via Security::ensureSession) using the same cookie
// settings as api/csrf-token.php, so it can see the token that was
// previously stored in the session.

// CSRF check
$csrfToken = $_POST['csrf_token'] ?? '';
if (!Security::validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$transaction_id = trim($_POST['transaction_id'] ?? '');
if (empty($transaction_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction ID']);
    exit;
}

try {
    $db = Database::getInstance();

    // Always fetch amount from DB — never trust client-side values
    $donation = $db->fetch(
        "SELECT * FROM donations WHERE transaction_id = ? AND payment_status = 'pending' LIMIT 1",
        [$transaction_id]
    );

    if (!$donation) {
        echo json_encode(['success' => false, 'message' => 'Donation record not found or already processed']);
        exit;
    }

    $amount      = number_format((float)$donation['amount'], 2, '.', '');
    $customer_id = 'CUST_' . ($donation['user_id'] ?? $donation['id']);
    $mobile      = preg_replace('/[^0-9]/', '', $donation['donor_phone'] ?? '');
    $email       = $donation['donor_email'] ?? '';

    // Build Paytm parameter array
    $paytm_params = [
        'MID'              => PAYTM_MID,
        'WEBSITE'          => PAYTM_WEBSITE,
        'CHANNEL_ID'       => 'WEB',
        'INDUSTRY_TYPE_ID' => 'Ecommerce',
        'ORDER_ID'         => $transaction_id,
        'CUST_ID'          => $customer_id,
        'TXN_AMOUNT'       => $amount,
        'CALLBACK_URL'     => PAYTM_CALLBACK_URL,
        'EMAIL'            => $email,
        'MOBILE_NO'        => $mobile,
    ];

    // Generate Checksum
    $checksum_hash = PaytmChecksum::generateSignature($paytm_params, PAYTM_MERCHANT_KEY);
    $paytm_params['CHECKSUMHASH'] = $checksum_hash;

    echo json_encode([
        'success'      => true,
        'paytm_params' => $paytm_params,
        'paytm_url'    => PAYTM_TXN_URL,
        'order_id'     => $transaction_id,
        'amount'       => $amount
    ]);

} catch (Exception $e) {
    error_log('Paytm Initiate Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment initiation failed. Please try again.']);
}
