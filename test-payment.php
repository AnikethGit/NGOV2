<?php
/**
 * test-payment.php  — NGOV2 Payment Flow Mock Test Harness
 * DEV ONLY — delete from server before going live.
 */

define('TEST_PASSWORD', 'ngov2test2026');

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

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

$db  = Database::getInstance();
$pdo = $db->getConnection();
$log    = [];
$result = null;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ─── Helper: get real column names for a table ──────────────────────────────
function getColumns(PDO $pdo, string $table): array {
    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($stmt->fetchAll() as $row) $cols[] = $row['Field'];
    } catch (Exception $e) {}
    return $cols;
}

/* ── 1. CREATE DONATION ──────────────────────────────────────────────────── */
if ($action === 'create_donation') {
    $amount      = (float)($_POST['amount']      ?? 500);
    $donor_name  = trim($_POST['donor_name']     ?? 'Test Donor');
    $donor_email = trim($_POST['donor_email']    ?? 'test@example.com');
    $donor_phone = trim($_POST['donor_phone']    ?? '9999999999');
    $cause       = trim($_POST['cause']          ?? 'general'); // matches DB column name
    $frequency   = trim($_POST['frequency']      ?? 'one-time');

    if ($amount < 1) { $result = ['error' => 'Amount must be ≥ ₹1']; goto render; }

    $transaction_id = 'TEST_' . strtoupper(uniqid());

    // Detect live schema
    $existing_cols = getColumns($pdo, 'donations');
    $log[] = ['status' => 'info', 'step' => '0 — Schema Check',
        'msg'  => 'donations columns: ' . (empty($existing_cols) ? 'TABLE NOT FOUND' : implode(', ', $existing_cols)),
        'data' => null];

    if (empty($existing_cols)) {
        $log[]  = ['status' => 'error', 'step' => '0 — Schema Check', 'msg' => 'The donations table does not exist. Run schema.sql first.', 'data' => null];
        $result = ['error' => 'donations table not found'];
        goto render;
    }

    // Core fields (these must exist — if missing, show a clear error per field)
    $core = [
        'transaction_id' => $transaction_id,
        'donor_name'     => $donor_name,
        'donor_email'    => $donor_email,
        'donor_phone'    => $donor_phone,
        'amount'         => $amount,
    ];

    // Optional fields — only inserted if column exists in live table
    // Maps: PHP value  =>  DB column name
    $optional_map = [
        'cause'                => $cause,
        'frequency'            => $frequency,
        'currency'             => 'INR',
        'payment_status'       => 'pending',
        'payment_method'       => 'Paytm',
        'payment_gateway'      => 'paytm',
        'is_anonymous'         => 0,
        'is_recurring'         => ($frequency !== 'one-time') ? 1 : 0,
        'tax_exemption_amount' => round($amount * 0.5, 2),
        'status'               => 'pending',
        'created_at'           => date('Y-m-d H:i:s'),
        'updated_at'           => date('Y-m-d H:i:s'),
    ];

    $fields = $core;
    foreach ($optional_map as $col => $val) {
        if (in_array($col, $existing_cols, true)) $fields[$col] = $val;
    }

    $col_list     = implode(', ', array_keys($fields));
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $values       = array_values($fields);

    $log[] = ['status' => 'info', 'step' => '1a — INSERT Preview',
        'msg'  => "INSERT INTO donations ({$col_list}) VALUES (...)",
        'data' => $fields];

    try {
        $stmt = $pdo->prepare("INSERT INTO donations ({$col_list}) VALUES ({$placeholders})");
        $stmt->execute($values);
        $inserted_id = $pdo->lastInsertId();

        $donation = $db->fetch("SELECT * FROM donations WHERE id = ?", [$inserted_id]);
        $log[] = ['status' => 'ok',   'step' => '1 — Create Donation', 'msg' => "Row inserted ✓  ID: {$inserted_id}",   'data' => $donation];
        $log[] = ['status' => 'ok',   'step' => '2 — Transaction ID',  'msg' => $transaction_id,                       'data' => null];
        $log[] = ['status' => 'info', 'step' => '3 — Amount',          'msg' => '₹' . number_format($amount, 2),       'data' => null];

        // Paytm param preview
        $paytm_params = [
            'MID'              => defined('PAYTM_MID')          ? PAYTM_MID          : 'MOCK_MID',
            'WEBSITE'          => defined('PAYTM_WEBSITE')      ? PAYTM_WEBSITE      : 'WEBSTAGING',
            'CHANNEL_ID'       => 'WEB',
            'INDUSTRY_TYPE_ID' => 'Ecommerce',
            'ORDER_ID'         => $transaction_id,
            'CUST_ID'          => 'CUST_' . ($donation['user_id'] ?? $inserted_id),
            'TXN_AMOUNT'       => number_format($amount, 2, '.', ''),
            'CALLBACK_URL'     => defined('PAYTM_CALLBACK_URL') ? PAYTM_CALLBACK_URL : 'https://yourdomain.com/api/payment-callback.php',
            'EMAIL'            => $donor_email,
            'MOBILE_NO'        => preg_replace('/[^0-9]/', '', $donor_phone),
        ];
        $log[] = ['status' => 'ok', 'step' => '4 — Paytm Params', 'msg' => 'Params built (checksum skipped in mock)', 'data' => $paytm_params];

        $mid_ok = defined('PAYTM_MID') && !in_array(PAYTM_MID, ['your_live_merchant_id', '']);
        $key_ok = defined('PAYTM_MERCHANT_KEY') && !in_array(PAYTM_MERCHANT_KEY, ['your_live_merchant_key', '']);
        $log[] = ['status' => $mid_ok ? 'ok' : 'warn', 'step' => '5 — PAYTM_MID',
            'msg' => $mid_ok ? 'Real MID found (' . substr(PAYTM_MID, 0, 4) . '****)' : 'Placeholder — update .env when keys arrive', 'data' => null];
        $log[] = ['status' => $key_ok ? 'ok' : 'warn', 'step' => '6 — MERCHANT_KEY',
            'msg' => $key_ok ? 'Key found (length: ' . strlen(PAYTM_MERCHANT_KEY) . ')' : 'Placeholder — update .env when keys arrive', 'data' => null];

        $result = ['success' => true, 'transaction_id' => $transaction_id, 'amount' => $amount];

    } catch (PDOException $e) {
        $log[]  = ['status' => 'error', 'step' => '1 — Create Donation',
            'msg'  => 'PDO Error: ' . $e->getMessage() . ' (SQLSTATE: ' . $e->getCode() . ')',
            'data' => null];
        $result = ['error' => 'PDO: ' . $e->getMessage()];
    } catch (Exception $e) {
        $log[]  = ['status' => 'error', 'step' => '1 — Create Donation', 'msg' => $e->getMessage(), 'data' => null];
        $result = ['error' => $e->getMessage()];
    }
}

