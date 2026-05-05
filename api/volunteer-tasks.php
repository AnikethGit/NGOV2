<?php
/**
 * Volunteer Tasks API
 * GET api/volunteer-tasks.php → returns tasks + simple stats for logged-in volunteer.
 *
 * NOTE: This is a lightweight stub implementation intended to power
 * the volunteer dashboard UI. You can later replace the in-memory
 * task list with a real database-backed implementation.
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
        'lifetime' => 7200,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_volunteer(): void {
    if (empty($_SESSION['logged_in']) || (($_SESSION['user_role'] ?? '') !== 'volunteer')) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Volunteer access required']);
        exit;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    require_volunteer();

    $userId   = (int)($_SESSION['user_id'] ?? 0);
    $userName = (string)($_SESSION['user_name'] ?? 'Volunteer');

    // Basic derived stats — in future, compute from a real tasks table
    $stats = [
        'hours_volunteered' => 12,
        'tasks_completed'   => 4,
        'events_attended'   => 3,
        'recognition_points'=> 120,
    ];

    // Sample tasks list — replace with DB-backed tasks later
    $tasks = [
        [
            'id'             => 1,
            'title'          => 'Distribute food packets at shelter',
            'meta'           => 'Completed · 12 Apr 2026',
            'status'         => 'done',        // done|upcoming|new
            'assigned_to_me' => true,
        ],
        [
            'id'             => 2,
            'title'          => 'Help organise blood donation camp',
            'meta'           => 'Scheduled · 20 Apr 2026',
            'status'         => 'upcoming',
            'assigned_to_me' => true,
        ],
        [
            'id'             => 3,
            'title'          => 'Website content review',
            'meta'           => 'New assignment · Assign to yourself',
            'status'         => 'new',
            'assigned_to_me' => false,
        ],
    ];

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'user'    => [
            'id'    => $userId,
            'name'  => $userName,
            'email' => $_SESSION['user_email'] ?? '',
        ],
        'stats'   => $stats,
        'tasks'   => $tasks,
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
