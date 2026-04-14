<?php
/**
 * api/auth.php
 * Handles: login, register, logout, check (session), update-profile, forgot-password
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

// ── Session: must match csrf-token.php exactly so both files share one session
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

// ─────────────────────────────────────────────────────────────────────────────
// Helper: resolve redirect URL from role
// ─────────────────────────────────────────────────────────────────────────────
function redirectForRole(string $role): string {
    switch ($role) {
        case 'admin':     return 'admin-dashboard.html';
        case 'volunteer': return 'volunteer-dashboard.html';
        default:          return 'donor-dashboard.html';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET actions (check-session, check, logout)
// ─────────────────────────────────────────────────────────────────────────────
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

    // Any other GET action is not supported
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

// Decode JSON body — auth-enhanced.js sends JSON, not form-encoded.
// FIX: action is read from body only (JS no longer appends ?action= to URL).
$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true);
if (!is_array($body)) $body = [];
$action = trim($body['action'] ?? $_POST['action'] ?? '');

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
        $requested = $body['user_type'] ?? 'donor';
        $role      = in_array($requested, ['donor', 'volunteer']) ? $requested : 'donor';

        if (!$name)                                     throw new Exception('Name is required');
        if (!Security::validateEmail($email))           throw new Exception('Valid email is required');
        if (strlen($password) < 8)                      throw new Exception('Password must be at least 8 characters');
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

    // ── Forgot Password ────────────────────────────────────────────────────
    // NOTE: This handler was completely missing before — JS called it but PHP
    // had no case, causing another 'Unknown action' on the forgot-password form.
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

        // Always return the same success message whether the email exists or not
        // (prevents email enumeration attacks)
        $user = $db->fetch("SELECT id, name FROM users WHERE email = ? AND status = 'active' LIMIT 1", [$email]);

        if ($user) {
            // Generate a secure reset token valid for 1 hour
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);

            // Store token in DB (add password_resets table if not present — see note below)
            try {
                $db->query(
                    "INSERT INTO password_resets (user_id, token, expires_at, created_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()",
                    [$user['id'], hash('sha256', $token), $expires]
                );

                // Send reset email
                $resetLink = rtrim(getenv('APP_URL') ?: 'https://sadgurubharadwaja.org', '/') . '/reset-password.html?token=' . urlencode($token);

                if (function_exists('mail')) {
                    $subject = 'Password Reset - Sadguru Bharadwaja Seva Mandali Bangalore Trust';
                    $html    = "<p>Hello {$user['name']},</p>"
                             . "<p>A password reset was requested for your account. Click the link below to set a new password:</p>"
                             . "<p><a href='{$resetLink}'>{$resetLink}</a></p>"
                             . "<p>This link expires in 1 hour. If you didn't request this, ignore this email.</p>"
                             . "<p>Warm regards,<br>SDSMBT Team</p>";
                    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: noreply@sadgurubharadwaja.org";
                    mail($email, $subject, $html, $headers);
                }
            } catch (Exception $ignored) {
                // Don't expose DB errors — fall through to the generic success response
            }
        }

        // Generic response — never reveal whether the email exists
        echo json_encode([
            'success' => true,
            'message' => 'If an account with that email exists, a password reset link has been sent.'
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
