<?php
/**
 * Razorpay Create Order
 * Called by donation-handler.js when ACTIVE_GATEWAY = 'razorpay'
 * Creates a Razorpay order and returns order_id + key_id to the frontend.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';
require_once '../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$transaction_id = trim($_POST['transaction_id'] ?? '');
if (empty($transaction_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction_id']);
    exit;
}

// Fetch donation from DB
try {
    $db       = Database::getInstance();
    $donation = $db->fetch(
        'SELECT amount, donor_name, donor_email, donor_phone FROM donations WHERE transaction_id = ? LIMIT 1',
        [$transaction_id]
    );

    if (!$donation) {
        echo json_encode(['success' => false, 'message' => 'Donation record not found']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$amount_paise = (int) round((float) $donation['amount'] * 100);

$payload = [
    'amount'   => $amount_paise,
    'currency' => 'INR',
    'receipt'  => $transaction_id,
    'notes'    => [
        'transaction_id' => $transaction_id,
        'donor_name'     => $donation['donor_name'],
        'donor_email'    => $donation['donor_email'],
    ]
];

$key_id     = RAZORPAY_KEY_ID;
$key_secret = RAZORPAY_KEY_SECRET;

// Call Razorpay Orders API
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_USERPWD        => $key_id . ':' . $key_secret,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['success' => false, 'message' => 'Network error: ' . $curl_error]);
    exit;
}

$order = json_decode($response, true);

if ($http_code !== 200 || empty($order['id'])) {
    $err = $order['error']['description'] ?? 'Failed to create Razorpay order';
    echo json_encode(['success' => false, 'message' => $err]);
    exit;
}

// Store razorpay_order_id in donations table
try {
    $db->query(
        'UPDATE donations SET razorpay_order_id = ? WHERE transaction_id = ?',
        [$order['id'], $transaction_id]
    );
} catch (Exception $e) {
    // Non-fatal — continue
}

echo json_encode([
    'success'           => true,
    'razorpay_order_id' => $order['id'],
    'amount_paise'      => $amount_paise,
    'key_id'            => $key_id,
    'donor_name'        => $donation['donor_name'],
    'donor_email'       => $donation['donor_email'],
    'donor_phone'       => $donation['donor_phone'] ?? '',
    'transaction_id'    => $transaction_id,
]);
