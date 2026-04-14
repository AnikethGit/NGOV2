<?php
/**
 * includes/security.php
 * Security Utilities — CSRF, sanitization, validation
 *
 * SESSION RULE: every file that touches the session MUST call
 *   session_name('NGOV2_SESSION') + session_set_cookie_params([...])
 * before session_start(). If any file uses a different name, PHP silently
 * creates a second, separate session and cross-file reads all return null.
 */

class Security {

    // ────────────────────────────────────────────────────────────────
    // Internal helper — ensures the shared NGOV2_SESSION is started before
    // any session read/write. Safe to call multiple times (checks status).
    // ────────────────────────────────────────────────────────────────
    private static function ensureSession(): void {
        if (session_status() !== PHP_SESSION_NONE) {
            // Session already active — nothing to do
            return;
        }

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

    // ────────────────────────────────────────────────────────────────
    // Generate CSRF token and store it in the shared session
    // ────────────────────────────────────────────────────────────────
    public static function generateCSRFToken(): string {
        self::ensureSession();
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token']      = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    // ────────────────────────────────────────────────────────────────
    // Validate CSRF token against the shared session
    // ────────────────────────────────────────────────────────────────
    public static function validateCSRFToken($token): bool {
        self::ensureSession();

        if (empty($token)) {
            error_log('CSRF: token not provided by client');
            return false;
        }

        $sessionToken = $_SESSION['csrf_token']      ?? null;
        $tokenTime    = $_SESSION['csrf_token_time'] ?? null;

        if (empty($sessionToken)) {
            error_log('CSRF: no token in session (session name mismatch or session expired)');
            return false;
        }

        // Token expires after 1 hour
        if ($tokenTime && (time() - $tokenTime > 3600)) {
            error_log('CSRF: token expired');
            return false;
        }

        $valid = hash_equals($sessionToken, $token);
        error_log('CSRF: validation ' . ($valid ? 'PASSED' : 'FAILED'));
        return $valid;
    }

    // ────────────────────────────────────────────────────────────────
    // Input sanitization
    // ────────────────────────────────────────────────────────────────
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }

    // ────────────────────────────────────────────────────────────────
    // Validation helpers
    // ────────────────────────────────────────────────────────────────
    public static function validateEmail($email): bool {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validatePhone($phone): bool {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 10 && (bool) preg_match('/^[6-9]/', $phone);
    }

    public static function validatePAN($pan): bool {
        return (bool) preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper($pan));
    }

    // ────────────────────────────────────────────────────────────────
    // Token / password utilities
    // ────────────────────────────────────────────────────────────────
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}
