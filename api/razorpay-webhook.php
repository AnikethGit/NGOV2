<?php
/**
 * Razorpay Webhook Handler
 * Handles: payment.captured, payment.failed, refund.processed
 *
 * Register in Razorpay Dashboard → Settings → Webhooks:
 *   URL: https://sadgurubharadwaja.org/api/razorpay-webhook.php
 *   Events: payment.captured, payment.failed, refund.processed
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/receipt-service.php';

// ── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// ── Read raw body ────────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    exit('Empty body');
}

// ── Verify Razorpay signature ────────────────────────────────────────────────
$receivedSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$webhookSecret     = RAZORPAY_WEBHOOK_SECRET;

if (empty($webhookSecret)) {
    error_log('[Razorpay Webhook] RAZORPAY_WEBHOOK_SECRET not set in .env');
    http_response_code(500);
    exit('Webhook secret not configured');
}

$expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);
if (!hash_equals($expectedSignature, $receivedSignature)) {
    error_log('[Razorpay Webhook] Signature mismatch');
    http_response_code(400);
    exit('Invalid signature');
}

// ── Parse payload ────────────────────────────────────────────────────────────
$event = json_decode($rawBody, true);
if (!$event || empty($event['event'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$eventName = $event['event'];
$db        = Database::getInstance();
$logger    = new Logger();

error_log('[Razorpay Webhook] Event: ' . $eventName);

// ── payment.captured ─────────────────────────────────────────────────────────
if ($eventName === 'payment.captured') {
    $payment   = $event['payload']['payment']['entity'] ?? [];
    $rzpPayId  = $payment['id']       ?? '';
    $rzpOrdId  = $payment['order_id'] ?? '';

    if (empty($rzpOrdId)) {
        http_response_code(400);
        exit('Missing order_id');
    }

    // Only update if still pending — avoid overwriting a verified payment
    $donation = $db->fetch(
        'SELECT id, user_id, amount, payment_status FROM donations WHERE razorpay_order_id = ? LIMIT 1',
        [$rzpOrdId]
    );

    if ($donation && $donation['payment_status'] === 'pending') {
        $db->query(
            'UPDATE donations
             SET payment_status = ?, razorpay_payment_id = ?, payment_gateway = ?, payment_mode = ?, updated_at = NOW()
             WHERE razorpay_order_id = ?',
            ['completed', $rzpPayId, 'razorpay', 'Online', $rzpOrdId]
        );
        $logger->log(
            $donation['user_id'],
            'payment_captured_webhook',
            "Payment captured via webhook — ₹{$donation['amount']}",
            'donation',
            $donation['id'],
            ['razorpay_payment_id' => $rzpPayId, 'razorpay_order_id' => $rzpOrdId]
        );
        error_log("[Razorpay Webhook] Donation {$donation['id']} marked completed via webhook");

        // Dispatch receipt only if verify-payment.php hasn't already done so (idempotency via receipt_number)
        $fresh = $db->fetch('SELECT * FROM donations WHERE id = ? LIMIT 1', [$donation['id']]);
        if ($fresh && empty($fresh['receipt_number'])) {
            try {
                $user = [];
                if (!empty($fresh['user_id'])) {
                    $row = $db->fetch(
                        'SELECT id, full_name, email, phone, address, pan_number FROM users WHERE id = ? LIMIT 1',
                        [$fresh['user_id']]
                    );
                    if ($row) {
                        $user = $row;
                        $user['name'] = $row['full_name'] ?? '';
                    }
                }
                if (empty($user)) {
                    $user = [
                        'name'       => $fresh['donor_name']    ?? 'Donor',
                        'full_name'  => $fresh['donor_name']    ?? 'Donor',
                        'email'      => $fresh['donor_email']   ?? '',
                        'phone'      => $fresh['donor_phone']   ?? '',
                        'pan_number' => $fresh['donor_pan']     ?? '',
                        'address'    => $fresh['donor_address'] ?? '',
                    ];
                }
                ReceiptService::dispatch($fresh, $user);
            } catch (Throwable $e) {
                error_log('[Razorpay Webhook] ReceiptService error: ' . $e->getMessage());
            }
        }
    }

    http_response_code(200);
    exit('ok');
}

// ── payment.failed ───────────────────────────────────────────────────────────
if ($eventName === 'payment.failed') {
    $payment  = $event['payload']['payment']['entity'] ?? [];
    $rzpOrdId = $payment['order_id'] ?? '';
    $errDesc  = $payment['error_description'] ?? 'Unknown error';

    if (empty($rzpOrdId)) {
        http_response_code(400);
        exit('Missing order_id');
    }

    $donation = $db->fetch(
        'SELECT id, user_id, amount, payment_status FROM donations WHERE razorpay_order_id = ? LIMIT 1',
        [$rzpOrdId]
    );

    if ($donation && $donation['payment_status'] === 'pending') {
        $db->query(
            'UPDATE donations
             SET payment_status = ?, updated_at = NOW()
             WHERE razorpay_order_id = ?',
            ['failed', $rzpOrdId]
        );
        $logger->log(
            $donation['user_id'],
            'payment_failed_webhook',
            "Payment failed via webhook — ₹{$donation['amount']} — {$errDesc}",
            'donation',
            $donation['id'],
            ['razorpay_order_id' => $rzpOrdId, 'error' => $errDesc]
        );
        error_log("[Razorpay Webhook] Donation {$donation['id']} marked failed via webhook");
    }

    http_response_code(200);
    exit('ok');
}

// ── refund.processed ─────────────────────────────────────────────────────────
if ($eventName === 'refund.processed') {
    $refund   = $event['payload']['refund']['entity'] ?? [];
    $rzpPayId = $refund['payment_id'] ?? '';

    if (empty($rzpPayId)) {
        http_response_code(400);
        exit('Missing payment_id');
    }

    $donation = $db->fetch(
        'SELECT id, user_id, amount, payment_status FROM donations WHERE razorpay_payment_id = ? LIMIT 1',
        [$rzpPayId]
    );

    if ($donation && $donation['payment_status'] === 'completed') {
        $db->query(
            'UPDATE donations
             SET payment_status = ?, updated_at = NOW()
             WHERE razorpay_payment_id = ?',
            ['refunded', $rzpPayId]
        );
        $logger->log(
            $donation['user_id'],
            'refund_processed_webhook',
            "Refund processed via webhook — ₹{$donation['amount']}",
            'donation',
            $donation['id'],
            ['razorpay_payment_id' => $rzpPayId]
        );
        error_log("[Razorpay Webhook] Donation {$donation['id']} marked refunded via webhook");
    }

    http_response_code(200);
    exit('ok');
}

// ── Unhandled event — still return 200 so Razorpay doesn't retry ─────────────
http_response_code(200);
exit('unhandled event');
