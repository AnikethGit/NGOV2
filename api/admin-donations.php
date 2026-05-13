<?php
/**
 * Admin Donations API
 * GET  api/admin-donations.php?action=list  — paginated, filtered donation list (admin only)
 *
 * Query params (all optional):
 *   page          int    Page number (default 1)
 *   per_page      int    Results per page (default 20, max 100)
 *   status        str    payment_status filter: completed|pending|failed
 *   cause         str    cause column value filter
 *   amount_range  str    e.g. "0-1000", "1000-5000", "5000-10000", "10000+"
 *   from          date   created_at >= YYYY-MM-DD
 *   to            date   created_at <= YYYY-MM-DD
 *   search        str    matches donor_name, donor_email or transaction_id
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
    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
    $status  = trim($_GET['status']  ?? '');
    $cause   = trim($_GET['cause']   ?? '');
    $search  = trim($_GET['search']  ?? '');
    $from    = trim($_GET['from']    ?? '');
    $to      = trim($_GET['to']      ?? '');
    $range   = trim($_GET['amount_range'] ?? '');

    $where  = [];
    $params = [];

    if ($status !== '') {
        $where[]  = 'payment_status = ?';
        $params[] = $status;
    }
    if ($cause !== '') {
        $where[]  = 'cause = ?';
        $params[] = $cause;
    }
    if ($search !== '') {
        $where[]  = '(donor_name LIKE ? OR donor_email LIKE ? OR transaction_id LIKE ?)';
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($from !== '') {
        $where[]  = 'DATE(created_at) >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[]  = 'DATE(created_at) <= ?';
        $params[] = $to;
    }
    if ($range !== '') {
        if (substr($range, -1) === '+') {
            $where[]  = 'amount >= ?';
            $params[] = (float)rtrim($range, '+');
        } elseif (strpos($range, '-') !== false) {
            list($lo, $hi) = explode('-', $range, 2);
            $where[]  = 'amount >= ?';
            $params[] = (float)$lo;
            $where[]  = 'amount <= ?';
            $params[] = (float)$hi;
        }
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $db = Database::getInstance();

    $countRow = $db->fetch("SELECT COUNT(*) AS total FROM donations {$whereSql}", $params);
    $total    = (int)($countRow['total'] ?? 0);

    $offset = ($page - 1) * $perPage;
    $qp     = array_merge($params, [$perPage, $offset]);

    $rows = $db->fetchAll(
        "SELECT id, transaction_id, donor_name, donor_email, donor_phone,
                amount, cause, payment_status, payment_mode, created_at
         FROM donations
         {$whereSql}
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?",
        $qp
    );

    ob_end_clean();
    echo json_encode([
        'success'    => true,
        'data'       => $rows,
        'pagination' => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ],
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
