<?php
/**
 * api/payment-callback.php
 *
 * Handles TWO types of requests from Paytm:
 *
 *  1. BROWSER REDIRECT  — user is sent here after completing payment on Paytm
 *     Paytm POSTs params in the request body AND the user’s browser follows.
 *     We redirect the user to a success/failure page.
 *
 *  2. SERVER WEBHOOK  — Paytm’s servers POST to this URL silently (no browser)
 *     The ‘User-Agent’ header is ‘Paytm’ and there is no browser session.
 *     We must respond with HTTP 200 + plain text ‘OK’ (not a redirect).
 *
 * Set in Paytm dashboard — Payment Notification URL:
 *   https://sadgurubharadwaja.org/api/payment-callback.php
 */

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';
require_once '../includes/PaytmChecksum.php';
require_once '../includes/receipt-service.php';

// ── Detect whether this is a server-to-server webhook or a browser redirect ──
// Paytm webhook User-Agent contains ‘Paytm’; browser redirects won’t.
$ua          = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_webhook  = stripos($ua, 'Paytm') !== false
            || empty($_SERVER['HTTP_ACCEPT'])          // no Accept header = not a browser
            || ($_SERVER['HTTP_ACCEPT'] ?? '') === '*/*';

// ── Helper: send JSON response for webhook, redirect for browser ──
function endRequest(bool $is_webhook, string $redirect_url, string $json_status = 'ok', string $msg = ''): void {
    if ($is_webhook) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => $json_status, 'message' => $msg]);
    } else {
        header('Location: ' . $redirect_url);
    }
    exit;
}

$db            = Database::getInstance();
$paytm_params  = $_POST;
$received_checksum = $paytm_params['CHECKSUMHASH'] ?? '';
$order_id      = trim($paytm_params['ORDERID']     ?? '');
$txn_status    = $paytm_params['STATUS']      ?? 'FAILED';
$paytm_txn_id  = $paytm_params['TXNID']       ?? '';
$response_code = $paytm_params['RESPCODE']    ?? '';
$response_msg  = $paytm_params['RESPMSG']     ?? '';
$bank_txn_id   = $paytm_params['BANKTXNID']  ?? '';
$payment_mode  = $paytm_params['PAYMENTMODE'] ?? '';
$txn_amount    = $paytm_params['TXNAMOUNT']   ?? '0';

error_log('[Paytm Callback] is_webhook=' . ($is_webhook ? 'yes' : 'no') . ' order_id=' . $order_id . ' params=' . json_encode($paytm_params));

if (empty($order_id)) {
    endRequest($is_webhook, '/donate.html?error=invalid_callback', 'error', 'Missing ORDERID');
}

// ── Step 1: Verify Checksum ─────────────────────────────────────────────
unset($paytm_params['CHECKSUMHASH']);
$is_valid = PaytmChecksum::verifySignature($paytm_params, PAYTM_MERCHANT_KEY, $received_checksum);

if (!$is_valid) {
    error_log('[Paytm Callback] Checksum FAILED for order: ' . $order_id);
    $db->query("UPDATE donations SET payment_status='failed', updated_at=NOW() WHERE transaction_id=?", [$order_id]);
    endRequest($is_webhook, '/donate.html?error=checksum_failed', 'error', 'Checksum verification failed');
}

// ── Step 2: Verify with Paytm API (server-side double-check) ──────────────────
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
$curl_error      = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log('[Paytm Callback] cURL error verifying ' . $order_id . ': ' . $curl_error);
    // Don’t fail hard — fall through using callback data only
}

$verified_status = $verify_response['body']['resultInfo']['resultStatus'] ?? $txn_status;
$verified_txn_id = $verify_response['body']['txnId']     ?? $paytm_txn_id;
$verified_amount = $verify_response['body']['txnAmount'] ?? $txn_amount;

error_log('[Paytm Callback] Verify result for ' . $order_id . ': ' . $verified_status);

// ── Fetch original donation record ──────────────────────────────────────
$donation = $db->fetch("SELECT * FROM donations WHERE transaction_id=? LIMIT 1", [$order_id]);
if (!$donation) {
    error_log('[Paytm Callback] Donation not found: ' . $order_id);
    endRequest($is_webhook, '/donate.html?error=order_not_found', 'error', 'Order not found');
}

