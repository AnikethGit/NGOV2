<?php
/**
 * api/auth.php
 * Handles: login, register, logout, check (session), update-profile
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ─────────────────────────────────────────────────────────────────────────────
// Helper: resolve redirect URL from role
// ─────────────────────────────────────────────────────────────────────────────
function redirectForRole(string $role): string {
    switch ($role) {
        case 'admin':     return 'admin-dashboard.html';
        case 'volunteer': return 'volunteer-dashboard.html';
        default:          return 'donor-dashboard.html';   // donor + any unknown
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET actions
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // Session check – supports both 'check' and 'check-session' for compatibility
    if ($action === 'check' || $action === 'check-session') {
        if (!empty($_SESSION['logged_in'])) {
            $role = $_SESSION['user_role'] ?? 'donor';
            echo json_encode([
                'success'   => true,
                'logged_in' => true,
                'data' => [
                    'user_type' => $role,
                    'redirect'  => redirectForRole($role),
                    'user' => [
                        'id'         => $_SESSION['user_id'],
                        'name'       => $_SESSION['user_name']    ?? '',
                        'full_name'  => $_SESSION['user_name']    ?? '',
                        'email'      => $_SESSION['user_email']   ?? '',
                        'role'       => $role,
                        'phone'      => $_SESSION['user_phone']   ?? '',
                        'address'    => $_SESSION['user_address'] ?? '',
                        'pan_number' => $_SESSION['user_pan']     ?? '',
                    ]
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'logged_in' => false]);
        }
        exit;
    }

    // Logout
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST actions
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

// Decode JSON body (auth-enhanced.js sends JSON, not form-encoded)
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? $_POST['action'] ?? '';

try {
    $db = Database::getInstance();

    // ── Login ──────────────────────────────────────────────────────────────
    if ($action === 'login') {
        $email    = Security::sanitize($body['email']    ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Email and password are required']);
            exit;
        }

        // Rate-limit check
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lockout  = $_SESSION['login_lockout']  ?? 0;
        if ($attempts >= 5 && time() < $lockout) {
            $wait = ceil(($lockout - time()) / 60);
            echo json_encode(['success' => false, 'message' => "Too many attempts. Try again in {$wait} minutes."]);
            exit;
        }

        $user = $db->fetch("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);

        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['login_attempts'] = $attempts + 1;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_lockout'] = time() + 900; // 15 min
            }
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
            exit;
        }

        if (($user['status'] ?? 'active') !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Account is not active. Please contact support.']);
            exit;
        }

        // Reset rate-limit
        unset($_SESSION['login_attempts'], $_SESSION['login_lockout']);

        // Store session
        $role = $user['role'] ?? 'donor';
        session_regenerate_id(true);
        $_SESSION['logged_in']    = true;
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['user_name']    = $user['name'] ?? $user['full_name'] ?? '';
        $_SESSION['user_email']   = $user['email'];
        $_SESSION['user_role']    = $role;
        $_SESSION['user_phone']   = $user['phone']      ?? '';
        $_SESSION['user_address'] = $user['address']    ?? '';
        $_SESSION['user_pan']     = $user['pan_number'] ?? '';

        $logger = new Logger();
        $logger->log($user['id'], 'login', 'User logged in', 'user', $user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'redirect'  => redirectForRole($role),
                'user_type' => $role,
                'user' => [
                    'id'    => $user['id'],
                    'name'  => $_SESSION['user_name'],
                    'email' => $user['email'],
                    'role'  => $role,
                ]
            ]
        ]);
        exit;
    }

    // ── Register ───────────────────────────────────────────────────────────
    if ($action === 'register') {
        $csrfToken = $body['csrf_token'] ?? '';
        if (!Security::validateCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
            exit;
        }

        $name      = Security::sanitize($body['name']      ?? '');
        $email     = Security::sanitize($body['email']     ?? '');
        $password  = $body['password']  ?? '';
        $phone     = Security::sanitize($body['phone']     ?? '');
        // Only allow donor or volunteer self-registration; admin cannot self-register
        $requested = $body['user_type'] ?? 'donor';
        $role      = in_array($requested, ['donor', 'volunteer']) ? $requested : 'donor';

        if (!$name)                                    throw new Exception('Name is required');
        if (!Security::validateEmail($email))          throw new Exception('Valid email is required');
        if (strlen($password) < 8)                     throw new Exception('Password must be at least 8 characters');
        if ($phone && !Security::validatePhone($phone)) throw new Exception('Invalid phone number');

        $exists = $db->fetch("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        if ($exists) throw new Exception('An account with this email already exists');

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $userId = $db->insert('users', [
            'name'     => $name,
            'email'    => $email,
            'password' => $hashed,
            'phone'    => $phone,
            'role'     => $role,
            'status'   => 'active',
        ]);

        session_regenerate_id(true);
        $_SESSION['logged_in']  = true;
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role']  = $role;

        $logger = new Logger();
        $logger->log($userId, 'register', 'New user registered', 'user', $userId);

        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully! Redirecting...',
            'data' => [
                'redirect'  => redirectForRole($role),
                'user_type' => $role,
            ]
        ]);
        exit;
    }

    // ── Update profile ─────────────────────────────────────────────────────
    if ($action === 'update-profile') {
        if (empty($_SESSION['logged_in'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit;
        }

        $csrfToken = $body['csrf_token'] ?? $_POST['csrf_token'] ?? '';
        if (!Security::validateCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }

        $userId  = $_SESSION['user_id'];
        $name    = Security::sanitize($body['full_name']   ?? $_POST['full_name']   ?? '');
        $phone   = Security::sanitize($body['phone']       ?? $_POST['phone']       ?? '');
        $address = Security::sanitize($body['address']     ?? $_POST['address']     ?? '');
        $pan     = strtoupper(Security::sanitize($body['pan_number'] ?? $_POST['pan_number'] ?? ''));

        if ($pan && !Security::validatePAN($pan)) {
            echo json_encode(['success' => false, 'message' => 'Invalid PAN number format (e.g. ABCDE1234F)']);
            exit;
        }

        $update = ['updated_at' => date('Y-m-d H:i:s')];
        if ($name)    $update['name']       = $name;
        if ($phone)   $update['phone']      = $phone;
        if ($address) $update['address']    = $address;
        if ($pan)     $update['pan_number'] = $pan;

        $db->update('users', $update, 'id = ?', [$userId]);

        if ($name)    $_SESSION['user_name']    = $name;
        if ($phone)   $_SESSION['user_phone']   = $phone;
        if ($address) $_SESSION['user_address'] = $address;
        if ($pan)     $_SESSION['user_pan']     = $pan;

        $logger = new Logger();
        $logger->log($userId, 'profile_update', 'Profile updated', 'user', $userId);

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
