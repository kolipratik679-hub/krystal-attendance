<?php
/**
 * Application Configuration - Krystal Attendance System
 *
 * APP_ENV controls behavior:
 *   'development' - verbose internal logging, errors still hidden from browser
 *   'production'  - minimal logging (WARNING+), errors fully hidden
 *
 * To switch to production: change APP_ENV to 'production'
 * Future: migrate APP_ENV to .env file
 */

define('APP_ENV', 'development'); // 'development' | 'production'

// ---- Load logger first (error handler depends on it) ----
require_once __DIR__ . '/logger.php';

// ---- Load and activate production error handling ----
require_once __DIR__ . '/error_handler.php';
setupErrorHandling();

// ---- Load audit trail helper ----
require_once __DIR__ . '/audit.php';

// ---- Database credentials ----
define('DB_HOST',    'localhost');
define('DB_NAME',    'krystal_attendance');
define('DB_USER',    'root');
define('DB_PASS',    'hello brother'); // Never logged
define('DB_CHARSET', 'utf8mb4');

/**
 * Get a shared PDO connection.
 * On failure: logs the error internally, returns safe JSON error, exits.
 * Never exposes DB credentials or raw PDO messages to the browser.
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            logCritical('DB_CONNECTION_FAILED', [
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
            ]);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database unavailable. Please try again later.']);
            exit;
        }
    }
    return $pdo;
}
