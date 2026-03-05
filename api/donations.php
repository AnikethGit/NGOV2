<?php
/**
 * Donations API
 * Handle donation form submissions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/logger.php';
require_once '../includes/email-service.php';

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
    $donorName = Security::sanitize($_POST['donor_name'] ?? '');
    $donorEmail = Security::sanitize($_POST['donor_email'] ?? '');
    $donorPhone = Security::sanitize($_POST['donor_phone'] ?? '');
    $donorPan = strtoupper(Security::sanitize($_POST['donor_pan'] ?? ''));
    $donorAddress = Security::sanitize($_POST['donor_address'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $cause = Security::sanitize($_POST['cause'] ?? 'general');
    $frequency = Security::sanitize($_POST['frequency'] ?? 'one-time');
    $isAnonymous = isset($_POST['anonymous']) ? 1 : 0;
    $wantsUpdates = isset($_POST['updates']) ? 1 : 0;
    
    // Validation
    if (empty($donorName)) {
        throw new Exception('Donor name is required');
    }
    
    if (!Security::validateEmail($donorEmail)) {
        throw new Exception('Valid email address is required');
    }
    
    if ($amount < 1) {
        throw new Exception('Donation amount must be at least ₹1');
    }
    
    if ($amount > 1000000) {
        throw new Exception('Maximum donation amount is ₹10,00,000. For larger donations, please contact us.');
    }
    
    if ($donorPhone && !Security::validatePhone($donorPhone)) {
        throw new Exception('Invalid phone number. Please enter a valid 10-digit Indian mobile number.');
    }
    
    if ($donorPan && !Security::validatePAN($donorPan)) {
        throw new Exception('Invalid PAN number format');
    }
    
    // Allowed causes
    $validCauses = ['general', 'poor-feeding', 'education', 'medical', 'disaster'];
    if (!in_array($cause, $validCauses)) {
        $cause = 'general';
    }
    
    // Generate transaction ID
    $transactionId = 'TXN_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -8));
    
    // Calculate tax exemption (50% under 80G)
    $taxExemption = $amount * 0.5;
    
    // Get database instance
    $db = Database::getInstance();
    
    // Check if user exists (for logged-in donors)
    $userId = null;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    // Prepare donation data
    $donationData = [
        'user_id' => $userId,
        'transaction_id' => $transactionId,
        'donor_name' => $donorName,
        'donor_email' => $donorEmail,
        'donor_phone' => $donorPhone,
        'donor_pan' => $donorPan,
        'donor_address' => $donorAddress,
        'amount' => $amount,
        'cause' => $cause,
        'frequency' => $frequency,
        'payment_status' => 'pending',
        'payment_method' => 'PhonePe',
        'is_anonymous' => $isAnonymous,
        'is_recurring' => ($frequency !== 'one-time'),
        'tax_exemption_amount' => $taxExemption
    ];
    
    // Insert donation record
    $donationId = $db->insert('donations', $donationData);
    
    // Log activity
    $logger = new Logger();
    $logger->log(
        $userId,
        'donation_initiated',
        "Donation initiated: ₹{$amount} for {$cause}",
        'donation',
        $donationId,
        ['amount' => $amount, 'cause' => $cause]
    );
    
    // For now, simulate successful payment
    // In next step, we'll integrate actual PhonePe payment
    
    // Update payment status to completed (temporary)
    $db->update(
        'donations',
        ['payment_status' => 'completed', 'payment_method' => 'PhonePe (Test)'],
        'id = ?',
        [$donationId]
    );
    
    // Get complete donation data for email
    $completeDonation = $db->fetch('SELECT * FROM donations WHERE id = ?', [$donationId]);
    
   // Send confirmation email
try {
    $emailService = new EmailService();
    $emailService->sendDonationConfirmation($completeDonation);
} catch (Exception $emailError) {
    // Log email error but don't fail the donation
    error_log('Email notification failed: ' . $emailError->getMessage());
}
    
    // Log successful donation
    $logger->logDonation($userId, $donationId, $amount, 'completed');
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your donation!',
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'payment_url' => '/payment-success.html?txn=' . $transactionId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
