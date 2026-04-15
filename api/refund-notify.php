<?php
/**
 * api/refund-notify.php
 *
 * Handles Paytm refund webhooks (server-to-server POST).
 * Set BOTH of these in your Paytm dashboard:
 *   Success Refund Notify  -> https://sadgurubharadwaja.org/api/refund-notify.php
 *   Accept Refund Notify   -> https://sadgurubharadwaja.org/api/refund-notify.php
 *
 * Paytm always expects HTTP 200 + plain text response for webhooks.
 */

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';
require_once '../includes/PaytmChecksum.php';

header('Content-Type: application/json');
http_response_code(200);

$db           = Database::getInstance();
$params       = $_POST;
$checksum     = $params['CHECKSUMHASH'] ?? '';
$order_id     = trim($params['ORDERID']     ?? '');
$refund_id    = trim($params['REFUNDID']    ?? '');
$refund_amt   = $params['REFUNDAMOUNT']  ?? '0';
$resp_code    = $params['RESPCODE']      ?? '';
$resp_msg     = $params['RESPMSG']       ?? '';
$status       = $params['STATUS']        ?? '';

error_log('[Paytm Refund] Received: ' . json_encode($params));

if (empty($order_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ORDERID']);
    exit;
}

// ── Verify Checksum ─────────────────────────────────────────────────
unset($params['CHECKSUMHASH']);
$is_valid = PaytmChecksum::verifySignature($params, PAYTM_MERCHANT_KEY, $checksum);

if (!$is_valid) {
    error_log('[Paytm Refund] Checksum FAILED for order: ' . $order_id);
    echo json_encode(['status' => 'error', 'message' => 'Checksum failed']);
    exit;
}

// ── Find the original donation ──────────────────────────────────────────
$donation = $db->fetch("SELECT * FROM donations WHERE transaction_id=? LIMIT 1", [$order_id]);
if (!$donation) {
    error_log('[Paytm Refund] Donation not found: ' . $order_id);
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

// ── Update refund status ───────────────────────────────────────────────
if ($status === 'TXN_SUCCESS' || $resp_code === '10') {
    // Refund succeeded — mark donation as refunded
    $db->query(
        "UPDATE donations SET payment_status='refunded', updated_at=NOW() WHERE transaction_id=?",
        [$order_id]
    );
    $logger = new Logger();
    $logger->log(
        $donation['user_id'] ?? null,
        'refund_success',
        "Refund of ₹{$refund_amt} successful for {$order_id}. Refund ID: {$refund_id}",
        'donation',
        $donation['id']
    );
    error_log("[Paytm Refund] SUCCESS for {$order_id} — refund ID: {$refund_id} amount: ₹{$refund_amt}");
} else {
    // Refund failed or pending
    $logger = new Logger();
    $logger->log(
        $donation['user_id'] ?? null,
        'refund_failed',
        "Refund FAILED for {$order_id}. Code: {$resp_code} Msg: {$resp_msg}",
        'donation',
        $donation['id']
    );
    error_log("[Paytm Refund] FAILED for {$order_id}: {$resp_code} {$resp_msg}");
}

echo json_encode(['status' => 'ok', 'message' => 'Refund notification received']);
