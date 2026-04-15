<?php
/**
 * api/chargeback-notify.php
 *
 * Handles Paytm chargeback webhooks (server-to-server POST).
 * Set in your Paytm dashboard:
 *   Charge Back Notify  -> https://sadgurubharadwaja.org/api/chargeback-notify.php
 *
 * A chargeback = donor’s bank reversed the payment.
 * This is rare but needs to be logged and the donation marked accordingly.
 */

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';
require_once '../includes/PaytmChecksum.php';

header('Content-Type: application/json');
http_response_code(200);

$db       = Database::getInstance();
$params   = $_POST;
$checksum = $params['CHECKSUMHASH'] ?? '';
$order_id = trim($params['ORDERID'] ?? '');
$cb_id    = $params['CHARGEBACKID'] ?? '';
$cb_amt   = $params['CHARGEBACKAMOUNT'] ?? '0';
$status   = $params['STATUS']  ?? '';
$reason   = $params['REASON']  ?? '';

error_log('[Paytm Chargeback] Received: ' . json_encode($params));

if (empty($order_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ORDERID']);
    exit;
}

// ── Verify Checksum ─────────────────────────────────────────────────
unset($params['CHECKSUMHASH']);
$is_valid = PaytmChecksum::verifySignature($params, PAYTM_MERCHANT_KEY, $checksum);

if (!$is_valid) {
    error_log('[Paytm Chargeback] Checksum FAILED for order: ' . $order_id);
    echo json_encode(['status' => 'error', 'message' => 'Checksum failed']);
    exit;
}

// ── Find the original donation ──────────────────────────────────────────
$donation = $db->fetch("SELECT * FROM donations WHERE transaction_id=? LIMIT 1", [$order_id]);
if (!$donation) {
    error_log('[Paytm Chargeback] Donation not found: ' . $order_id);
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

// ── Mark donation as chargebacked + log ───────────────────────────────────
// Update donation status to 'chargebacked'
$db->query(
    "UPDATE donations SET payment_status='chargebacked', updated_at=NOW() WHERE transaction_id=?",
    [$order_id]
);

$logger = new Logger();
$logger->log(
    $donation['user_id'] ?? null,
    'chargeback',
    "Chargeback received for {$order_id}. Amount: ₹{$cb_amt}. Chargeback ID: {$cb_id}. Reason: {$reason}",
    'donation',
    $donation['id']
);

error_log("[Paytm Chargeback] Order {$order_id} chargebacked. ID: {$cb_id}, Amount: {$cb_amt}, Reason: {$reason}");

// TODO: Alert admin by email when a chargeback occurs
// mail('admin@sadgurubharadwaja.org', 'ALERT: Chargeback Received', "Order: {$order_id}\nAmount: {$cb_amt}\nReason: {$reason}");

echo json_encode(['status' => 'ok', 'message' => 'Chargeback notification received']);
