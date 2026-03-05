<?php
/**
 * Contact Form API - Simplified
 * No session dependency - direct validation
 */

header('Content-Type: application/json');

// Don't start session - use direct token validation

require_once '../includes/config.php';
require_once '../includes/database.php';

try {
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get CSRF token from POST
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (empty($csrfToken)) {
        throw new Exception('Security token is missing');
    }
    
    // Validate CSRF token (simple file-based check)
    $tokenDir = sys_get_temp_dir() . '/csrf_tokens/';
    $tokenFile = $tokenDir . session_id() . '.token';
    
    if (!file_exists($tokenFile)) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }
    
    $storedToken = trim(file_get_contents($tokenFile));
    if (!hash_equals($storedToken, $csrfToken)) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }
    
    // Delete used token
    @unlink($tokenFile);
    
    // Get form fields
    $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Validate first name
    if (empty($firstName)) {
        throw new Exception('First name is required');
    }
    if (strlen($firstName) < 2) {
        throw new Exception('First name must be at least 2 characters');
    }
    
    // Validate last name
    if (empty($lastName)) {
        throw new Exception('Last name is required');
    }
    if (strlen($lastName) < 2) {
        throw new Exception('Last name must be at least 2 characters');
    }
    
    // Validate email
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email address is invalid');
    }
    
    // Validate phone (optional but if provided must be valid)
    if (!empty($phone)) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) != 10 || !preg_match('/^[6-9]/', $phone)) {
            throw new Exception('Phone must be a valid 10-digit Indian mobile number');
        }
    } else {
        $phone = '';
    }
    
    // Validate subject
    if (empty($subject)) {
        throw new Exception('Please select a subject');
    }
    
    // Validate message
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    if (strlen($message) < 10) {
        throw new Exception('Message must be at least 10 characters');
    }
    if (strlen($message) > 5000) {
        throw new Exception('Message is too long (max 5000 characters)');
    }
    
    // Combine names
    $fullName = $firstName . ' ' . $lastName;
    
    // Get database
    $db = Database::getInstance();
    
    // Map subject to display value
    $subjectMap = [
        'general' => 'General Inquiry',
        'donation' => 'Donation Related',
        'volunteer' => 'Volunteer Opportunities',
        'partnership' => 'Partnership',
        'support' => 'Support/Help',
        'other' => 'Other'
    ];
    $subjectDisplay = $subjectMap[$subject] ?? 'General Inquiry';
    
    // Prepare data for database
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Insert into database
    $contactData = [
        'name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'subject' => $subjectDisplay,
        'message' => $message,
        'status' => 'new',
        'priority' => 'normal',
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent
    ];
    
    // Insert the contact message
    $contactId = $db->insert('contact_messages', $contactData);
    
    if (!$contactId) {
        throw new Exception('Failed to save message to database');
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for contacting us, ' . htmlspecialchars($firstName) . '! We will get back to you soon.',
        'contact_id' => $contactId
    ]);
    exit;
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
