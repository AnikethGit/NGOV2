<?php
/**
 * test-payment.php  —  NGOV2 Payment Flow Mock Test Harness
 *
 * PURPOSE : Let you test the ENTIRE donation → payment → callback → DB update
 *           flow WITHOUT real Paytm keys. Uses a "mock" mode that bypasses
 *           PaytmChecksum and curl so nothing ever reaches Paytm.
 *
 * HOW IT WORKS:
 *   1. Creates a real donation row in your DB (same as the live flow)
 *   2. Simulates initiate-payment (builds the param array, skips checksum)
 *   3. Simulates the Paytm callback POST with chosen status (SUCCESS / PENDING / FAIL)
 *   4. Updates the donation row exactly as the real callback would
 *   5. Shows you a full audit trail of every step
 *
 * WHEN YOU RECEIVE PAYTM KEYS:
 *   → Set PAYTM_MID, PAYTM_MERCHANT_KEY in your .env
 *   → Delete this file from the server  (⚠️ never leave it in production)
 *
 * ACCESS : https://yourdomain.com/test-payment.php
 *          Protect with the password below if deployed on Hostinger.
 */

// ─── PROTECTION ──────────────────────────────────────────────────────────────
define('TEST_PASSWORD', 'ngov2test2026'); // Change this!

session_start();
if (!isset($_SESSION['test_auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pw'] ?? '') === TEST_PASSWORD) {
        $_SESSION['test_auth'] = true;
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') $wrongPw = true;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Test Auth</title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:sans-serif;background:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh}.box{background:#1e293b;padding:40px;border-radius:12px;width:320px;text-align:center}h2{color:#e2e8f0;margin-bottom:24px}input{width:100%;padding:12px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#e2e8f0;font-size:15px;margin-bottom:16px}button{width:100%;padding:12px;background:#21808d;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}.err{color:#f87171;font-size:13px;margin-top:8px}</style>
</head><body>
<div class="box">
  <h2>🔒 Test Harness</h2>
  <form method="POST">
    <input type="password" name="pw" placeholder="Enter test password" autofocus>
    <button type="submit">Enter</button>
    <?php if (!empty($wrongPw)) echo '<p class="err">Wrong password</p>'; ?>
  </form>
</div></body></html>
<?php
        exit;
    }
}

// ─── BOOTSTRAP ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

$db = Database::getInstance();
$log = [];
$result = null;

