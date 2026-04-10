<?php
/**
 * Donations API
 * Handle donation form submissions — saves record, returns txn_id for Paytm.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/logger.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::validateCSRFToken($csrfToken)) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    // Sanitize and validate input
    $donorName    = Security::sanitize($_POST['donor_name']    ?? '');
    $donorEmail   = Security::sanitize($_POST['donor_email']   ?? '');
    $donorPhone   = Security::sanitize($_POST['donor_phone']   ?? '');
    $donorPan     = strtoupper(Security::sanitize($_POST['donor_pan'] ?? ''));
    $donorAddress = Security::sanitize($_POST['donor_address'] ?? '');
    $amount       = floatval($_POST['amount'] ?? 0);
    $cause        = Security::sanitize($_POST['cause']         ?? 'general');
    $frequency    = Security::sanitize($_POST['frequency']     ?? 'one-time');
    $isAnonymous  = isset($_POST['anonymous']) ? 1 : 0;
    $wantsUpdates = isset($_POST['updates'])   ? 1 : 0;

    // Validation
    if (empty($donorName))                        throw new Exception('Donor name is required');
    if (!Security::validateEmail($donorEmail))    throw new Exception('Valid email address is required');
    if ($amount < 1)                              throw new Exception('Donation amount must be at least ₹1');
    if ($amount > 1000000)                        throw new Exception('Maximum donation amount is ₹10,00,000. For larger donations, please contact us.');
    if ($donorPhone && !Security::validatePhone($donorPhone)) throw new Exception('Invalid phone number. Please enter a valid 10-digit Indian mobile number.');
    if ($donorPan   && !Security::validatePAN($donorPan))     throw new Exception('Invalid PAN number format');

    $validCauses = ['general', 'poor-feeding', 'education', 'medical', 'disaster'];
    if (!in_array($cause, $validCauses)) $cause = 'general';

    // Generate transaction ID
    $transactionId = 'TXN_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -8));

    // Calculate 80G tax exemption (50%)
    $taxExemption = $amount * 0.5;

    $db = Database::getInstance();

    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = $_SESSION['user_id'] ?? null;

    $donationData = [
        'user_id'              => $userId,
        'transaction_id'       => $transactionId,
        'donor_name'           => $donorName,
        'donor_email'          => $donorEmail,
        'donor_phone'          => $donorPhone,
        'donor_pan'            => $donorPan,
        'donor_address'        => $donorAddress,
        'amount'               => $amount,
        'cause'                => $cause,
        'frequency'            => $frequency,
        'payment_status'       => 'pending',
        'payment_method'       => 'Paytm',
        'is_anonymous'         => $isAnonymous,
        'is_recurring'         => ($frequency !== 'one-time'),
        'tax_exemption_amount' => $taxExemption
    ];

    $donationId = $db->insert('donations', $donationData);

    $logger = new Logger();
    $logger->log($userId, 'donation_initiated', "₹{$amount} donation initiated for {$cause}", 'donation', $donationId);

    // Return transaction_id to frontend — Paytm payment is initiated next
    echo json_encode([
        'success'        => true,
        'message'        => 'Donation saved. Redirecting to Paytm...',
        'transaction_id' => $transactionId,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