// ── Idempotency guard: already completed ──────────────────────────────────
if ($donation['payment_status'] === 'completed') {
    error_log('[Paytm Callback] Duplicate callback for already-completed order: ' . $order_id);
    endRequest($is_webhook, "/payment-success.html?txn={$order_id}&status=success", 'ok', 'Already completed');
}

// ════════════════════════════════════════════════════════════
// Route by payment result
// ════════════════════════════════════════════════════════════
if ($verified_status === 'TXN_SUCCESS' && $txn_status === 'TXN_SUCCESS') {

    // Security: cross-check amount (prevent partial-payment attacks)
    if ((float)$verified_amount < (float)$donation['amount']) {
        error_log("[Paytm Callback] Amount mismatch for {$order_id}: expected {$donation['amount']}, got {$verified_amount}");
        $db->query("UPDATE donations SET payment_status='failed', updated_at=NOW() WHERE transaction_id=?", [$order_id]);
        endRequest($is_webhook, '/donate.html?error=amount_mismatch', 'error', 'Amount mismatch');
    }

    // 1. Update donation record
    $db->query(
        "UPDATE donations SET
            payment_status        = 'completed',
            paytm_order_id        = ?,
            paytm_transaction_id  = ?,
            payment_mode          = ?,
            bank_txn_id           = ?,
            paytm_response_code   = ?,
            paytm_response_msg    = ?,
            updated_at            = NOW()
         WHERE transaction_id = ?",
        [$order_id, $verified_txn_id, $payment_mode, $bank_txn_id, $response_code, $response_msg, $order_id]
    );

    // Refresh donation row
    $donation = $db->fetch("SELECT * FROM donations WHERE transaction_id=? LIMIT 1", [$order_id]);
    $donation['payment_mode'] = $payment_mode;

    // 2. Fetch donor’s user record
    // IMPORTANT: live DB column is full_name, not name
    $user = [];
    if (!empty($donation['user_id'])) {
        $user = $db->fetch(
            "SELECT id, full_name, email, phone, address, pan_number FROM users WHERE id=? LIMIT 1",
            [$donation['user_id']]
        ) ?? [];
        // Normalise: receipt-service expects 'name' key
        if (!empty($user)) $user['name'] = $user['full_name'] ?? '';
    }

    // Guest donation (no user_id) — use fields stored on donation row
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

    // 3. Dispatch receipt (email + SMS)
    ReceiptService::dispatch($donation, $user);

    // 4. Log
    $logger = new Logger();
    $logger->log(
        $donation['user_id'] ?? null,
        'payment_success',
        "Payment completed for {$order_id}. Amount: ₹{$verified_amount}",
        'donation',
        $donation['id']
    );

    endRequest(
        $is_webhook,
        "/payment-success.html?txn={$order_id}&status=success&amount={$verified_amount}",
        'ok',
        'Payment successful'
    );

} elseif ($verified_status === 'PENDING' || $txn_status === 'PENDING') {

    $db->query(
        "UPDATE donations SET payment_status='pending', paytm_transaction_id=?, updated_at=NOW() WHERE transaction_id=?",
        [$verified_txn_id, $order_id]
    );

    $logger = new Logger();
    $logger->log($donation['user_id'] ?? null, 'payment_pending', "Payment pending for {$order_id}", 'donation', $donation['id']);

    endRequest(
        $is_webhook,
        "/payment-success.html?txn={$order_id}&status=pending",
        'ok',
        'Payment pending'
    );

} else {

    $db->query(
        "UPDATE donations SET payment_status='failed', paytm_transaction_id=?, paytm_response_code=?, paytm_response_msg=?, updated_at=NOW() WHERE transaction_id=?",
        [$verified_txn_id, $response_code, $response_msg, $order_id]
    );

    $logger = new Logger();
    $logger->log($donation['user_id'] ?? null, 'payment_failed', "Payment failed for {$order_id}: {$response_msg}", 'donation', $donation['id']);

    endRequest(
        $is_webhook,
        "/donate.html?error=payment_failed&code={$response_code}",
        'error',
        'Payment failed: ' . $response_msg
    );
}