/* ── 2. SIMULATE CALLBACK ────────────────────────────────────────────────── */
if ($action === 'simulate_callback') {
    $transaction_id  = trim($_POST['transaction_id'] ?? '');
    $simulate_status = $_POST['simulate_status'] ?? 'TXN_SUCCESS';

    if (empty($transaction_id)) { $result = ['error' => 'Transaction ID required']; goto render; }

    $donation = $db->fetch("SELECT * FROM donations WHERE transaction_id = ?", [$transaction_id]);
    if (!$donation) { $result = ['error' => 'Donation not found: ' . $transaction_id]; goto render; }

    $log[] = ['status' => 'ok', 'step' => '1 — Found Donation',
        'msg' => "ID {$donation['id']} | Status: {$donation['payment_status']} | Amount: ₹{$donation['amount']}", 'data' => null];

    if ($donation['payment_status'] === 'completed') {
        $log[] = ['status' => 'warn', 'step' => '2 — Duplicate Guard',
            'msg' => 'Already completed — idempotency guard skips re-processing ✓', 'data' => null];
        $result = ['success' => true, 'already_complete' => true, 'transaction_id' => $transaction_id];
        goto render;
    }

    $mock_txn_id  = 'MOCK_TXN_' . strtoupper(uniqid());
    $mock_callback = [
        'ORDERID'     => $transaction_id,
        'TXNID'       => $mock_txn_id,
        'STATUS'      => $simulate_status,
        'TXNAMOUNT'   => number_format((float)$donation['amount'], 2, '.', ''),
        'RESPCODE'    => $simulate_status === 'TXN_SUCCESS' ? '01' : ($simulate_status === 'PENDING' ? '227' : '141'),
        'RESPMSG'     => $simulate_status === 'TXN_SUCCESS' ? 'Txn Success' : ($simulate_status === 'PENDING' ? 'Txn Pending' : 'Txn Failed'),
        'BANKTXNID'   => 'BANK_' . rand(100000, 999999),
        'PAYMENTMODE' => 'UPI',
    ];
    $log[] = ['status' => 'info', 'step' => '2 — Mock Callback', 'msg' => 'Checksum + API verify skipped in mock', 'data' => $mock_callback];
    $log[] = ['status' => 'ok',   'step' => '3 — Checksum',     'msg' => 'MOCK: skipped', 'data' => null];
    $log[] = ['status' => 'ok',   'step' => '4 — API Verify',   'msg' => 'MOCK: skipped', 'data' => null];

    if ($simulate_status === 'TXN_SUCCESS') {
        if ((float)$mock_callback['TXNAMOUNT'] < (float)$donation['amount']) {
            $log[] = ['status' => 'error', 'step' => '5 — Amount Check',
                'msg' => "FAIL: ₹{$mock_callback['TXNAMOUNT']} < ₹{$donation['amount']}", 'data' => null];
            $pdo->prepare("UPDATE donations SET payment_status='failed', updated_at=NOW() WHERE transaction_id=?")->execute([$transaction_id]);
            $result = ['error' => 'Amount mismatch']; goto render;
        }
        $log[] = ['status' => 'ok', 'step' => '5 — Amount Check', 'msg' => "PASS ✓ ₹{$mock_callback['TXNAMOUNT']}", 'data' => null];
    }

    $existing_cols = getColumns($pdo, 'donations');

    try {
        if ($simulate_status === 'TXN_SUCCESS') {
            $upd = ['payment_status' => 'completed'];
            $map = [
                'paytm_order_id'       => $transaction_id,
                'paytm_transaction_id' => $mock_txn_id,
                'payment_mode'         => 'UPI',
                'bank_txn_id'          => $mock_callback['BANKTXNID'],
                'paytm_response_code'  => $mock_callback['RESPCODE'],
                'paytm_response_msg'   => $mock_callback['RESPMSG'],
            ];
            foreach ($map as $c => $v) if (in_array($c, $existing_cols, true)) $upd[$c] = $v;
            $set = implode(', ', array_map(fn($c) => "`{$c}`=?", array_keys($upd))) . ', updated_at=NOW()';
            $pdo->prepare("UPDATE donations SET {$set} WHERE transaction_id=?")->execute([...array_values($upd), $transaction_id]);
            $log[] = ['status' => 'ok', 'step' => '6 — DB Update', 'msg' => "payment_status → 'completed' ✓", 'data' => null];
            $log[] = ['status' => 'ok', 'step' => '7 — Redirect',  'msg' => "/payment-success.html?txn={$transaction_id}&status=success", 'data' => null];
        } elseif ($simulate_status === 'PENDING') {
            $upd = ['payment_status' => 'pending'];
            if (in_array('paytm_transaction_id', $existing_cols, true)) $upd['paytm_transaction_id'] = $mock_txn_id;
            $set = implode(', ', array_map(fn($c) => "`{$c}`=?", array_keys($upd))) . ', updated_at=NOW()';
            $pdo->prepare("UPDATE donations SET {$set} WHERE transaction_id=?")->execute([...array_values($upd), $transaction_id]);
            $log[] = ['status' => 'warn', 'step' => '6 — DB Update', 'msg' => "payment_status → 'pending' (in transit)", 'data' => null];
        } else {
            $upd = ['payment_status' => 'failed'];
            foreach (['paytm_transaction_id' => $mock_txn_id, 'paytm_response_code' => $mock_callback['RESPCODE'], 'paytm_response_msg' => $mock_callback['RESPMSG']] as $c => $v)
                if (in_array($c, $existing_cols, true)) $upd[$c] = $v;
            $set = implode(', ', array_map(fn($c) => "`{$c}`=?", array_keys($upd))) . ', updated_at=NOW()';
            $pdo->prepare("UPDATE donations SET {$set} WHERE transaction_id=?")->execute([...array_values($upd), $transaction_id]);
            $log[] = ['status' => 'error', 'step' => '6 — DB Update', 'msg' => "payment_status → 'failed'", 'data' => null];
        }
    } catch (PDOException $e) {
        $log[] = ['status' => 'error', 'step' => '6 — DB Update', 'msg' => 'PDO: ' . $e->getMessage(), 'data' => null];
        $result = ['error' => $e->getMessage()]; goto render;
    }

    $final = $db->fetch("SELECT * FROM donations WHERE transaction_id=?", [$transaction_id]);
    $log[]  = ['status' => 'info', 'step' => '8 — Final DB Row', 'msg' => 'All fields below', 'data' => $final];
    $result = ['success' => true, 'transaction_id' => $transaction_id, 'final_status' => $simulate_status, 'db_row' => $final];
}

