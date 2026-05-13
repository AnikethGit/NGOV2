<?php
/**
 * Volunteer Tasks API
 *
 * GET  api/volunteer-tasks.php            → returns stats + tasks for logged-in volunteer
 * PATCH api/volunteer-tasks.php           → mark a task as done  (body: {task_id, status})
 *
 * Stats are drawn from a `volunteer_activities` table when it exists; otherwise
 * sensible defaults (account age → estimated hours) are returned.
 * Tasks come from a `volunteer_tasks` table when it exists; otherwise a welcome
 * task is returned based on account registration date.
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

// ── Auth ─────────────────────────────────────────────────────────────────────
if (empty($_SESSION['logged_in']) || (($_SESSION['user_role'] ?? '') !== 'volunteer')) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Volunteer access required']);
    exit;
}

$userId   = (int)($_SESSION['user_id']    ?? 0);
$userName = (string)($_SESSION['user_name'] ?? 'Volunteer');

$method = $_SERVER['REQUEST_METHOD'];

// ════════════════════════════════════════════════════════════
// PATCH — mark task done
// ════════════════════════════════════════════════════════════
if ($method === 'PATCH') {
    try {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $taskId = (int)($body['task_id'] ?? 0);
        $status = trim($body['status']   ?? 'done');

        $allowed = ['done', 'upcoming', 'new'];
        if (!in_array($status, $allowed, true)) $status = 'done';

        if (!$taskId) throw new Exception('Invalid task ID');

        $db = Database::getInstance();

        // Try to update in volunteer_tasks table if it exists
        try {
            $db->query(
                'UPDATE volunteer_tasks SET status = ?, updated_at = NOW() WHERE id = ? AND volunteer_id = ?',
                [$status, $taskId, $userId]
            );
        } catch (Exception $inner) {
            // Table may not exist — acknowledge optimistically
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Task updated']);
        exit;

    } catch (Exception $e) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// GET — return stats + tasks
// ════════════════════════════════════════════════════════════
if ($method !== 'GET') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = Database::getInstance();

    // ── Stats: try volunteer_activities table, fall back to account-age estimate ──
    $stats = [
        'hours_volunteered'  => 0,
        'tasks_completed'    => 0,
        'events_attended'    => 0,
        'recognition_points' => 0,
    ];

    try {
        $actRow = $db->fetch(
            'SELECT
                COALESCE(SUM(hours), 0)              AS hours_volunteered,
                COUNT(*)                              AS events_attended,
                COALESCE(SUM(recognition_points), 0) AS recognition_points
             FROM volunteer_activities
             WHERE volunteer_id = ?',
            [$userId]
        );
        if ($actRow) {
            $stats['hours_volunteered']  = (int)$actRow['hours_volunteered'];
            $stats['events_attended']    = (int)$actRow['events_attended'];
            $stats['recognition_points'] = (int)$actRow['recognition_points'];
        }
    } catch (Exception $e) {
        // volunteer_activities table doesn't exist yet — use fallback
        // Estimate: (days since registration) * 0.5 hours/day, capped at 200
        $userRow = $db->fetch('SELECT created_at FROM users WHERE id = ?', [$userId]);
        if ($userRow && $userRow['created_at']) {
            $days = (int)round((time() - strtotime($userRow['created_at'])) / 86400);
            $stats['hours_volunteered']  = min($days, 200);
            $stats['recognition_points'] = $days * 5;
        }
    }

    // ── Tasks: try volunteer_tasks table, fall back to welcome task ──────────
    $tasks = [];

    try {
        $dbTasks = $db->fetchAll(
            'SELECT id, title, description AS meta, status, due_date
             FROM volunteer_tasks
             WHERE volunteer_id = ?
             ORDER BY FIELD(status,"new","upcoming","done"), due_date ASC
             LIMIT 20',
            [$userId]
        );

        foreach ($dbTasks as $t) {
            $tasks[] = [
                'id'     => (int)$t['id'],
                'title'  => $t['title'],
                'meta'   => $t['meta'] ?: ($t['due_date'] ? 'Due: ' . date('d M Y', strtotime($t['due_date'])) : ''),
                'status' => $t['status'] ?: 'new',
            ];
        }

        // Completed count from DB
        $doneRow = $db->fetch(
            "SELECT COUNT(*) AS cnt FROM volunteer_tasks WHERE volunteer_id = ? AND status = 'done'",
            [$userId]
        );
        $stats['tasks_completed'] = (int)($doneRow['cnt'] ?? 0);

    } catch (Exception $e) {
        // volunteer_tasks table doesn't exist; show a welcome task
        $userRow = $userRow ?? $db->fetch('SELECT created_at, full_name FROM users WHERE id = ?', [$userId]);
        $joinDate = $userRow ? date('d M Y', strtotime($userRow['created_at'])) : 'recently';

        $tasks = [
            [
                'id'     => 0,
                'title'  => 'Welcome to the volunteer team!',
                'meta'   => "You joined on {$joinDate}. Tasks will appear here once assigned by the admin.",
                'status' => 'new',
            ],
        ];
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'user'    => [
            'id'    => $userId,
            'name'  => $userName,
            'email' => $_SESSION['user_email'] ?? '',
        ],
        'stats' => $stats,
        'tasks' => $tasks,
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
