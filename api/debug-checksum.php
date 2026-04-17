<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER TESTING
 * Visit: https://sadgurubharadwaja.org/api/debug-checksum.php
 */

require_once '../includes/config.php';
require_once '../includes/PaytmChecksum.php';

// Simulate exact same params as initiate-payment.php
$paytm_params = [
    'MID'              => PAYTM_MID,
    'WEBSITE'          => PAYTM_WEBSITE,
    'CHANNEL_ID'       => 'WEB',
    'INDUSTRY_TYPE_ID' => 'Ecommerce',
    'ORDER_ID'         => 'TEST_ORDER_DEBUG_001',
    'CUST_ID'          => 'CUST_1',
    'TXN_AMOUNT'       => '100.00',
    'CALLBACK_URL'     => PAYTM_CALLBACK_URL,
    'EMAIL'            => 'test@test.com',
    'MOBILE_NO'        => '9999999999',
];

$key      = PAYTM_MERCHANT_KEY;
$checksum = PaytmChecksum::generateSignature($paytm_params, $key);
$length   = strlen($checksum);

// Check param string (what gets hashed)
ksort($paytm_params);
$param_string = implode('|', array_map(fn($v) => is_null($v) ? '' : $v, $paytm_params));

header('Content-Type: text/plain');
echo "=== PaytmChecksum Deep Diagnostic ===\n\n";
echo "Merchant Key length : " . strlen($key) . " chars\n";
echo "Merchant Key preview: " . substr($key, 0, 4) . str_repeat('*', max(0, strlen($key) - 8)) . substr($key, -4) . "\n\n";
echo "Param pipe string   : " . $param_string . "\n\n";
echo "CHECKSUMHASH value  : " . $checksum . "\n";
echo "CHECKSUMHASH length : " . $length . " chars\n";
echo "Has = signs         : " . (strpos($checksum, '=') !== false ? 'YES — problem!' : 'NO — good') . "\n";
echo "Has spaces          : " . (strpos($checksum, ' ') !== false ? 'YES — problem!' : 'NO — good') . "\n\n";

if ($length === 68) {
    echo "STATUS: CORRECT — 68-char HMAC-SHA256 hash. Should be accepted by Paytm.\n";
} elseif ($length > 80) {
    echo "STATUS: WRONG — Still producing AES output. Check if PaytmChecksum.php was saved correctly.\n";
} else {
    echo "STATUS: UNEXPECTED length " . $length . " — check the key and params.\n";
}

echo "\nPaytm URL: " . PAYTM_TXN_URL . "\n";
echo "Callback : " . PAYTM_CALLBACK_URL . "\n";
echo "WEBSITE  : " . PAYTM_WEBSITE . "\n";
echo "ENV      : " . PAYTM_ENV . "\n";