/* ── 3. LIST RECENT TEST DONATIONS ──────────────────────────────────────── */
if ($action === 'list_donations') {
    try {
        $rows  = $db->fetchAll("SELECT * FROM donations WHERE transaction_id LIKE 'TEST_%' ORDER BY created_at DESC LIMIT 20");
        $result = ['donations' => $rows];
    } catch (PDOException $e) { $result = ['error' => 'PDO: ' . $e->getMessage()]; }
      catch (Exception   $e) { $result = ['error' => $e->getMessage()]; }
}

/* ── 4. CLEAN UP ─────────────────────────────────────────────────────────── */
if ($action === 'cleanup') {
    try {
        $db->query("DELETE FROM donations WHERE transaction_id LIKE 'TEST_%'");
        $result = ['success' => true, 'msg' => 'All TEST_ rows deleted ✓'];
    } catch (Exception $e) { $result = ['error' => $e->getMessage()]; }
}

/* ── 5. CHECK ENV / CONFIG ────────────────────────────────────────────────── */
if ($action === 'check_env') {
    $checks = [];
    foreach (['PAYTM_MID', 'PAYTM_MERCHANT_KEY', 'PAYTM_WEBSITE', 'PAYTM_CALLBACK_URL', 'PAYTM_ENV'] as $c) {
        $val = defined($c) ? constant($c) : null;
        $real = $val !== null && !in_array($val, ['your_live_merchant_id', 'your_live_merchant_key', ''], true);
        $checks[$c] = ['is_real' => $real, 'value' => ($c === 'PAYTM_MERCHANT_KEY' && $real) ? substr($val, 0, 4) . str_repeat('*', max(0, strlen($val) - 4)) : $val];
    }
    $cfg = Config::db();
    foreach (['host' => 'DB_HOST', 'name' => 'DB_NAME', 'user' => 'DB_USER'] as $k => $l)
        $checks[$l] = ['is_real' => !empty($cfg[$k]), 'value' => $cfg[$k] ?? '—'];
    $checks['DB_PASS'] = ['is_real' => !empty($cfg['pass']), 'value' => !empty($cfg['pass']) ? '(set, ' . strlen($cfg['pass']) . ' chars) ✓' : '— (empty)'];
    try { $db->fetch("SELECT 1"); $checks['DB_CONNECTION'] = ['is_real' => true,  'value' => 'Connected ✓']; }
    catch (Exception $e) {         $checks['DB_CONNECTION'] = ['is_real' => false, 'value' => 'FAILED: ' . $e->getMessage()]; }
    $cols = getColumns($pdo, 'donations');
    $checks['donations_table'] = ['is_real' => !empty($cols), 'value' => empty($cols) ? '⚠ Not found — run schema.sql' : implode(', ', $cols)];
    $checks['PaytmChecksum_class'] = ['is_real' => file_exists(__DIR__ . '/includes/PaytmChecksum.php'), 'value' => file_exists(__DIR__ . '/includes/PaytmChecksum.php') ? 'Found ✓' : '⚠ Missing'];
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
.container{max-width:960px;margin:0 auto}
.header{background:linear-gradient(135deg,#21808d,#1a6670);padding:30px;border-radius:12px;margin-bottom:24px}
.header h1{font-size:22px;margin-bottom:6px}
.header p{opacity:.8;font-size:13px}
.warning{background:#451a03;border-left:4px solid #f97316;padding:12px 18px;border-radius:8px;margin-bottom:20px;font-size:13px;color:#fdba74}
.warning strong{color:#f97316}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.card{background:#1e293b;padding:22px;border-radius:12px;border:1px solid #334155}
.card h2{font-size:15px;color:#7dd3fc;margin-bottom:14px}
.form-group{margin-bottom:12px}
label{display:block;font-size:12px;color:#94a3b8;margin-bottom:4px}
input,select{width:100%;padding:9px 12px;background:#0f172a;border:1px solid #334155;border-radius:7px;color:#e2e8f0;font-size:13px}
input:focus,select:focus{outline:none;border-color:#21808d}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;width:100%;justify-content:center;margin-top:4px;transition:background .15s}
.btn-teal{background:#21808d;color:#fff}.btn-teal:hover{background:#1a6670}
.btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d}
.btn-red{background:#b91c1c;color:#fff}.btn-red:hover{background:#991b1b}
.btn-gray{background:#334155;color:#e2e8f0}.btn-gray:hover{background:#475569}
.results{background:#1e293b;border-radius:12px;border:1px solid #334155;overflow:hidden;margin-bottom:20px}
.results-header{background:#0f172a;padding:12px 18px;font-size:13px;font-weight:600;color:#7dd3fc;border-bottom:1px solid #334155}
.log-item{padding:11px 18px;border-bottom:1px solid #1a2540;display:flex;gap:10px;font-size:12px;align-items:flex-start}
.log-item:last-child{border-bottom:none}
.badge{padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;flex-shrink:0;margin-top:1px;text-transform:uppercase;letter-spacing:.05em}
.badge-ok{background:#14532d;color:#86efac}.badge-warn{background:#451a03;color:#fbbf24}
.badge-error{background:#450a0a;color:#fca5a5}.badge-info{background:#1e3a5f;color:#93c5fd}
.log-step{color:#64748b;font-size:11px;min-width:170px;flex-shrink:0;padding-top:1px}
.log-msg{flex:1;color:#e2e8f0;word-break:break-word}
.data-toggle{margin-top:5px;font-size:11px;color:#7dd3fc;cursor:pointer;text-decoration:underline;background:none;border:none;padding:0}
pre{background:#0f172a;border-radius:6px;padding:10px;font-size:11px;overflow-x:auto;margin-top:6px;border:1px solid #334155;color:#a5f3fc}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12px}
th{background:#0f172a;color:#7dd3fc;padding:9px 12px;text-align:left;font-weight:600;white-space:nowrap}
td{padding:9px 12px;border-bottom:1px solid #1a2540;color:#cbd5e1;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.s-completed{color:#86efac;font-weight:600}.s-failed{color:#fca5a5;font-weight:600}.s-pending{color:#fbbf24;font-weight:600}
.ok{color:#86efac}.warn{color:#fbbf24}.fail{color:#fca5a5}
.env-val{font-size:11px;color:#94a3b8;word-break:break-all;max-width:380px}
</style>
</head>
<body>
<div class="container">

  <div class="header">
    <h1>🧪 NGOV2 — Payment Flow Test Harness</h1>
    <p>Mock-test donation → initiate → callback → DB without real Paytm keys</p>
  </div>

  <div class="warning">
    <strong>⚠ DEV ONLY</strong> — Delete this file from the server before going live.
  </div>

  <!-- ENV CHECK -->
  <div class="card" style="margin-bottom:20px">
    <h2>🔧 Environment &amp; Config Check</h2>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="check_env">
      <button type="submit" class="btn btn-gray" style="width:auto">Run Config Check</button>
    </form>
    <?php if ($action === 'check_env' && isset($result['checks'])): ?>
    <div style="margin-top:14px;overflow-x:auto">
      <table>
        <thead><tr><th>Key</th><th>Status</th><th>Value</th></tr></thead>
        <tbody>
          <?php foreach ($result['checks'] as $k => $c): ?>
          <tr>
            <td><code style="font-size:11px"><?= htmlspecialchars($k) ?></code></td>
            <td><?= $c['is_real'] ? '<span class="ok">✅ Ready</span>' : '<span class="warn">⚠ Missing</span>' ?></td>
            <td class="env-val"><?= htmlspecialchars((string)($c['value'] ?? '—')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="grid">
    <div class="card">
      <h2>📝 Step 1 — Create Donation</h2>
      <form method="POST">
        <input type="hidden" name="action" value="create_donation">
        <div class="form-group"><label>Donor Name</label><input name="donor_name" value="Test Donor"></div>
        <div class="form-group"><label>Email</label><input type="email" name="donor_email" value="test@example.com"></div>
        <div class="form-group"><label>Phone</label><input name="donor_phone" value="9999999999"></div>
        <div class="form-group"><label>Amount (₹)</label><input type="number" name="amount" value="500" min="1"></div>
        <div class="form-group">
          <label>Cause</label>
          <select name="cause">
            <option value="general">General Donation</option>
            <option value="poor-feeding">Poor Feeding Program</option>
            <option value="medical">Medical Camp</option>
            <option value="disaster">Disaster Relief</option>
            <option value="education">Education</option>
          </select>
        </div>
        <div class="form-group">
          <label>Frequency</label>
          <select name="frequency">
            <option value="one-time">One-time</option>
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
        </div>
        <button class="btn btn-teal">▶ Create &amp; Initiate</button>
      </form>
    </div>

    <div class="card">
      <h2>📡 Step 2 — Simulate Callback</h2>
      <form method="POST">
        <input type="hidden" name="action" value="simulate_callback">
        <div class="form-group">
          <label>Transaction ID</label>
          <input name="transaction_id"
            value="<?= htmlspecialchars($result['transaction_id'] ?? ($result['db_row']['transaction_id'] ?? '')) ?>"
            placeholder="TEST_XXXXXXXX">
        </div>
        <div class="form-group">
          <label>Simulate Status</label>
          <select name="simulate_status">
            <option value="TXN_SUCCESS">✅ TXN_SUCCESS</option>
            <option value="PENDING">⏳ PENDING</option>
            <option value="TXN_FAILURE">❌ TXN_FAILURE</option>
          </select>
        </div>
        <button class="btn btn-green">▶ Send Mock Callback</button>
      </form>
    </div>

    <div class="card">
      <h2>📋 View Test Donations</h2>
      <p style="color:#64748b;font-size:12px;margin-bottom:12px">All rows where transaction_id starts with TEST_</p>
      <form method="POST"><input type="hidden" name="action" value="list_donations">
        <button class="btn btn-gray">🔍 Load from DB</button>
      </form>
    </div>

    <div class="card">
      <h2>🧹 Clean Up</h2>
      <p style="color:#64748b;font-size:12px;margin-bottom:12px">Delete all TEST_ rows from donations table</p>
      <form method="POST" onsubmit="return confirm('Delete all TEST_ rows?')"><input type="hidden" name="action" value="cleanup">
        <button class="btn btn-red">🗑 Delete Test Rows</button>
      </form>
    </div>
  </div>

  <?php if (!empty($log)): ?>
  <div class="results">
    <div class="results-header">📋 Audit Trail</div>
    <?php foreach ($log as $e): ?>
    <div class="log-item">
      <span class="badge badge-<?= $e['status'] ?>"><?= $e['status'] ?></span>
      <span class="log-step"><?= htmlspecialchars($e['step']) ?></span>
      <div class="log-msg">
        <?= htmlspecialchars($e['msg']) ?>
        <?php if (!empty($e['data'])): ?>
        <br><button class="data-toggle" onclick="var p=this.nextElementSibling;p.style.display=p.style.display==='none'?'block':'none'">Toggle data</button>
        <pre style="display:none"><?= htmlspecialchars(json_encode($e['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($result['error'])): ?>
  <div style="background:#450a0a;border-left:4px solid #ef4444;padding:14px 18px;border-radius:8px;margin-bottom:20px;color:#fca5a5;font-size:13px">
    ❌ <strong>Error:</strong> <?= htmlspecialchars($result['error']) ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($result['success']) && !empty($result['msg'])): ?>
  <div style="background:#14532d;border-left:4px solid #22c55e;padding:14px 18px;border-radius:8px;margin-bottom:20px;color:#86efac;font-size:13px">
    ✅ <?= htmlspecialchars($result['msg']) ?>
  </div>
  <?php endif; ?>

  <?php if ($action === 'list_donations' && isset($result['donations'])): ?>
  <div class="results">
    <div class="results-header">🗄 Test Donations (last 20)</div>
    <?php if (empty($result['donations'])): ?>
    <div style="padding:20px;color:#64748b;text-align:center">No TEST_ rows in DB yet.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>ID</th><th>Transaction ID</th><th>Donor</th><th>Amount</th><th>Cause</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
          <?php foreach ($result['donations'] as $r): ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td><code style="font-size:10px"><?= htmlspecialchars($r['transaction_id']) ?></code></td>
            <td><?= htmlspecialchars($r['donor_name']) ?></td>
            <td>₹<?= number_format((float)$r['amount'], 2) ?></td>
            <td><?= htmlspecialchars($r['cause'] ?? '—') ?></td>
            <td><span class="s-<?= htmlspecialchars($r['payment_status'] ?? 'pending') ?>"><?= htmlspecialchars($r['payment_status'] ?? '—') ?></span></td>
            <td style="font-size:11px"><?= htmlspecialchars($r['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>📖 How to Test</h2>
    <ol style="padding-left:18px;line-height:2.1;color:#64748b;font-size:13px">
      <li>Run <strong style="color:#e2e8f0">Config Check</strong> — verify DB + donations columns</li>
      <li>Fill <strong style="color:#e2e8f0">Step 1</strong> → audit trail shows INSERT with <code style="color:#a5f3fc">cause</code> column ✓</li>
      <li>Copy the <code style="color:#a5f3fc">transaction_id</code> into Step 2</li>
      <li>Run all 3 statuses: <span class="ok">TXN_SUCCESS</span>, <span class="warn">PENDING</span>, <span class="fail">TXN_FAILURE</span></li>
      <li>Use <strong style="color:#e2e8f0">View Donations</strong> to confirm DB rows look correct</li>
      <li>Clean up test rows when done</li>
      <li style="color:#f97316">When Paytm keys arrive → update .env → re-run Config Check → delete this file</li>
    </ol>
  </div>

</div>
</body>
</html>
