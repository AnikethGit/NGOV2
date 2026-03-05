<?php
/**
 * Activity Logger
 * Track all important user activities
 */

require_once 'database.php';

class Logger {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Log activity
     */
    public function log($userId, $action, $description, $entityType = null, $entityId = null, $additionalData = null) {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'additional_data' => $additionalData ? json_encode($additionalData) : null
        ];
        
        try {
            $this->db->insert('activity_logs', $data);
        } catch (Exception $e) {
            error_log('Logger error: ' . $e->getMessage());
        }
    }
    
    /**
     * Log donation
     */
    public function logDonation($userId, $donationId, $amount, $status) {
        $this->log(
            $userId,
            'donation_' . $status,
            "Donation of ₹{$amount} - Status: {$status}",
            'donation',
            $donationId,
            ['amount' => $amount, 'status' => $status]
        );
    }
    
    /**
     * Log login
     */
    public function logLogin($userId, $success = true) {
        $this->log(
            $userId,
            $success ? 'login_success' : 'login_failed',
            $success ? 'User logged in successfully' : 'Failed login attempt',
            'user',
            $userId
        );
    }
    
    /**
     * Get user activity
     */
    public function getUserActivity($userId, $limit = 50) {
        return $this->db->fetchAll(
            'SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }
}
?>
