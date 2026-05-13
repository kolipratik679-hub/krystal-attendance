<?php
/**
 * Login API Endpoint
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$method = requireMethod(['POST']);

// Validate CSRF token
requireCsrf();

$input = getJsonInput();
if (!$input) {
    logApiValidation('api/login.php', 'Invalid or empty JSON payload');
    jsonError('Invalid request data.');
}

$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if (!$username || !$password) {
    jsonError('Please enter both username and password.');
}

// Basic length limits
if (mb_strlen($username) > 100 || mb_strlen($password) > 255) {
    logAuthFailure('Oversized credentials submitted', $username);
    jsonError('Invalid credentials.');
}

// ---- Phase 3B-2: Rate-limit check (fail-open) ----
// If the rate-limit system fails internally, login proceeds normally.
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$rateCheck = checkLoginRateLimit($clientIp);
if (!$rateCheck['allowed']) {
    $waitMinutes = max(1, (int)ceil($rateCheck['remaining'] / 60));
    // Audit: rate-limited attempt (before blocking)
    auditLog('LOGIN_RATE_LIMITED', [
        'attempted_username' => mb_substr($username, 0, 100),
        'ip_address'         => $clientIp,
        'wait_minutes'       => $waitMinutes,
    ], ['id' => null, 'username' => mb_substr($username, 0, 100), 'role' => null, 'shift' => null]);
    jsonError('Too many login attempts. Please try again in ' . $waitMinutes . ' minute(s).', 429);
}

try {
    if (loginUser($username, $password)) {
        // ---- Phase 3B-2: Clear failed attempts on successful login ----
        clearLoginAttempts($clientIp);
        $user = getSessionUser();
        jsonSuccess([
            'user' => [
                'username' => $user['username'],
                'role'     => $user['role'],
                'shift'    => $user['shift']
            ],
            'csrf_token' => getCsrfToken()
        ]);
    } else {
        // loginUser already logged the failure
        // ---- Phase 3B-2: Record failed attempt for rate limiting ----
        recordFailedLogin($clientIp, $username);
        jsonError('Invalid username or password. Please try again.');
    }
} catch (Exception $e) {
    logException('API_LOGIN_UNEXPECTED_FAILURE', $e);
    jsonError('An error occurred. Please try again.', 500);
}
