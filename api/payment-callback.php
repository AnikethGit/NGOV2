<?php
/**
 * api/payment-callback.php
 * Handles Paytm's POST callback after payment.
 * Set this as your Callback URL in the Paytm dashboard.
 */

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';
require_once '../includes/PaytmChecksum.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db           = Database::getInstance();
$paytm_params = $_POST;
$received_checksum = $paytm_params['CHECKSUMHASH'] ?? '';
$order_id     = $paytm_params['ORDERID']    ?? '';
$txn_status   = $paytm_params['STATUS']     ?? 'FAILED';
$paytm_txn_id = $paytm_params['TXNID']      ?? '';
$response_code = $paytm_params['RESPCODE']  ?? '';
$response_msg  = $paytm_params['RESPMSG']   ?? '';
$bank_txn_id   = $paytm_params['BANKTXNID'] ?? '';
$payment_mode  = $paytm_params['PAYMENTMODE'] ?? '';
$txn_amount    = $paytm_params['TXNAMOUNT'] ?? '0';

error_log('Paytm Callback: ' . json_encode($paytm_params));

if (empty($order_id)) {
    header('Location: /donate.html?error=invalid_callback');
    exit;
}

// Step 1: Verify Checksum (critical security step)
unset($paytm_params['CHECKSUMHASH']);
$is_valid = PaytmChecksum::verifySignature($paytm_params, PAYTM_MERCHANT_KEY, $received_checksum);

if (!$is_valid) {
    error_log('Paytm Checksum FAILED for order: ' . $order_id);
    $db->query("UPDATE donations SET payment_status='failed', updated_at=NOW() WHERE transaction_id=?", [$order_id]);
    header('Location: /donate.html?error=checksum_failed');
    exit;
}

// Step 2: Verify with Paytm API (never rely on redirect alone)
$verify_params = ['MID' => PAYTM_MID, 'ORDERID' => $order_id];
$verify_checksum = PaytmChecksum::generateSignature($verify_params, PAYTM_MERCHANT_KEY);
$verify_params['CHECKSUMHASH'] = $verify_checksum;
$post_data = json_encode(['body' => $verify_params]);

$ch = curl_init(PAYTM_STATUS_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post_data,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($post_data)],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$verify_response = json_decode(curl_exec($ch), true);
curl_close($ch);

$verified_status = $verify_response['body']['resultInfo']['resultStatus'] ?? 'F';
$verified_txn_id = $verify_response['body']['txnId']      ?? $paytm_txn_id;
$verified_amount = $verify_response['body']['txnAmount']  ?? '0';

error_log('Paytm Verify for ' . $order_id . ': ' . $verified_status);

// Fetch original donation
$donation = $db->fetch("SELECT * FROM donations WHERE transaction_id=? LIMIT 1", [$order_id]);
if (!$donation) {
    header('Location: /donate.html?error=order_not_found');
    exit;
}

// Prevent double-processing
if ($donation['payment_status'] === 'completed') {
    header("Location: /payment-success.html?txn={$order_id}&status=success");
    exit;
}

if ($verified_status === 'TXN_SUCCESS' && $txn_status === 'TXN_SUCCESS') {

    // Cross-check amount (security: prevent partial payment)
    if ((float)$verified_amount < (float)$donation['amount']) {
        error_log("Amount mismatch for {$order_id}: expected {$donation['amount']}, got {$verified_amount}");
        $db->query("UPDATE donations SET payment_status='failed', updated_at=NOW() WHERE transaction_id=?", [$order_id]);
        header('Location: /donate.html?error=amount_mismatch');
        exit;
    }

    $db->query(
        "UPDATE donations SET
            payment_status='completed',
            paytm_order_id=?,
            paytm_transaction_id=?,
            payment_mode=?,
            bank_txn_id=?,
            paytm_response_code=?,
            paytm_response_msg=?,
            updated_at=NOW()
         WHERE transaction_id=?",
        [$order_id, $verified_txn_id, $payment_mode, $bank_txn_id, $response_code, $response_msg, $order_id]
    );

    $logger = new Logger();
    $logger->log($donation['user_id'] ?? null, 'payment_success',
        "Payment completed for {$order_id}. Amount: ₹{$verified_amount}", 'donation', $donation['id']);

    header("Location: /payment-success.html?txn={$order_id}&status=success&amount={$verified_amount}");
    exit;

} elseif ($verified_status === 'PENDING' || $txn_status === 'PENDING') {
    $db->query("UPDATE donations SET payment_status='pending', paytm_transaction_id=?, updated_at=NOW() WHERE transaction_id=?",
        [$verified_txn_id, $order_id]);
    header("Location: /payment-success.html?txn={$order_id}&status=pending");
    exit;

} else {
    $db->query("UPDATE donations SET payment_status='failed', paytm_transaction_id=?, paytm_response_code=?, paytm_response_msg=?, updated_at=NOW() WHERE transaction_id=?",
        [$verified_txn_id, $response_code, $response_msg, $order_id]);
    header("Location: /donate.html?error=payment_failed&code={$response_code}");
    exit;
}
