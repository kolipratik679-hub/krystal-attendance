<?php
/**
 * Application Configuration - Krystal Attendance System
 *
 * APP_ENV controls behavior:
 *   'development' - verbose internal logging, errors still hidden from browser
 *   'production'  - minimal logging (WARNING+), errors fully hidden
 *   'staging'     - future-safe, treated as production for error/logging behaviour
 *
 * Load order:
 *   1. This file defines defaults (APP_ENV = 'development').
 *   2. config.local.php is loaded AFTER if it exists — allowing per-environment
 *      overrides without touching this file.
 *   3. config.local.php MUST use defined() guards to avoid redefinition errors.
 *
 * config.local.php is OPTIONAL — missing it will NOT cause a fatal error.
 * It must NEVER be committed to version control.
 */

// ---- Default environment (overridden by config.local.php if present) ----
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development'); // 'development' | 'production' | 'staging'
}

// ---- Timezone standardization (must be first, before any date() call) ----
date_default_timezone_set('Asia/Kolkata');

// ---- Optional local override (safe: missing file = no fatal) ----
$_localConfig = __DIR__ . '/config.local.php';
if (file_exists($_localConfig)) {
    require_once $_localConfig;
}
unset($_localConfig);

// ---- Load logger first (error handler depends on it) ----
require_once __DIR__ . '/logger.php';

// ---- Load and activate production error handling ----
require_once __DIR__ . '/error_handler.php';
setupErrorHandling();

// ---- Load audit trail helper ----
require_once __DIR__ . '/audit.php';

// ---- Database credentials (override in config.local.php for production) ----
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    'krystal_attendance');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    'hello brother'); // Never logged
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ---- Login rate-limit settings (Phase 3B-2, override in config.local.php) ----
if (!defined('MAX_LOGIN_ATTEMPTS'))   define('MAX_LOGIN_ATTEMPTS',   5);    // Max failed attempts per IP
if (!defined('LOGIN_WINDOW_SECONDS')) define('LOGIN_WINDOW_SECONDS', 900);  // Time window in seconds (15 min)

/**
 * Get a shared PDO connection.
 * On failure: logs the error internally, returns safe JSON error, exits.
 * Never exposes DB credentials or raw PDO messages to the browser.
 *
 * Phase 3A-2: Adds SET NAMES utf8mb4 and SET time_zone = '+05:30' as PDO
 * init commands to guarantee charset and timezone at the DB session level,
 * independent of server-level my.cnf settings.
 */
function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Enforce charset at the session level (belt-and-suspenders with DSN charset)
            $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
            // Enforce IST timezone at the MySQL session level
            $pdo->exec("SET time_zone = '+05:30'");
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
