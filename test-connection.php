<?php
/**
 * Database Connection Test
 * Run this to verify everything is working
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

echo "<h1>NGO Phase 2 - Database Connection Test</h1>";

try {
    // Test database connection
    $db = Database::getInstance();
    echo "<p>✅ Database connection successful!</p>";
    
    // Test basic query
    $result = $db->fetch('SELECT COUNT(*) as count FROM users');
    echo "<p>✅ Database queries working! Found {$result['count']} users.</p>";
    
    // Test configuration
    echo "<p>✅ App Name: " . Config::app('name') . "</p>";
    echo "<p>✅ App URL: " . Config::app('url') . "</p>";
    echo "<p>✅ Debug Mode: " . (Config::isDebug() ? 'ON' : 'OFF') . "</p>";
    
    echo "<h2 style='color: green;'>✅ All systems ready!</h2>";
    echo "<p><a href='/' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Website</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error occurred:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p><strong>Please check:</strong></p>";
    echo "<ul>";
    echo "<li>Database credentials in includes/config.php</li>";
    echo "<li>Database exists and tables are created</li>";  
    echo "<li>MySQL service is running</li>";
    echo "</ul>";
}
?>
