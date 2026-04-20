<?php
/**
 * api/initiate-payment.php
 * Paytm v3 Integration — Initiate Transaction API
 *
 * Flow:
 *  1. Fetch donation from DB (never trust client amount)
 *  2. Build JSON body + generate checksum (Paytm's snippet)
 *  3. Call /theia/api/v1/initiateTransaction via cURL
 *  4. Return txnToken + mid + orderId to frontend
 *  5. Frontend loads Paytm JS SDK and opens inline popup
 */

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/PaytmChecksum.php';

header('Content-Type: application/json');

// Light CSRF presence check (primary validation is on donations.php)
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

    $amount      = number_format((float) $donation['amount'], 2, '.', '');
    $customer_id = 'CUST_' . ($donation['user_id'] ?? $donation['id']);
    $mobile      = preg_replace('/[^0-9]/', '', $donation['donor_phone'] ?? '');
    $email       = $donation['donor_email'] ?? '';

    // ── Step 1: Build request body ────────────────────────────────────────────
    $paytmParams = [];

    $paytmParams['body'] = [
        'requestType' => 'Payment',
        'mid'         => PAYTM_MID,
        'websiteName' => PAYTM_WEBSITE,
        'orderId'     => $transaction_id,
        'callbackUrl' => PAYTM_CALLBACK_URL,
        'txnAmount'   => [
            'value'    => $amount,
            'currency' => 'INR',
        ],
        'userInfo' => [
            'custId' => $customer_id,
            'mobile' => $mobile,
            'email'  => $email,
        ],
    ];

    // ── Step 2: Generate checksum (Paytm official snippet) ────────────────────
    $checksum = PaytmChecksum::generateSignature(
        json_encode($paytmParams['body'], JSON_UNESCAPED_SLASHES),
        PAYTM_MERCHANT_KEY
    );

    $paytmParams['head'] = [
        'signature' => $checksum,
    ];

    // ── Step 3: Call Paytm initiateTransaction API via cURL ───────────────────
    // Staging:    https://securestage.paytmpayments.com/theia/api/v1/initiateTransaction?mid=MID&orderId=ORDER_ID
    // Production: https://secure.paytmpayments.com/theia/api/v1/initiateTransaction?mid=MID&orderId=ORDER_ID
    $apiBase = (PAYTM_ENV === 'PROD')
        ? 'https://secure.paytmpayments.com'
        : 'https://securestage.paytmpayments.com';

    $apiUrl  = $apiBase . '/theia/api/v1/initiateTransaction'
             . '?mid=' . urlencode(PAYTM_MID)
             . '&orderId=' . urlencode($transaction_id);

    $postBody = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postBody,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postBody),
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response   = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_error) {
        error_log('Paytm cURL error: ' . $curl_error);
        echo json_encode(['success' => false, 'message' => 'Network error contacting Paytm. Please try again.']);
        exit;
    }

    $result = json_decode($response, true);

    error_log('Paytm initiateTransaction response [' . $http_code . ']: ' . $response);

    // ── Step 4: Extract txnToken ──────────────────────────────────────────────
    $resultStatus = $result['body']['resultInfo']['resultStatus'] ?? '';
    $resultCode   = $result['body']['resultInfo']['resultCode']   ?? '';
    $resultMsg    = $result['body']['resultInfo']['resultMsg']    ?? 'Unknown error';
    $txnToken     = $result['body']['txnToken']                   ?? '';

    if ($resultStatus !== 'S' || empty($txnToken)) {
        error_log('Paytm initiateTransaction failed: ' . $resultMsg . ' (code: ' . $resultCode . ')');
        echo json_encode([
            'success' => false,
            'message' => 'Paytm error: ' . $resultMsg . ' (code: ' . $resultCode . ')',
        ]);
        exit;
    }

    // ── Step 5: Return token to frontend ──────────────────────────────────────
    echo json_encode([
        'success'      => true,
        'txnToken'     => $txnToken,
        'mid'          => PAYTM_MID,
        'order_id'     => $transaction_id,
        'amount'       => $amount,
        'callback_url' => PAYTM_CALLBACK_URL,
        'env'          => PAYTM_ENV,
    ]);

} catch (Exception $e) {
    error_log('Paytm Initiate Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment initiation failed. Please try again.']);
}
