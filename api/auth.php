<?php
/**
 * Authentication API
 * Handles login, register, logout, forgot-password, check-session
 *
 * DB column reference (users table):
 *   full_name, email, phone, password_hash, user_type enum('admin','volunteer','donor')
 *   newsletter_subscribed, status enum('active','inactive','suspended')
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

$action = $_GET['action'] ?? '';

// ── Route ──────────────────────────────────────────────────────────────────
switch ($action) {
    case 'login':           handleLogin();          break;
    case 'register':        handleRegister();       break;
    case 'logout':          handleLogout();         break;
    case 'forgot-password': handleForgotPassword(); break;
    case 'check-session':   handleCheckSession();   break;
    default:
        jsonResponse(false, 'Invalid action', [], 400);
}

// ── Helpers ────────────────────────────────────────────────────────────────
function jsonResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function getPostData() {
    $raw  = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return $json ?: $_POST;
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Map frontend user_type values to DB enum values.
 * Frontend may send 'user' but DB enum only accepts 'donor'.
 */
function normaliseUserType($type) {
    if ($type === 'user') return 'donor';
    return $type;
}

/** Link any guest donations made with this email to the user account. */
function linkEmailDonations($db, $userId, $email) {
    try {
        $db->query(
            "UPDATE donations SET user_id = ? WHERE donor_email = ? AND user_id IS NULL",
            [$userId, $email]
        );
    } catch (Exception $e) {
        error_log('linkEmailDonations: ' . $e->getMessage());
    }
}

// ── Login ──────────────────────────────────────────────────────────────────
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Method not allowed', [], 405);
    }

    $data = getPostData();

    if (!verifyCsrf($data['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid security token. Please refresh and try again.', [], 403);
    }

    $email     = trim(strtolower($data['email'] ?? ''));
    $password  = $data['password'] ?? '';
    $user_type = normaliseUserType($data['user_type'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter a valid email address.');
    }
    if (empty($password)) {
        jsonResponse(false, 'Password is required.');
    }
    // Valid DB enum values
    if (!in_array($user_type, ['donor', 'volunteer', 'admin'])) {
        jsonResponse(false, 'Please select a valid account type.');
    }

    try {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate-limit check
        $locked = $db->fetch(
            "SELECT COUNT(*) as cnt FROM login_attempts
              WHERE ip_address = :ip AND email = :email
                AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                AND success = 0",
            [':ip' => $ip, ':email' => $email]
        );
        if (($locked['cnt'] ?? 0) >= 5) {
            jsonResponse(false, 'Too many failed attempts. Please wait 15 minutes and try again.', [], 429);
        }

        // Fetch user — uses correct column full_name via SELECT *
        $user = $db->fetch(
            "SELECT * FROM users WHERE email = :email AND user_type = :type AND status = 'active' LIMIT 1",
            [':email' => $email, ':type' => $user_type]
        );

        $logAttempt = function ($success) use ($db, $ip, $email) {
            try {
                $db->insert('login_attempts', [
                    'ip_address' => $ip,
                    'email'      => $email,
                    'success'    => $success ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) { /* non-fatal */ }
        };

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $logAttempt(false);
            jsonResponse(false, 'Invalid email or password. Please check your credentials.');
        }

        $logAttempt(true);
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['full_name'];   // correct column
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_type']  = $user['user_type'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_time'] = time();

        $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $user['id']]);
        linkEmailDonations($db, $user['id'], $email);

        $redirect = match ($user['user_type']) {
            'admin'     => 'admin/dashboard.html',
            'volunteer' => 'volunteer-dashboard.html',
            default     => 'dashboard.html'
        };

        jsonResponse(true, 'Login successful! Redirecting...', [
            'redirect'   => $redirect,
            'user_name'  => $user['full_name'],          // correct column
            'user_type'  => $user['user_type'],
            'user_email' => $user['email']
        ]);

    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        jsonResponse(false, 'Login failed. Please try again later.', [], 500);
    }
}

