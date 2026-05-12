<?php
/**
 * Authentication & Session Helpers - Krystal Attendance System
 */

function startSecureSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('KRYSTAL_SESSID');
        $isSecure = (defined('APP_ENV') && APP_ENV === 'production');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => $isSecure
        ]);
        session_start();
    }
}

function isLoggedIn()
{
    startSecureSession();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['shift'])) {
        return false;
    }

    $time = time();

    // Absolute timeout: 12 hours (43200 seconds)
    if (isset($_SESSION['login_time']) && ($time - $_SESSION['login_time'] > 43200)) {
        logoutUser();
        return false;
    }

    // Idle timeout: 30 minutes (1800 seconds)
    if (isset($_SESSION['last_activity']) && ($time - $_SESSION['last_activity'] > 1800)) {
        logoutUser();
        return false;
    }

    $_SESSION['last_activity'] = $time;
    return true;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        // For AJAX requests, return JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        header('Location: index.php');
        exit;
    }
}

function getSessionUser()
{
    startSecureSession();
    if (!isLoggedIn())
        return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'shift' => $_SESSION['shift']
    ];
}

function getShiftLabel($shift)
{
    $labels = [
        'morning' => 'Morning Shift',
        'afternoon' => 'Afternoon Shift',
        'night' => 'Night Shift',
        'all' => 'Main Admin'
    ];
    return isset($labels[$shift]) ? $labels[$shift] : 'Unknown';
}

function loginUser($username, $password)
{
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, password, role, shift FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        logException('AUTH_DB_QUERY_FAILED', $e);
        return false;
    }

    if (!$user || !password_verify($password, $user['password'])) {
        // Log failure — never log the actual password
        logAuthFailure('Invalid credentials', $username);
        // Audit: login failure (no session yet — pass username manually)
        auditLog('LOGIN_FAILURE', [
            'attempted_username' => mb_substr($username, 0, 100),
            'reason' => 'invalid_credentials',
        ], ['id' => null, 'username' => mb_substr($username, 0, 100), 'role' => null, 'shift' => null]);
        return false;
    }

    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['shift'] = $user['shift'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    // Generate initial CSRF token on login
    generateCsrfToken();
    logInfo('AUTH_LOGIN_SUCCESS', ['user_id' => $user['id'], 'role' => $user['role']]);
    // Audit: login success (session is now active)
    auditLog('LOGIN_SUCCESS', ['role' => $user['role'], 'shift' => $user['shift']]);
    return true;
}

function logoutUser()
{
    startSecureSession();
    // Capture actor BEFORE clearing session — session will be destroyed below
    $actor = null;
    if (isset($_SESSION['user_id'])) {
        $actor = [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'shift' => $_SESSION['shift'] ?? null,
        ];
    }
    // Audit logout before session is gone
    auditLog('LOGOUT', [], $actor);

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---- CSRF Protection ---- */

function generateCsrfToken()
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfToken()
{
    startSecureSession();
    return generateCsrfToken();
}

function validateCsrfToken($token)
{
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf()
{
    // Read token from header or JSON body
    $token = '';
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token. Please refresh the page and try again.']);
        exit;
    }
}
