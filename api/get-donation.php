<?php
/**
 * api/get-donation.php
 * Returns donation details for the payment success page.
 */

require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

$txn_id = trim($_GET['txn'] ?? '');
if (empty($txn_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction ID']);
    exit;
}

try {
    $db = Database::getInstance();
    $donation = $db->fetch(
        "SELECT id, donor_name, donor_email, amount, cause, payment_status, payment_mode, created_at
         FROM donations WHERE transaction_id = ? LIMIT 1",
        [$txn_id]
    );

    if ($donation) {
        echo json_encode(['success' => true, 'donation' => $donation]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Donation not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
