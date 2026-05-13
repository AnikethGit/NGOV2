<?php
/**
 * Admin Stats API
 * GET api/admin-stats.php
 * Returns real dashboard overview stats and recent donations (admin only).
 */

if (ob_get_level() === 0) ob_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('NGOV2_SESSION');
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 7200, 'path' => '/',
        'secure'   => $is_https, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['logged_in']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    $db = Database::getInstance();

    // Overall donation totals
    $donationRow = $db->fetch(
        "SELECT
            COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) AS total_amount,
            COUNT(*) AS total_count,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN payment_status = 'pending'   THEN 1 ELSE 0 END) AS pending_count
         FROM donations"
    );

    // This month
    $thisMonthRow = $db->fetch(
        "SELECT
            COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) AS amount,
            COUNT(*) AS cnt
         FROM donations
         WHERE YEAR(created_at) = YEAR(CURDATE())
           AND MONTH(created_at) = MONTH(CURDATE())"
    );

    // Last month
    $lastMonthRow = $db->fetch(
        "SELECT COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) AS amount
         FROM donations
         WHERE YEAR(created_at)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
           AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
    );

    // Volunteer and donor counts by type + status
    $userRows = $db->fetchAll(
        "SELECT user_type, status, COUNT(*) AS cnt FROM users GROUP BY user_type, status"
    );

    $volunteers = 0; $activeVolunteers = 0; $totalDonors = 0;
    foreach ($userRows as $row) {
        if ($row['user_type'] === 'volunteer') {
            $volunteers += (int)$row['cnt'];
            if ($row['status'] === 'active') $activeVolunteers += (int)$row['cnt'];
        }
        if (in_array($row['user_type'], ['user', 'donor'])) {
            $totalDonors += (int)$row['cnt'];
        }
    }

    // Recent 5 donations
    $recentDonations = $db->fetchAll(
        "SELECT id, donor_name, donor_email, amount, cause, payment_status, created_at
         FROM donations ORDER BY created_at DESC LIMIT 5"
    );

    $totalAmount     = (float)($donationRow['total_amount']    ?? 0);
    $monthAmount     = (float)($thisMonthRow['amount']         ?? 0);
    $lastMonthAmount = (float)($lastMonthRow['amount']         ?? 0);

    $monthChangePct = 0;
    if ($lastMonthAmount > 0) {
        $monthChangePct = round((($monthAmount - $lastMonthAmount) / $lastMonthAmount) * 100, 1);
    } elseif ($monthAmount > 0) {
        $monthChangePct = 100;
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'stats'   => [
            'total_amount'      => $totalAmount,
            'total_count'       => (int)($donationRow['total_count']    ?? 0),
            'completed_count'   => (int)($donationRow['completed_count'] ?? 0),
            'pending_count'     => (int)($donationRow['pending_count']   ?? 0),
            'month_amount'      => $monthAmount,
            'month_count'       => (int)($thisMonthRow['cnt']           ?? 0),
            'month_change_pct'  => $monthChangePct,
            'total_volunteers'  => $volunteers,
            'active_volunteers' => $activeVolunteers,
            'total_donors'      => $totalDonors,
            'lives_impacted'    => (int)round($totalAmount / 100),
        ],
        'recent_donations' => $recentDonations,
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
