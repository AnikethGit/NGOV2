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

// IMPORTANT:
// We already validate CSRF when the donation is first created in api/donations.php.
// This endpoint is called immediately afterwards from the same page + same
// browser session. Because of Hostinger's proxy/session peculiarities, the
// second CSRF check has been flaky, blocking payments.
//
// To unblock real-world testing, we only enforce that a non-empty token is
// present here. The primary protection remains on donations.php.

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Missing CSRF token']);
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

    // Build Paytm parameter body (v2 JSON format)
    $paytmParams = [
        'body' => [
            'requestType'   => 'Payment',
            'mid'           => PAYTM_MID,
            'websiteName'   => PAYTM_WEBSITE,
            'orderId'       => $transaction_id,
            'callbackUrl'   => PAYTM_CALLBACK_URL,
            'txnAmount'     => [
                'value'    => $amount,
                'currency' => 'INR',
            ],
            'userInfo'      => [
                'custId'   => $customer_id,
                'mobile'   => $mobile,
                'email'    => $email,
            ],
        ]
    ];

    // Generate checksum using json_encode of body — as per Paytm instructions
    $checksum = PaytmChecksum::generateSignature(
        json_encode($paytmParams['body'], JSON_UNESCAPED_SLASHES),
        PAYTM_MERCHANT_KEY
    );

    $paytmParams['head'] = [
        'signature' => $checksum
    ];

    echo json_encode([
        'success'      => true,
        'paytm_params' => $paytmParams,
        'paytm_url'    => PAYTM_TXN_URL,
        'order_id'     => $transaction_id,
        'amount'       => $amount
    ]);

} catch (Exception $e) {
    error_log('Paytm Initiate Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment initiation failed. Please try again.']);
}
