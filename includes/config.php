<?php
/**
 * Core Configuration Manager
 * Handles database connection and app settings
 */

class Config {
    // Database Configuration
    private static $db_config = [
        'host' => 'localhost',
        'name' => '', // Update this
        'user' => '',   // Update this  
        'pass' => '',   // Update this
        'port' => 3306,
        'charset' => 'utf8mb4'
    ];
    
    // Application Settings
    private static $app_config = [
        'name' => 'Sri Dutta Sai Manga Bharadwaja Trust',
        'url' => 'https://sadgurubharadwaja.org', // Update this
        'email' => 'admin@sadgurubharadwaja.org', // Update this
        'phone' => '+91 7893601789',
        'debug' => false, // Set to false in production
        'timezone' => 'Asia/Kolkata'
    ];
    
    // Security Settings
    private static $security_config = [
        'jwt_secret' => 'your-secure-jwt-secret-key', // Change this
        'password_salt' => 'your-password-salt',      // Change this
        'session_lifetime' => 7200, // 2 hours
        'max_login_attempts' => 5,
        'lockout_duration' => 900 // 15 minutes
    ];
    
    public static function db($key = null) {
        return $key ? self::$db_config[$key] : self::$db_config;
    }
    
    public static function app($key = null) {
        return $key ? self::$app_config[$key] : self::$app_config;
    }
    
    public static function security($key = null) {
        return $key ? self::$security_config[$key] : self::$security_config;
    }
    
    public static function isDebug() {
        return self::$app_config['debug'];
    }
}

// Set timezone
date_default_timezone_set(Config::app('timezone'));
?>
