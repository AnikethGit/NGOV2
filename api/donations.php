<?php
/**
 * api/donations.php (updated)
 * POST: saves a new donation record
 * GET ?action=user-history: returns all donations for logged-in user
 *   – matches by user_id OR donor_email so pre-registration donations appear
 *   – also back-fills user_id on those rows for future speed
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── GET: user history ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'user-history') {

    if (empty($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $email  = $_SESSION['user_email'];

    try {
        $db = Database::getInstance();

        // Back-fill: assign user_id to any donations made with this email before registration
        $db->query(
            "UPDATE donations SET user_id = ? WHERE donor_email = ? AND user_id IS NULL",
            [$userId, $email]
        );

        // Fetch all donations matching user_id OR email (covers any edge-cases)
        $donations = $db->fetchAll(
            "SELECT id, transaction_id, donor_name, donor_email, amount,
                    cause AS cause_name, payment_status, payment_mode,
                    created_at
             FROM donations
             WHERE user_id = ? OR donor_email = ?
             ORDER BY created_at DESC",
            [$userId, $email]
        );

        echo json_encode(['success' => true, 'data' => $donations]);
    } catch (Exception $e) {
        error_log('Donations history error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not load donation history']);
    }
    exit;
}

// ── POST: save new donation ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::validateCSRFToken($csrfToken)) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    // Sanitize inputs
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
    if ($amount > 1000000)                        throw new Exception('Maximum donation amount is ₹10,00,000');
    if ($donorPhone && !Security::validatePhone($donorPhone)) throw new Exception('Invalid phone number');
    if ($donorPan   && !Security::validatePAN($donorPan))     throw new Exception('Invalid PAN number format');

    $validCauses = ['general', 'poor-feeding', 'education', 'medical', 'disaster'];
    if (!in_array($cause, $validCauses)) $cause = 'general';

    $transactionId = 'TXN_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -8));
    $taxExemption  = $amount * 0.5;

    $db     = Database::getInstance();
    $userId = $_SESSION['user_id'] ?? null;

    // If not logged in, try to match an existing user by email so donations link
    if (!$userId) {
        $matched = $db->fetch(
            "SELECT id FROM users WHERE email = ? LIMIT 1",
            [$donorEmail]
        );
        if ($matched) $userId = $matched['id'];
    }

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
        'is_recurring'         => ($frequency !== 'one-time') ? 1 : 0,
        'tax_exemption_amount' => $taxExemption,
    ];

    $donationId = $db->insert('donations', $donationData);

    $logger = new Logger();
    $logger->log($userId, 'donation_initiated', "₹{$amount} donation initiated for {$cause}", 'donation', $donationId);

    echo json_encode([
        'success'        => true,
        'message'        => 'Donation saved. Redirecting to payment...',
        'transaction_id' => $transactionId,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}