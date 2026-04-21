<?php
/**
 * Core Configuration Manager
 * Handles database, app settings, Paytm and Razorpay credentials.
 * Credentials are read from .env file — never hardcode here.
 */

/**
 * Robust .env parser — handles special characters like & # * % ! in values.
 * parse_ini_file() breaks on these characters. This custom parser does not.
 */
function load_env_file($path) {
    if (!file_exists($path)) return [];

    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip blank lines and comment lines
        if ($line === '' || $line[0] === '#') continue;

        // Must contain an = sign
        $pos = strpos($line, '=');
        if ($pos === false) continue;

        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        // Strip inline comments (only if value is NOT quoted)
        // e.g.  KEY=value ; comment
        if (strlen($value) > 0 && $value[0] !== '"' && $value[0] !== "'") {
            $comment_pos = strpos($value, ' ;');
            if ($comment_pos !== false) {
                $value = trim(substr($value, 0, $comment_pos));
            }
        }

        // Strip surrounding quotes (single or double)
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key !== '') {
            $env[$key] = $value;
        }
    }

    return $env;
}

// Load .env file
$env_path = __DIR__ . '/../.env';
$env = load_env_file($env_path);

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
        'jwt_secret'         => '',
        'password_salt'      => '',
        'session_lifetime'   => 7200,
        'max_login_attempts' => 5,
        'lockout_duration'   => 900
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

// ============================================================
// GATEWAY SWITCH
// Change this ONE line to swap between gateways.
//   'razorpay' → use Razorpay (test phase)
//   'paytm'    → use Paytm (after test phase completes)
// ============================================================
define('ACTIVE_GATEWAY', 'paytm');

// ============================================================
// Paytm Gateway Constants (unchanged — do not remove)
// Hosts updated per Paytm Merchant Integrations team (April 2026):
//   OLD staging: securegw-stage.paytm.in      → NEW: securestage.paytmpayments.com
//   OLD prod:    securegw.paytm.in             → NEW: secure.paytmpayments.com
// ============================================================
$paytm_env = $env['PAYTM_ENV'] ?? 'TEST';
define('PAYTM_MID',           $env['PAYTM_MID']           ?? '');
define('PAYTM_MERCHANT_KEY',  $env['PAYTM_MERCHANT_KEY']  ?? '');
define('PAYTM_WEBSITE',       $env['PAYTM_WEBSITE']       ?? 'WEBSTAGING');
define('PAYTM_ENV',           $paytm_env);
define('PAYTM_CALLBACK_URL',  $env['PAYTM_CALLBACK_URL']  ?? '');

if ($paytm_env === 'PROD') {
    define('PAYTM_TXN_URL',    'https://secure.paytmpayments.com/theia/processTransaction');
    define('PAYTM_STATUS_URL', 'https://secure.paytmpayments.com/order/status');
} else {
    define('PAYTM_TXN_URL',    'https://securestage.paytmpayments.com/theia/processTransaction');
    define('PAYTM_STATUS_URL', 'https://securestage.paytmpayments.com/order/status');
}

// ============================================================
// Razorpay Gateway Constants
// RAZORPAY_KEY_SECRET must be added to your .env file:
//   RAZORPAY_KEY_SECRET=your_test_secret_here
// ============================================================
define('RAZORPAY_KEY_ID',     'rzp_test_SbJ17DZlTzkV3g');
define('RAZORPAY_KEY_SECRET', $env['RAZORPAY_KEY_SECRET'] ?? '');

// Set timezone
date_default_timezone_set(Config::app('timezone'));
?>