// ─── ACTIONS ─────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ── 1. CREATE DONATION ────────────────────────────────────────────────────── */
if ($action === 'create_donation') {
    $amount      = (float) ($_POST['amount'] ?? 500);
    $donor_name  = trim($_POST['donor_name']  ?? 'Test Donor');
    $donor_email = trim($_POST['donor_email'] ?? 'test@example.com');
    $donor_phone = trim($_POST['donor_phone'] ?? '9999999999');
    $purpose     = trim($_POST['purpose']     ?? 'General Donation');

    if ($amount < 1) { $result = ['error' => 'Amount must be ≥ ₹1']; goto render; }

    $transaction_id = 'TEST_' . strtoupper(uniqid());

    try {
        $db->query(
            "INSERT INTO donations
                (transaction_id, donor_name, donor_email, donor_phone, amount, purpose,
                 payment_status, payment_gateway, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', 'paytm', NOW(), NOW())",
            [$transaction_id, $donor_name, $donor_email, $donor_phone, $amount, $purpose]
        );
        $donation = $db->fetch("SELECT * FROM donations WHERE transaction_id = ?", [$transaction_id]);
        $log[] = ['status' => 'ok',    'step' => '1 — Create Donation', 'msg' => "Row inserted. ID: {$donation['id']}",     'data' => $donation];
        $log[] = ['status' => 'ok',    'step' => '2 — Transaction ID',  'msg' => $transaction_id,                           'data' => null];
        $log[] = ['status' => 'info',  'step' => '3 — Amount',          'msg' => '₹' . number_format($amount, 2) . ' (fetched from DB — client value ignored in live flow)', 'data' => null];

        // ── Simulate initiate-payment param building ──────────────────────────
        $paytm_params = [
            'MID'              => defined('PAYTM_MID') ? PAYTM_MID : 'MOCK_MID_PLACEHOLDER',
            'WEBSITE'          => defined('PAYTM_WEBSITE') ? PAYTM_WEBSITE : 'WEBSTAGING',
            'CHANNEL_ID'       => 'WEB',
            'INDUSTRY_TYPE_ID' => 'Ecommerce',
            'ORDER_ID'         => $transaction_id,
            'CUST_ID'          => 'CUST_' . ($donation['user_id'] ?? $donation['id']),
            'TXN_AMOUNT'       => number_format($amount, 2, '.', ''),
            'CALLBACK_URL'     => defined('PAYTM_CALLBACK_URL') ? PAYTM_CALLBACK_URL : 'https://yourdomain.com/api/payment-callback.php',
            'EMAIL'            => $donor_email,
            'MOBILE_NO'        => preg_replace('/[^0-9]/', '', $donor_phone),
        ];
        $log[] = ['status' => 'ok', 'step' => '4 — Paytm Params Built', 'msg' => 'Checksum skipped in mock mode (no real keys needed)', 'data' => $paytm_params];

        if (defined('PAYTM_MID') && PAYTM_MID !== 'your_live_merchant_id' && PAYTM_MID !== '') {
            $log[] = ['status' => 'ok',   'step' => '5 — PAYTM_MID',  'msg' => 'Found in .env ✓ — Real MID: ' . substr(PAYTM_MID, 0, 4) . '****', 'data' => null];
        } else {
            $log[] = ['status' => 'warn', 'step' => '5 — PAYTM_MID',  'msg' => 'Placeholder / missing — replace with your real MID from Paytm dashboard when received', 'data' => null];
        }
        if (defined('PAYTM_MERCHANT_KEY') && PAYTM_MERCHANT_KEY !== 'your_live_merchant_key' && PAYTM_MERCHANT_KEY !== '') {
            $log[] = ['status' => 'ok',   'step' => '6 — MERCHANT_KEY', 'msg' => 'Found in .env ✓ — Key exists (length: ' . strlen(PAYTM_MERCHANT_KEY) . ')', 'data' => null];
        } else {
            $log[] = ['status' => 'warn', 'step' => '6 — MERCHANT_KEY', 'msg' => 'Placeholder / missing — replace with your real key from Paytm dashboard when received', 'data' => null];
        }

        $result = ['success' => true, 'transaction_id' => $transaction_id, 'amount' => $amount, 'paytm_params' => $paytm_params];

    } catch (Exception $e) {
        $log[]  = ['status' => 'error', 'step' => '1 — Create Donation', 'msg' => $e->getMessage(), 'data' => null];
        $result = ['error' => $e->getMessage()];
    }
}

/* ── 2. SIMULATE CALLBACK ──────────────────────────────────────────────────── */
if ($action === 'simulate_callback') {
    $transaction_id  = trim($_POST['transaction_id'] ?? '');
    $simulate_status = $_POST['simulate_status'] ?? 'TXN_SUCCESS';

    if (empty($transaction_id)) { $result = ['error' => 'Transaction ID required']; goto render; }

    $donation = $db->fetch("SELECT * FROM donations WHERE transaction_id = ?", [$transaction_id]);
    if (!$donation) { $result = ['error' => 'Donation not found: ' . $transaction_id]; goto render; }

    $log[] = ['status' => 'ok', 'step' => '1 — Found Donation', 'msg' => "ID {$donation['id']} | Status: {$donation['payment_status']} | Amount: ₹{$donation['amount']}", 'data' => null];

    // Guard: already processed?
    if ($donation['payment_status'] === 'completed') {
        $log[] = ['status' => 'warn', 'step' => '2 — Duplicate Guard', 'msg' => 'This donation is already completed — callback would redirect to success page without re-processing (idempotency ✓)', 'data' => null];
        $result = ['success' => true, 'already_complete' => true, 'transaction_id' => $transaction_id];
        goto render;
    }

    // Build mock Paytm callback POST data
    $mock_txn_id   = 'MOCK_TXN_' . strtoupper(uniqid());
    $mock_callback = [
        'MID'         => defined('PAYTM_MID') ? PAYTM_MID : 'MOCK_MID',
        'ORDERID'     => $transaction_id,
        'TXNID'       => $mock_txn_id,
        'STATUS'      => $simulate_status,
        'TXNAMOUNT'   => number_format((float)$donation['amount'], 2, '.', ''),
        'RESPCODE'    => $simulate_status === 'TXN_SUCCESS' ? '01' : ($simulate_status === 'PENDING' ? '227' : '141'),
        'RESPMSG'     => $simulate_status === 'TXN_SUCCESS' ? 'Txn Success' : ($simulate_status === 'PENDING' ? 'Txn Pending' : 'Txn Failed'),
        'BANKTXNID'   => 'BANK_' . rand(100000, 999999),
        'PAYMENTMODE' => 'UPI',
        'CHECKSUMHASH' => 'MOCK_CHECKSUM_BYPASSED',
    ];
    $log[] = ['status' => 'info', 'step' => '2 — Mock Callback Data', 'msg' => 'Simulating Paytm POST (checksum verification skipped in mock)', 'data' => $mock_callback];

    // ── Replay callback logic (mirrors payment-callback.php exactly) ──────────
    $verified_status = $simulate_status === 'TXN_SUCCESS' ? 'TXN_SUCCESS' : ($simulate_status === 'PENDING' ? 'PENDING' : 'F');
    $log[] = ['status' => 'ok', 'step' => '3 — Checksum Verify',  'msg' => 'MOCK: skipped (would use PaytmChecksum::verifySignature in production)', 'data' => null];
    $log[] = ['status' => 'ok', 'step' => '4 — API Status Query', 'msg' => 'MOCK: skipped curl to PAYTM_STATUS_URL (would verify via Paytm Transaction Status API in production)', 'data' => null];

    // Amount cross-check
    if ($simulate_status === 'TXN_SUCCESS') {
        if ((float)$mock_callback['TXNAMOUNT'] < (float)$donation['amount']) {
            $log[] = ['status' => 'error', 'step' => '5 — Amount Check', 'msg' => "FAIL: Paytm amount {$mock_callback['TXNAMOUNT']} < DB amount {$donation['amount']} — would reject & mark failed", 'data' => null];
            $db->query("UPDATE donations SET payment_status='failed', updated_at=NOW() WHERE transaction_id=?", [$transaction_id]);
            $result = ['error' => 'Amount mismatch']; goto render;
        }
        $log[] = ['status' => 'ok', 'step' => '5 — Amount Check', 'msg' => "PASS: ₹{$mock_callback['TXNAMOUNT']} ≥ ₹{$donation['amount']} ✓", 'data' => null];
    }

    // DB update (same queries as payment-callback.php)
    if ($simulate_status === 'TXN_SUCCESS') {
        $db->query(
            "UPDATE donations SET
                payment_status='completed',
                paytm_order_id=?,
                paytm_transaction_id=?,
                payment_mode=?,
                bank_txn_id=?,
                paytm_response_code=?,
                paytm_response_msg=?,
                updated_at=NOW()
             WHERE transaction_id=?",
            [$transaction_id, $mock_txn_id, 'UPI', $mock_callback['BANKTXNID'],
             $mock_callback['RESPCODE'], $mock_callback['RESPMSG'], $transaction_id]
        );
        $log[] = ['status' => 'ok', 'step' => '6 — DB Update', 'msg' => "donations.payment_status → 'completed' ✓", 'data' => null];
        $log[] = ['status' => 'ok', 'step' => '7 — Redirect', 'msg' => "Would redirect to: /payment-success.html?txn={$transaction_id}&status=success&amount={$mock_callback['TXNAMOUNT']}", 'data' => null];

    } elseif ($simulate_status === 'PENDING') {
        $db->query("UPDATE donations SET payment_status='pending', paytm_transaction_id=?, updated_at=NOW() WHERE transaction_id=?",
            [$mock_txn_id, $transaction_id]);
        $log[] = ['status' => 'warn', 'step' => '6 — DB Update', 'msg' => "donations.payment_status → 'pending' (payment still in transit)", 'data' => null];
        $log[] = ['status' => 'warn', 'step' => '7 — Redirect',  'msg' => "Would redirect to: /payment-success.html?txn={$transaction_id}&status=pending", 'data' => null];

    } else {
        $db->query("UPDATE donations SET payment_status='failed', paytm_transaction_id=?, paytm_response_code=?, paytm_response_msg=?, updated_at=NOW() WHERE transaction_id=?",
            [$mock_txn_id, $mock_callback['RESPCODE'], $mock_callback['RESPMSG'], $transaction_id]);
        $log[] = ['status' => 'error', 'step' => '6 — DB Update', 'msg' => "donations.payment_status → 'failed' | Code: {$mock_callback['RESPCODE']} | Msg: {$mock_callback['RESPMSG']}", 'data' => null];
        $log[] = ['status' => 'error', 'step' => '7 — Redirect',  'msg' => "Would redirect to: /donate.html?error=payment_failed&code={$mock_callback['RESPCODE']}", 'data' => null];
    }

    // Final DB state
    $final = $db->fetch("SELECT * FROM donations WHERE transaction_id=?", [$transaction_id]);
    $log[] = ['status' => 'info', 'step' => '8 — Final DB Row', 'msg' => 'Verify all fields updated correctly', 'data' => $final];
    $result = ['success' => true, 'transaction_id' => $transaction_id, 'final_status' => $simulate_status, 'db_row' => $final];
}

/* ── 3. LIST RECENT TEST DONATIONS ────────────────────────────────────────── */
if ($action === 'list_donations') {
    try {
        $rows = $db->fetchAll("SELECT id, transaction_id, donor_name, donor_email, amount, purpose, payment_status, payment_mode, created_at, updated_at FROM donations WHERE transaction_id LIKE 'TEST_%' ORDER BY created_at DESC LIMIT 20");
        $result = ['donations' => $rows];
    } catch (Exception $e) {
        $result = ['error' => $e->getMessage()];
    }
}

/* ── 4. CLEAN UP TEST ROWS ─────────────────────────────────────────────────── */
if ($action === 'cleanup') {
    try {
        $db->query("DELETE FROM donations WHERE transaction_id LIKE 'TEST_%'");
        $result = ['success' => true, 'msg' => 'All TEST_ donation rows deleted from database ✓'];
    } catch (Exception $e) {
        $result = ['error' => $e->getMessage()];
    }
}

/* ── 5. CHECK ENV / CONFIG ─────────────────────────────────────────────────── */
if ($action === 'check_env') {
    $checks = [];
    $constants = ['PAYTM_MID', 'PAYTM_MERCHANT_KEY', 'PAYTM_WEBSITE', 'PAYTM_CALLBACK_URL', 'PAYTM_ENV', 'DB_HOST', 'DB_NAME', 'DB_USER'];
    foreach ($constants as $c) {
        $val = defined($c) ? constant($c) : null;
        $placeholder_vals = ['your_live_merchant_id', 'your_live_merchant_key', '', null];
        $is_real = $val !== null && !in_array($val, $placeholder_vals, true);
        $checks[$c] = [
            'defined' => defined($c),
            'is_real' => $is_real,
            'value'   => ($c === 'PAYTM_MERCHANT_KEY' && $is_real)
                            ? substr($val, 0, 4) . str_repeat('*', max(0, strlen($val) - 4))
                            : ($c === 'DB_USER' ? $val : (($is_real && strlen($val ?? '') > 40) ? substr($val, 0, 40) . '...' : $val)),
        ];
    }
    // DB connection test
    try {
        $db->fetch("SELECT 1");
        $checks['DB_CONNECTION'] = ['defined' => true, 'is_real' => true, 'value' => 'Connected successfully ✓'];
    } catch (Exception $e) {
        $checks['DB_CONNECTION'] = ['defined' => false, 'is_real' => false, 'value' => 'FAILED: ' . $e->getMessage()];
    }
    // PaytmChecksum class
    $checks['PaytmChecksum_class'] = [
        'defined' => file_exists(__DIR__ . '/includes/PaytmChecksum.php'),
        'is_real' => file_exists(__DIR__ . '/includes/PaytmChecksum.php'),
        'value'   => file_exists(__DIR__ . '/includes/PaytmChecksum.php') ? 'File found ✓' : '⚠ File missing at includes/PaytmChecksum.php',
    ];
    $result = ['checks' => $checks];
}

render:
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NGOV2 — Payment Test Harness</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;padding:20px;line-height:1.6}
.container{max-width:900px;margin:0 auto}
.header{background:linear-gradient(135deg,#21808d,#1a6670);padding:30px;border-radius:12px;margin-bottom:24px}
.header h1{font-size:24px;margin-bottom:6px}
.header p{opacity:.85;font-size:14px}
.warning{background:#451a03;border-left:4px solid #f97316;padding:14px 18px;border-radius:8px;margin-bottom:24px;font-size:13px;color:#fdba74}
.warning strong{color:#f97316}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.card{background:#1e293b;padding:24px;border-radius:12px;border:1px solid #334155}
.card h2{font-size:16px;color:#7dd3fc;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.form-group{margin-bottom:14px}
label{display:block;font-size:13px;color:#94a3b8;margin-bottom:5px}
input,select{width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:14px}
input:focus,select:focus{outline:none;border-color:#21808d}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;width:100%;justify-content:center;margin-top:4px}
.btn-teal{background:#21808d;color:#fff}.btn-teal:hover{background:#1a6670}
.btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d}
.btn-red{background:#b91c1c;color:#fff}.btn-red:hover{background:#991b1b}
.btn-gray{background:#334155;color:#e2e8f0}.btn-gray:hover{background:#475569}
.results{background:#1e293b;border-radius:12px;border:1px solid #334155;overflow:hidden;margin-bottom:24px}
.results-header{background:#0f172a;padding:14px 20px;font-size:14px;font-weight:600;color:#7dd3fc;border-bottom:1px solid #334155}
.log-item{padding:12px 20px;border-bottom:1px solid #1e293b;display:flex;gap:12px;font-size:13px;align-items:flex-start}
.log-item:last-child{border-bottom:none}
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;flex-shrink:0;margin-top:2px;text-transform:uppercase}
.badge-ok{background:#14532d;color:#86efac}
.badge-warn{background:#451a03;color:#fbbf24}
.badge-error{background:#450a0a;color:#fca5a5}
.badge-info{background:#1e3a5f;color:#93c5fd}
.log-step{color:#94a3b8;font-size:12px;min-width:180px;flex-shrink:0}
.log-msg{flex:1;color:#e2e8f0}
.data-toggle{margin-top:6px;font-size:12px;color:#7dd3fc;cursor:pointer;text-decoration:underline;background:none;border:none;padding:0}
pre{background:#0f172a;border-radius:6px;padding:12px;font-size:12px;overflow-x:auto;margin-top:8px;border:1px solid #334155;color:#a5f3fc}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#0f172a;color:#7dd3fc;padding:10px 12px;text-align:left;font-weight:600}
td{padding:10px 12px;border-bottom:1px solid #1e293b;color:#cbd5e1}
.status-completed{color:#86efac;font-weight:600}
.status-failed{color:#fca5a5;font-weight:600}
.status-pending{color:#fbbf24;font-weight:600}
.check-ok{color:#86efac}
.check-warn{color:#fbbf24}
.check-fail{color:#fca5a5}
.section-divider{height:1px;background:#334155;margin:24px 0}
.logout{float:right;font-size:12px;color:#64748b;text-decoration:none;margin-top:4px}
.full-width{grid-column:1/-1}
</style>
</head>
<body>
<div class="container">

  <div class="header">
    <a href="?action=logout" class="logout" onclick="document.cookie='PHPSESSID=;expires=Thu,01 Jan 1970 00:00:00 GMT'">Logout</a>
    <h1>🧪 NGOV2 — Payment Flow Test Harness</h1>
    <p>Mock-test the full donation → initiate → callback → DB cycle without real Paytm keys</p>
  </div>

  <div class="warning">
    <strong>⚠ DEV ONLY</strong> — Delete <code>test-payment.php</code> from your server before going live.
    This file bypasses checksum verification and exposes internal data.
  </div>

  <!-- ENV CHECK -->
  <div class="card" style="margin-bottom:24px">
    <h2>🔧 Environment &amp; Config Check</h2>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="check_env">
      <button type="submit" class="btn btn-gray" style="width:auto;padding:8px 20px">Run Config Check</button>
    </form>
    <?php if (($action === 'check_env') && isset($result['checks'])): ?>
    <div style="margin-top:16px">
      <table>
        <thead><tr><th>Constant / Check</th><th>Status</th><th>Value</th></tr></thead>
        <tbody>
          <?php foreach ($result['checks'] as $key => $check): ?>
          <tr>
            <td><code><?= htmlspecialchars($key) ?></code></td>
            <td>
              <?php if ($check['is_real']): ?>
                <span class="check-ok">✅ Ready</span>
              <?php else: ?>
                <span class="check-warn">⚠ Placeholder / Missing</span>
              <?php endif; ?>
            </td>
            <td style="word-break:break-all"><?= htmlspecialchars((string)($check['value'] ?? '—')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="grid">
    <!-- STEP 1: CREATE DONATION -->
    <div class="card">
      <h2>📝 Step 1 — Create Donation</h2>
      <form method="POST">
        <input type="hidden" name="action" value="create_donation">
        <div class="form-group"><label>Donor Name</label><input name="donor_name" value="Test Donor" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="donor_email" value="test@example.com" required></div>
        <div class="form-group"><label>Phone</label><input name="donor_phone" value="9999999999"></div>
        <div class="form-group"><label>Amount (₹)</label><input type="number" name="amount" value="500" min="1" required></div>
        <div class="form-group">
          <label>Purpose</label>
          <select name="purpose">
            <option>General Donation</option>
            <option>Poor Feeding Program</option>
            <option>Medical Camp</option>
            <option>Disaster Relief</option>
            <option>Spiritual Events</option>
          </select>
        </div>
        <button type="submit" class="btn btn-teal">▶ Create &amp; Initiate</button>
      </form>
    </div>

    <!-- STEP 2: SIMULATE CALLBACK -->
    <div class="card">
      <h2>📡 Step 2 — Simulate Paytm Callback</h2>
      <form method="POST">
        <input type="hidden" name="action" value="simulate_callback">
        <div class="form-group">
          <label>Transaction ID <small style="color:#64748b">(from Step 1 above)</small></label>
          <input name="transaction_id"
            value="<?= htmlspecialchars(($result['transaction_id'] ?? ($result['db_row']['transaction_id'] ?? ''))) ?>"
            placeholder="TEST_XXXXXXXX" required>
        </div>
        <div class="form-group">
          <label>Simulate Status</label>
          <select name="simulate_status">
            <option value="TXN_SUCCESS">✅ TXN_SUCCESS — Payment completed</option>
            <option value="PENDING">⏳ PENDING — Payment in transit</option>
            <option value="TXN_FAILURE">❌ TXN_FAILURE — Payment failed</option>
          </select>
        </div>
        <button type="submit" class="btn btn-green">▶ Send Mock Callback</button>
      </form>
    </div>

    <!-- VIEW DONATIONS -->
    <div class="card">
      <h2>📋 View Test Donations</h2>
      <p style="color:#94a3b8;font-size:13px;margin-bottom:14px">Lists all rows where transaction_id starts with TEST_</p>
      <form method="POST">
        <input type="hidden" name="action" value="list_donations">
        <button type="submit" class="btn btn-gray">🔍 Load from DB</button>
      </form>
    </div>

    <!-- CLEANUP -->
    <div class="card">
      <h2>🧹 Clean Up</h2>
      <p style="color:#94a3b8;font-size:13px;margin-bottom:14px">Deletes all TEST_ rows from the donations table after testing</p>
      <form method="POST" onsubmit="return confirm('Delete all TEST_ donations from DB?')">
        <input type="hidden" name="action" value="cleanup">
        <button type="submit" class="btn btn-red">🗑 Delete Test Rows</button>
      </form>
    </div>
  </div>

  <?php if (!empty($log)): ?>
  <!-- AUDIT LOG -->
  <div class="results">
    <div class="results-header">📋 Audit Trail</div>
    <?php foreach ($log as $entry): ?>
    <div class="log-item">
      <span class="badge badge-<?= htmlspecialchars($entry['status']) ?>"><?= htmlspecialchars($entry['status']) ?></span>
      <span class="log-step"><?= htmlspecialchars($entry['step']) ?></span>
      <div class="log-msg">
        <?= htmlspecialchars($entry['msg']) ?>
        <?php if (!empty($entry['data'])): ?>
        <br><button class="data-toggle" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">Toggle raw data</button>
        <pre style="display:none"><?= htmlspecialchars(json_encode($entry['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($result['error'])): ?>
  <div style="background:#450a0a;border-left:4px solid #ef4444;padding:14px 18px;border-radius:8px;margin-bottom:24px;color:#fca5a5;font-size:14px">
    ❌ <strong>Error:</strong> <?= htmlspecialchars($result['error']) ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($result['msg']) && !empty($result['success'])): ?>
  <div style="background:#14532d;border-left:4px solid #22c55e;padding:14px 18px;border-radius:8px;margin-bottom:24px;color:#86efac;font-size:14px">
    ✅ <?= htmlspecialchars($result['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- TABLE VIEW -->
  <?php if ($action === 'list_donations' && isset($result['donations'])): ?>
  <div class="results">
    <div class="results-header">🗄 Test Donations in Database (last 20)</div>
    <?php if (empty($result['donations'])): ?>
    <div style="padding:24px;color:#64748b;text-align:center">No TEST_ donations found in DB yet.</div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Transaction ID</th><th>Donor</th><th>Amount</th><th>Purpose</th><th>Status</th><th>Payment Mode</th><th>Created</th></tr></thead>
      <tbody>
        <?php foreach ($result['donations'] as $row): ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><code style="font-size:11px"><?= htmlspecialchars($row['transaction_id']) ?></code></td>
          <td><?= htmlspecialchars($row['donor_name']) ?></td>
          <td>₹<?= number_format((float)$row['amount'], 2) ?></td>
          <td><?= htmlspecialchars($row['purpose']) ?></td>
          <td><span class="status-<?= htmlspecialchars($row['payment_status']) ?>"><?= htmlspecialchars($row['payment_status']) ?></span></td>
          <td><?= htmlspecialchars($row['payment_mode'] ?? '—') ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($row['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- GUIDE -->
  <div class="card" style="margin-top:8px">
    <h2>📖 How to Test</h2>
    <ol style="padding-left:20px;line-height:2.2;color:#94a3b8;font-size:14px">
      <li>Click <strong style="color:#e2e8f0">Run Config Check</strong> — verify DB connects and see which Paytm keys are still placeholder</li>
      <li>Fill <strong style="color:#e2e8f0">Step 1</strong> and click Create &amp; Initiate — a real DB row is inserted</li>
      <li>Copy the <code style="color:#a5f3fc">transaction_id</code> from the audit trail into <strong style="color:#e2e8f0">Step 2</strong></li>
      <li>Choose <strong>TXN_SUCCESS</strong> → click Send Mock Callback → verify audit trail shows DB updated to <span class="status-completed">completed</span></li>
      <li>Repeat Step 1-4 with <strong>PENDING</strong> then <strong>TXN_FAILURE</strong> to test all three branches</li>
      <li>Click <strong style="color:#e2e8f0">View Test Donations</strong> to confirm all rows are in the DB correctly</li>
      <li>Click <strong style="color:#e2e8f0">Clean Up</strong> to wipe test rows</li>
      <li style="color:#f97316">Once real Paytm keys arrive → paste them into <code>.env</code> → re-run Config Check → if all green, delete this file</li>
    </ol>
  </div>

</div>
</body>
</html>
