<?php
/**
 * Core Configuration Manager
 * Handles database, app settings, and Paytm credentials.
 * Credentials are read from .env file — never hardcode here.
 */

// Load .env if present (local development)
$env_path = __DIR__ . '/../.env';
if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
} else {
    $env = [];
}

class Config {
    // Database Configuration
    private static $db_config = [
        'host'    => '',
        'name'    => '',
        'user'    => '',
        'pass'    => '',
        'port'    => 3306,
        'charset' => 'utf8mb4'
    ];

    // Application Settings
    private static $app_config = [
        'name'     => 'Sri Dutta Sai Manga Bharadwaja Trust',
        'url'      => 'https://sadgurubharadwaja.org',
        'email'    => 'admin@sadgurubharadwaja.org',
        'phone'    => '+91 7893601789',
        'debug'    => false,
        'timezone' => 'Asia/Kolkata'
    ];

    // Security Settings
    private static $security_config = [
        'jwt_secret'       => '',
        'password_salt'    => '',
        'session_lifetime' => 7200,
        'max_login_attempts' => 5,
        'lockout_duration' => 900
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

// Populate from .env
if (!empty($env)) {
    // Use reflection to update private static properties
    $ref = new ReflectionClass('Config');

    $dbProp = $ref->getProperty('db_config');
    $dbProp->setAccessible(true);
    $dbProp->setValue(null, [
        'host'    => $env['DB_HOST']    ?? 'localhost',
        'name'    => $env['DB_NAME']    ?? '',
        'user'    => $env['DB_USER']    ?? '',
        'pass'    => $env['DB_PASS']    ?? '',
        'port'    => 3306,
        'charset' => 'utf8mb4'
    ]);

    $appProp = $ref->getProperty('app_config');
    $appProp->setAccessible(true);
    $appProp->setValue(null, [
        'name'     => 'Sri Dutta Sai Manga Bharadwaja Trust',
        'url'      => $env['APP_URL']   ?? 'https://sadgurubharadwaja.org',
        'email'    => 'admin@sadgurubharadwaja.org',
        'phone'    => '+91 7893601789',
        'debug'    => ($env['APP_ENV'] ?? 'production') === 'development',
        'timezone' => 'Asia/Kolkata'
    ]);

    $secProp = $ref->getProperty('security_config');
    $secProp->setAccessible(true);
    $secProp->setValue(null, [
        'jwt_secret'         => $env['JWT_SECRET']      ?? '',
        'password_salt'      => $env['PASSWORD_SALT']   ?? '',
        'session_lifetime'   => 7200,
        'max_login_attempts' => 5,
        'lockout_duration'   => 900
    ]);
}

// Paytm Gateway Constants
$paytm_env = $env['PAYTM_ENV'] ?? 'TEST';
define('PAYTM_MID',           $env['PAYTM_MID']           ?? '');
define('PAYTM_MERCHANT_KEY',  $env['PAYTM_MERCHANT_KEY']  ?? '');
define('PAYTM_WEBSITE',       $env['PAYTM_WEBSITE']       ?? 'WEBSTAGING');
define('PAYTM_ENV',           $paytm_env);
define('PAYTM_CALLBACK_URL',  $env['PAYTM_CALLBACK_URL']  ?? '');

if ($paytm_env === 'PROD') {
    define('PAYTM_TXN_URL',    'https://securegw.paytm.in/theia/processTransaction');
    define('PAYTM_STATUS_URL', 'https://securegw.paytm.in/order/status');
} else {
    define('PAYTM_TXN_URL',    'https://securegw-stage.paytm.in/theia/processTransaction');
    define('PAYTM_STATUS_URL', 'https://securegw-stage.paytm.in/order/status');
}

// Set timezone
date_default_timezone_set(Config::app('timezone'));
?>
