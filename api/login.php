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

try {
    if (loginUser($username, $password)) {
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
        jsonError('Invalid username or password. Please try again.');
    }
} catch (Exception $e) {
    logException('API_LOGIN_UNEXPECTED_FAILURE', $e);
    jsonError('An error occurred. Please try again.', 500);
}

