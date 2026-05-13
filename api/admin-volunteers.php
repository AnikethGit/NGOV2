<?php
/**
 * Admin Volunteers API
 * GET  api/admin-volunteers.php?action=list  — paginated volunteer list (admin only)
 *
 * Query params (all optional):
 *   page     int    Page number (default 1)
 *   per_page int    Results per page (default 20, max 100)
 *   status   str    active|inactive|pending
 *   from     date   registration date >= YYYY-MM-DD
 *   to       date   registration date <= YYYY-MM-DD
 *   search   str    matches full_name, email, or phone
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
    $status  = trim($_GET['status'] ?? '');
    $search  = trim($_GET['search'] ?? '');
    $from    = trim($_GET['from']   ?? '');
    $to      = trim($_GET['to']     ?? '');

    $where  = ['user_type = ?'];
    $params = ['volunteer'];

    if ($status !== '') {
        $where[]  = 'status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[]  = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
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

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $db = Database::getInstance();

    $countRow = $db->fetch("SELECT COUNT(*) AS total FROM users {$whereSql}", $params);
    $total    = (int)($countRow['total'] ?? 0);

    $offset = ($page - 1) * $perPage;
    $qp     = array_merge($params, [$perPage, $offset]);

    $rows = $db->fetchAll(
        "SELECT id, full_name, email, phone, status, last_login, created_at
         FROM users
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
