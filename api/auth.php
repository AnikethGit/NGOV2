<?php
/**
 * api/auth.php
 * Handles: login, register, logout, check-session, update-profile, forgot-password
 *
 * Live DB column reference (u701659873_ngo_management.users):
 *   full_name        VARCHAR(255)  NOT NULL
 *   password_hash    VARCHAR(255)  NOT NULL
 *   user_type        ENUM('admin','volunteer','donor')  DEFAULT 'donor'
 *   email, phone, address, pan_number, status, newsletter_subscribed ...
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

// ── Session: must match csrf-token.php + security.php exactly
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

// ── Redirect helper
function redirectForRole(string $role): string {
    switch ($role) {
        case 'admin':     return 'admin-dashboard.html';
        case 'volunteer': return 'volunteer-dashboard.html';
        default:          return 'donor-dashboard.html'; // donor
    }
}

// ════════════════════════════════════════════════════════════
// GET — check-session / logout
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

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

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ════════════════════════════════════════════════════════════
// POST actions
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true);
if (!is_array($body)) $body = [];
$action = trim($body['action'] ?? $_POST['action'] ?? '');

try {
    $db = Database::getInstance();

    // ── LOGIN ───────────────────────────────────────────────────────
    if ($action === 'login') {
        $email    = Security::sanitize($body['email']    ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Email and password are required']);
            exit;
        }

        // Rate-limit
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lockout  = $_SESSION['login_lockout']  ?? 0;
        if ($attempts >= 5 && time() < $lockout) {
            $wait = ceil(($lockout - time()) / 60);
            echo json_encode(['success' => false, 'message' => "Too many attempts. Try again in {$wait} minutes."]);
            exit;
        }

        $user = $db->fetch(
            "SELECT id, full_name, email, phone, address, pan_number,
                    password_hash, user_type, status
             FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_attempts'] = $attempts + 1;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_lockout'] = time() + 900;
            }
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
            exit;
        }

        if (($user['status'] ?? 'active') !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Account is not active. Please contact support.']);
            exit;
        }

        unset($_SESSION['login_attempts'], $_SESSION['login_lockout']);

        $role = $user['user_type'] ?? 'donor';
        session_regenerate_id(true);
        $_SESSION['logged_in']    = true;
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['user_name']    = $user['full_name'] ?? '';  // live column: full_name
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
                    'name'  => $user['full_name'] ?? '',
                    'email' => $user['email'],
                    'role'  => $role,
                ]
            ]
        ]);
        exit;
    }

    // ── REGISTER ───────────────────────────────────────────────────
    if ($action === 'register') {
        $csrfToken = $body['csrf_token'] ?? '';
        if (!Security::validateCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
            exit;
        }

        $fullName  = Security::sanitize($body['name']      ?? $body['full_name'] ?? '');
        $email     = Security::sanitize($body['email']     ?? '');
        $password  = $body['password']  ?? '';
        $phone     = Security::sanitize($body['phone']     ?? '');
        // user_type ENUM: 'admin','volunteer','donor'  — default donor
        $requested = strtolower($body['user_type'] ?? $body['role'] ?? 'donor');
        $userType  = in_array($requested, ['admin', 'volunteer', 'donor']) ? $requested : 'donor';
        // never allow self-registration as admin
        if ($userType === 'admin') $userType = 'donor';

        if (!$fullName)                                    throw new Exception('Full name is required');
        if (!Security::validateEmail($email))              throw new Exception('Valid email is required');
        if (strlen($password) < 8)                         throw new Exception('Password must be at least 8 characters');
        if ($phone && !Security::validatePhone($phone))    throw new Exception('Invalid phone number');

        $exists = $db->fetch("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        if ($exists) throw new Exception('An account with this email already exists');

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $userId = $db->insert('users', [
            'full_name'             => $fullName,        // live column
            'email'                 => $email,
            'password_hash'         => $hashed,          // live column
            'phone'                 => $phone,
            'user_type'             => $userType,        // live column
            'status'                => 'active',
            'newsletter_subscribed' => (int)($body['newsletter'] ?? 0),
        ]);

        session_regenerate_id(true);
        $_SESSION['logged_in']  = true;
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_name']  = $fullName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role']  = $userType;

        $logger = new Logger();
        $logger->log($userId, 'register', 'New user registered', 'user', $userId);

        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully! Redirecting...',
            'data' => [
                'redirect'  => redirectForRole($userType),
                'user_type' => $userType,
            ]
        ]);
        exit;
    }

    // ── FORGOT PASSWORD ───────────────────────────────────────────────
    if ($action === 'forgot-password') {
        $csrfToken = $body['csrf_token'] ?? '';
        if (!Security::validateCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
            exit;
        }

        $email = Security::sanitize($body['email'] ?? '');
        if (!Security::validateEmail($email)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        $user = $db->fetch(
            "SELECT id, full_name FROM users WHERE email = ? AND status = 'active' LIMIT 1",
            [$email]
        );

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            try {
                // Use live password_reset_token + password_reset_expires columns
                $db->query(
                    "UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?",
                    [hash('sha256', $token), $expires, $user['id']]
                );

                $resetLink = rtrim(getenv('APP_URL') ?: 'https://sadgurubharadwaja.org', '/')
                           . '/reset-password.html?token=' . urlencode($token);

                if (function_exists('mail')) {
                    $name    = $user['full_name'];
                    $subject = 'Password Reset — Sadguru Bharadwaja Seva Mandali Bangalore Trust';
                    $html    = "<p>Hello {$name},</p>"
                             . "<p>Click below to reset your password (expires in 1 hour):</p>"
                             . "<p><a href='{$resetLink}'>{$resetLink}</a></p>"
                             . "<p>If you didn't request this, please ignore this email.</p>"
                             . "<p>Warm regards,<br>SDSMBT Team</p>";
                    mail($email, $subject, $html,
                        "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                        . "From: noreply@sadgurubharadwaja.org"
                    );
                }
            } catch (Exception $ignored) {}
        }

        echo json_encode([
            'success' => true,
            'message' => 'If an account with that email exists, a password reset link has been sent.'
        ]);
        exit;
    }

    // ── UPDATE PROFILE ───────────────────────────────────────────────
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
        if ($name)    $update['full_name']   = $name;    // live column
        if ($phone)   $update['phone']       = $phone;
        if ($address) $update['address']     = $address;
        if ($pan)     $update['pan_number']  = $pan;

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