// ── Register ───────────────────────────────────────────────────────────────
function handleRegister() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Method not allowed', [], 405);
    }

    $data = getPostData();

    if (!verifyCsrf($data['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid security token. Please refresh and try again.', [], 403);
    }

    $name       = trim($data['name'] ?? '');
    $email      = trim(strtolower($data['email'] ?? ''));
    $phone      = preg_replace('/\D/', '', $data['phone'] ?? '');
    $password   = $data['password'] ?? '';
    $confirm    = $data['confirm_password'] ?? '';
    $user_type  = normaliseUserType($data['user_type'] ?? '');  // 'user' → 'donor'
    $newsletter = !empty($data['newsletter']) ? 1 : 0;

    $errors = [];
    if (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!empty($phone) && strlen($phone) !== 10) {
        $errors[] = 'Phone number must be 10 digits.';
    }
    // Valid DB enum values for self-registration
    if (!in_array($user_type, ['donor', 'volunteer'])) {
        $errors[] = 'Please select a valid account type.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!empty($errors)) {
        jsonResponse(false, implode(' ', $errors));
    }

    try {
        $db = Database::getInstance();

        $existing = $db->fetch(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            [':email' => $email]
        );
        if ($existing) {
            jsonResponse(false, 'An account with this email already exists. Please login or use a different email.');
        }

        $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        $userId = $db->insert('users', [
            'full_name'             => $safeName,       // correct column
            'email'                 => $email,
            'phone'                 => $phone ?: null,
            'password_hash'         => $hash,
            'user_type'             => $user_type,      // 'donor' or 'volunteer'
            'newsletter_subscribed' => $newsletter,     // correct column
            'status'                => 'active',
            'created_at'            => date('Y-m-d H:i:s')
        ]);

        session_regenerate_id(true);
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_name']  = $safeName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_type']  = $user_type;
        $_SESSION['logged_in']  = true;
        $_SESSION['login_time'] = time();

        linkEmailDonations($db, $userId, $email);

        $redirect = ($user_type === 'volunteer') ? 'volunteer-dashboard.html' : 'dashboard.html';

        jsonResponse(true, 'Account created successfully! Welcome aboard.', [
            'redirect'   => $redirect,
            'user_name'  => $safeName,
            'user_type'  => $user_type,
            'user_email' => $email
        ]);

    } catch (Exception $e) {
        error_log('Register error: ' . $e->getMessage());
        jsonResponse(false, 'Registration failed. Please try again later.', [], 500);
    }
}

// ── Logout ─────────────────────────────────────────────────────────────────
function handleLogout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    jsonResponse(true, 'Logged out successfully.', ['redirect' => 'login.html']);
}

// ── Forgot Password ────────────────────────────────────────────────────────
function handleForgotPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Method not allowed', [], 405);
    }

    $data  = getPostData();
    $email = trim(strtolower($data['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter a valid email address.');
    }

    try {
        $db   = Database::getInstance();
        // correct column: full_name
        $user = $db->fetch(
            'SELECT id, full_name FROM users WHERE email = :email AND status = "active" LIMIT 1',
            [':email' => $email]
        );

        if ($user) {
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);
            try {
                $db->query('DELETE FROM password_resets WHERE email = :email', [':email' => $email]);
                $db->insert('password_resets', [
                    'email'      => $email,
                    'token'      => hash('sha256', $token),
                    'expires_at' => $expiresAt,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                error_log('password_resets table missing: ' . $e->getMessage());
            }
        }

        jsonResponse(true, 'If an account exists with that email, you will receive reset instructions shortly.');

    } catch (Exception $e) {
        error_log('Forgot password error: ' . $e->getMessage());
        jsonResponse(true, 'If an account exists with that email, you will receive reset instructions shortly.');
    }
}

// ── Check Session ──────────────────────────────────────────────────────────
function handleCheckSession() {
    if (!empty($_SESSION['logged_in'])) {
        jsonResponse(true, 'Session active', [
            'user_name'  => $_SESSION['user_name']  ?? '',
            'user_email' => $_SESSION['user_email'] ?? '',
            'user_type'  => $_SESSION['user_type']  ?? ''
        ]);
    } else {
        jsonResponse(false, 'Not authenticated', [], 401);
    }
}
