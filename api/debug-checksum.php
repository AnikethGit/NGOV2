<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER TESTING
 * Visit: https://sadgurubharadwaja.org/api/debug-checksum.php
 * This tells you exactly which PaytmChecksum version is live on the server.
 */

require_once '../includes/PaytmChecksum.php';

$test_params = [
    'MID'        => 'TestMID123',
    'ORDER_ID'   => 'ORDER_001',
    'TXN_AMOUNT' => '100.00',
    'CUST_ID'    => 'CUST_001',
];

$test_key  = 'TestKey12345678'; // 16 chars for AES test
$checksum  = PaytmChecksum::generateSignature($test_params, $test_key);
$length    = strlen($checksum);
$has_space = (strpos($checksum, ' ') !== false) || (strpos($checksum, '=') !== false);

// AES-CBC output is ~108 chars with base64 padding (= signs)
// HMAC-SHA256 output is exactly 68 chars (64 hex + 4 char salt), no = signs
$algorithm = ($length > 80) ? 'OLD (AES-128-CBC) — needs deployment update' : 'NEW (HMAC-SHA256) — correct';

header('Content-Type: text/plain');
echo "=== PaytmChecksum Diagnostic ===\n";
echo "Checksum value : " . $checksum . "\n";
echo "Length         : " . $length . " chars\n";
echo "Has special chars (= space): " . ($has_space ? 'YES' : 'NO') . "\n";
echo "Algorithm      : " . $algorithm . "\n\n";

if ($length > 80) {
    echo "ACTION NEEDED: Server is running OLD PaytmChecksum.php\n";
    echo "Please manually upload the new includes/PaytmChecksum.php from GitHub to your server.\n";
    echo "GitHub file: https://github.com/AnikethGit/NGOV2/blob/main/includes/PaytmChecksum.php\n";
} else {
    echo "OK: Server is running the correct NEW PaytmChecksum.php\n";
    echo "You can now test the payment flow.\n";
}
