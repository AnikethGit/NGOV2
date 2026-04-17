<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER TESTING
 * Visit: https://sadgurubharadwaja.org/api/debug-checksum.php
 * This renders an actual Paytm form exactly as initiate-payment.php builds it.
 * Click "Submit to Paytm" to test the live gateway.
 */

require_once '../includes/config.php';
require_once '../includes/PaytmChecksum.php';

$paytm_params = [
    'MID'              => PAYTM_MID,
    'WEBSITE'          => PAYTM_WEBSITE,
    'CHANNEL_ID'       => 'WEB',
    'INDUSTRY_TYPE_ID' => 'Ecommerce',
    'ORDER_ID'         => 'DEBUG_' . time(),
    'CUST_ID'          => 'CUST_DEBUG_1',
    'TXN_AMOUNT'       => '1.00',
    'CALLBACK_URL'     => PAYTM_CALLBACK_URL,
    'EMAIL'            => 'test@test.com',
    'MOBILE_NO'        => '9999999999',
];

$checksum = PaytmChecksum::generateSignature($paytm_params, PAYTM_MERCHANT_KEY);
$paytm_params['CHECKSUMHASH'] = $checksum;
$paytm_url = PAYTM_TXN_URL;
?>
<!DOCTYPE html>
<html>
<head><title>Paytm Debug Form</title>
<style>
  body { font-family: monospace; padding: 20px; background: #f0f0f0; }
  table { border-collapse: collapse; width: 100%; margin-bottom: 20px; background: #fff; }
  td, th { border: 1px solid #ccc; padding: 8px 12px; text-align: left; font-size: 13px; }
  th { background: #333; color: #fff; }
  .ok { color: green; font-weight: bold; }
  .err { color: red; font-weight: bold; }
  .btn { background: #0066cc; color: #fff; padding: 12px 28px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
</style>
</head>
<body>
<h2>Paytm Live Form Debug</h2>
<p>Paytm URL: <strong><?= htmlspecialchars($paytm_url) ?></strong></p>

<table>
  <tr><th>Parameter</th><th>Value</th><th>Length</th><th>Check</th></tr>
  <?php foreach ($paytm_params as $k => $v):
    $len = strlen($v);
    $hasIssue = (strpos($v, ' ') !== false || strpos($v, '=') !== false) && $k === 'CHECKSUMHASH';
  ?>
  <tr>
    <td><?= htmlspecialchars($k) ?></td>
    <td style="word-break:break-all"><?= htmlspecialchars($v) ?></td>
    <td><?= $len ?></td>
    <td><?= $hasIssue ? '<span class="err">FAIL</span>' : '<span class="ok">OK</span>' ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<p>CHECKSUMHASH length: <strong><?= strlen($paytm_params['CHECKSUMHASH']) ?> chars</strong>
  <?= strlen($paytm_params['CHECKSUMHASH']) === 68 ? '<span class="ok">✔ Correct</span>' : '<span class="err">✘ Wrong</span>' ?>
</p>

<form method="POST" action="<?= htmlspecialchars($paytm_url) ?>">
  <?php foreach ($paytm_params as $k => $v): ?>
    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
  <?php endforeach; ?>
  <button type="submit" class="btn">Submit to Paytm Gateway &rarr;</button>
</form>

<p style="color:#999;margin-top:20px;font-size:12px">Amount: &#8377;1.00 (minimum test). Order ID: <?= htmlspecialchars($paytm_params['ORDER_ID']) ?></p>
</body>
</html>
