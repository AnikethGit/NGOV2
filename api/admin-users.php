<?php
/**
 * Admin Users API
 * GET  api/admin-users.php?action=list  → paginated, filtered list of users (admin only)
 *
 * Query params (all optional):
 *   page        int   Page number (default 1)
 *   per_page    int   Results per page (default 20, max 100)
 *   type        str   user_type filter: admin|volunteer|donor|user
 *   status      str   status filter: active|inactive|banned|suspended
 *   search      str   free text: matches name or email
 *   from        date  registration date >= YYYY-MM-DD
 *   to          date  registration date <= YYYY-MM-DD
 */

if (ob_get_level() === 0) ob_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';

// Start session (must match other APIs)
if (session_status() === PHP_SESSION_NONE) {
    session_name('NGOV2_SESSION');

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_admin(): void {
    if (empty($_SESSION['logged_in']) || (($_SESSION['user_role'] ?? '') !== 'admin')) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
}

try {
    if (($_GET['action'] ?? 'list') !== 'list') {
        throw new Exception('Unknown action');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    require_admin();

    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
    $type    = trim($_GET['type']   ?? '');
    $status  = trim($_GET['status'] ?? '');
    $search  = trim($_GET['search'] ?? '');
    $from    = trim($_GET['from']   ?? '');
    $to      = trim($_GET['to']     ?? '');

    $where  = [];
    $params = [];

    if ($type !== '') {
        $where[]  = 'user_type = ?';
        $params[] = $type;
    }

    if ($status !== '') {
        $where[]  = 'status = ?';
        $params[] = $status;
    }

    if ($search !== '') {
        $where[]  = '(full_name LIKE ? OR email LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($from !== '') {
        $where[]  = 'DATE(created_at) >= ?';
        $params[] = $from;
    }

    if ($to !== '') {
        $where[]  = 'DATE(created_at) <= ?';
        $params[] = $to;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $db = Database::getInstance();

    // Total count
    $countRow = $db->fetch("SELECT COUNT(*) AS total FROM users {$whereSql}", $params);
    $total    = (int)($countRow['total'] ?? 0);

    $offset = ($page - 1) * $perPage;

    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;

    $rows = $db->fetchAll(
        "SELECT id, full_name, email, phone, user_type, status, last_login, created_at
         FROM users
         {$whereSql}
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?",
        $queryParams
    );

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'pagination' => [
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ],
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    exit;
}
