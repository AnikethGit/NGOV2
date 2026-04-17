<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER TESTING
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

$len      = strlen($checksum);
$hasEq    = strpos($checksum, '=') !== false;
$hasSpace = strpos($checksum, ' ') !== false;
?>
<!DOCTYPE html>
<html>
<head><title>Paytm Debug Form</title>
<style>
  body{font-family:monospace;padding:20px;background:#f0f0f0}
  table{border-collapse:collapse;width:100%;margin-bottom:20px;background:#fff}
  td,th{border:1px solid #ccc;padding:8px 12px;font-size:13px;text-align:left;word-break:break-all}
  th{background:#333;color:#fff}
  .ok{color:green;font-weight:bold} .err{color:red;font-weight:bold}
  .btn{background:#0066cc;color:#fff;padding:12px 28px;border:none;border-radius:6px;font-size:16px;cursor:pointer;margin-top:10px}
  .info{background:#fff;padding:15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #0066cc}
</style>
</head>
<body>
<h2>Paytm Live Form Debug</h2>

<div class="info">
  <strong>CHECKSUMHASH:</strong> <?= htmlspecialchars($checksum) ?><br>
  <strong>Length:</strong> <?= $len ?> chars 
    <?= $len > 80 ? '<span class="ok">✔ AES format (expected by Paytm)</span>' : '<span class="err">✘ HMAC format (Paytm may reject)</span>' ?><br>
  <strong>Has = signs:</strong> <?= $hasEq ? '<span class="ok">YES (AES base64)</span>' : '<span class="err">NO</span>' ?><br>
  <strong>Has spaces:</strong> <?= $hasSpace ? '<span class="err">YES — problem!</span>' : '<span class="ok">NO — good</span>' ?><br>
  <strong>Paytm URL:</strong> <?= htmlspecialchars($paytm_url) ?>
</div>

<table>
  <tr><th>Parameter</th><th>Value</th><th>Length</th></tr>
  <?php foreach ($paytm_params as $k => $v): ?>
  <tr>
    <td><?= htmlspecialchars($k) ?></td>
    <td><?= htmlspecialchars($v) ?></td>
    <td><?= strlen($v) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<form method="POST" action="<?= htmlspecialchars($paytm_url) ?>">
  <?php foreach ($paytm_params as $k => $v): ?>
    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
  <?php endforeach; ?>
  <button type="submit" class="btn">Submit ₹1 test to Paytm Gateway &rarr;</button>
</form>
</body>
</html>
