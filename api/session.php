<?php
/**
 * Session Check API - Returns current user session info
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

requireMethod(['GET']);

if (!isLoggedIn()) {
    jsonError('Not authenticated', 401);
}

$user = getSessionUser();
jsonSuccess([
    'user' => [
        'username' => $user['username'],
        'role' => $user['role'],
        'shift' => $user['shift']
    ],
    'csrf_token' => getCsrfToken()
]);
