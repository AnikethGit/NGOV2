<?php
/**
 * Security Utilities - Fixed CSRF Handling
 * CSRF protection, input sanitization, encryption
 */

class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate a new token
        $token = bin2hex(random_bytes(32));
        
        // Store in session
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        // Also store in a cookie as backup
        setcookie('csrf_token', $token, 0, '/', '', false, true);
        
        return $token;
    }
    
    /**
     * Validate CSRF token - Fixed version
     */
    public static function validateCSRFToken($token) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token is empty
        if (empty($token)) {
            error_log('CSRF Validation: Token is empty');
            return false;
        }
        
        // Check session token
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        $tokenTime = $_SESSION['csrf_token_time'] ?? null;
        
        error_log('CSRF Validation: Session token exists: ' . (!empty($sessionToken) ? 'yes' : 'no'));
        error_log('CSRF Validation: Comparing tokens');
        error_log('CSRF Validation: Provided token length: ' . strlen($token));
        error_log('CSRF Validation: Session token length: ' . strlen($sessionToken ?? ''));
        
        // Token must exist in session
        if (empty($sessionToken)) {
            error_log('CSRF Validation: No token in session');
            return false;
        }
        
        // Check expiration (1 hour)
        if ($tokenTime && (time() - $tokenTime > 3600)) {
            error_log('CSRF Validation: Token expired');
            return false;
        }
        
        // Compare tokens securely
        $isValid = hash_equals($sessionToken, $token);
        
        if ($isValid) {
            error_log('CSRF Validation: Token is VALID');
        } else {
            error_log('CSRF Validation: Token is INVALID');
            error_log('CSRF Validation: Expected: ' . substr($sessionToken, 0, 20) . '...');
            error_log('CSRF Validation: Got: ' . substr($token, 0, 20) . '...');
        }
        
        return $isValid;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate phone number (Indian)
     */
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 10 && preg_match('/^[6-9]/', $phone);
    }
    
    /**
     * Validate PAN number
     */
    public static function validatePAN($pan) {
        return preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper($pan));
    }
    
    /**
     * Generate secure random string
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
?>
