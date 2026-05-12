<?php
/**
 * Centralized Logger - Krystal Attendance System
 *
 * - Writes to daily log files in /logs/
 * - Never exposes sensitive credentials
 * - Never crashes the application
 * - Logs: errors, warnings, auth failures, API failures, DB exceptions
 */

define('LOG_DIR', __DIR__ . '/../logs/');
define('LOG_MAX_BYTES', 5 * 1024 * 1024); // 5MB per file before rotation

/**
 * Log levels
 */
define('LOG_LEVEL_DEBUG',   'DEBUG');
define('LOG_LEVEL_INFO',    'INFO');
define('LOG_LEVEL_WARNING', 'WARNING');
define('LOG_LEVEL_ERROR',   'ERROR');
define('LOG_LEVEL_CRITICAL','CRITICAL');

/**
 * Core write function. Safe — never throws.
 */
function krystalLog($level, $message, $context = []) {
    // In production, skip DEBUG level logs
    if ($level === LOG_LEVEL_DEBUG && defined('APP_ENV') && APP_ENV === 'production') {
        return;
    }

    try {
        $logDir = LOG_DIR;

        // Ensure log directory exists
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }

        $date     = date('Y-m-d');
        $logFile  = $logDir . 'krystal_' . $date . '.log';

        // Rotate if file is too large
        if (file_exists($logFile) && filesize($logFile) > LOG_MAX_BYTES) {
            $archiveName = $logDir . 'krystal_' . $date . '_' . time() . '.log.bak';
            @rename($logFile, $archiveName);
        }

        // Build safe context — strip sensitive keys
        $safeContext = _sanitizeLogContext($context);

        // Build log entry
        $timestamp  = date('Y-m-d H:i:s');
        $url        = _safeServerVar('REQUEST_URI');
        $ip         = _safeServerVar('REMOTE_ADDR');
        $userId     = _getLoggedInUserId();
        $contextStr = !empty($safeContext) ? ' | context=' . json_encode($safeContext) : '';

        $line = sprintf(
            "[%s] [%s] %s | url=%s | ip=%s | user_id=%s%s\n",
            $timestamp,
            $level,
            $message,
            $url,
            $ip,
            $userId,
            $contextStr
        );

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

    } catch (Throwable $e) {
        // Logging itself must never crash the app — silently ignore
    }
}

/**
 * Convenience wrappers
 */
function logDebug($message, $context = [])    { krystalLog(LOG_LEVEL_DEBUG,    $message, $context); }
function logInfo($message, $context = [])     { krystalLog(LOG_LEVEL_INFO,     $message, $context); }
function logWarning($message, $context = [])  { krystalLog(LOG_LEVEL_WARNING,  $message, $context); }
function logError($message, $context = [])    { krystalLog(LOG_LEVEL_ERROR,    $message, $context); }
function logCritical($message, $context = []) { krystalLog(LOG_LEVEL_CRITICAL, $message, $context); }

/**
 * Log a caught exception safely (never exposes full trace to output).
 */
function logException($message, Throwable $e, $context = []) {
    $context['exception_class']   = get_class($e);
    $context['exception_message'] = $e->getMessage();
    $context['exception_file']    = $e->getFile();
    $context['exception_line']    = $e->getLine();
    // Do NOT log full stack trace in production — only file+line
    krystalLog(LOG_LEVEL_ERROR, $message, $context);
}

/**
 * Log an auth failure event.
 */
function logAuthFailure($reason, $username = '') {
    // Never log the actual password — only username (truncated)
    $safeUsername = $username ? mb_substr($username, 0, 50) : 'unknown';
    logWarning('AUTH_FAILURE: ' . $reason, ['username' => $safeUsername]);
}

/**
 * Log an API validation failure.
 */
function logApiValidation($endpoint, $reason) {
    logWarning('API_VALIDATION: ' . $reason, ['endpoint' => $endpoint]);
}

/**
 * Remove sensitive keys from context before logging.
 */
function _sanitizeLogContext($context) {
    if (!is_array($context)) return [];
    $sensitiveKeys = ['password', 'pass', 'secret', 'token', 'csrf', 'session', 'cookie', 'db_pass', 'key'];
    $clean = [];
    foreach ($context as $k => $v) {
        $lk = strtolower((string)$k);
        $isSensitive = false;
        foreach ($sensitiveKeys as $s) {
            if (strpos($lk, $s) !== false) { $isSensitive = true; break; }
        }
        if (!$isSensitive) {
            // Truncate very long values
            $clean[$k] = is_string($v) ? mb_substr($v, 0, 200) : $v;
        } else {
            $clean[$k] = '[REDACTED]';
        }
    }
    return $clean;
}

/**
 * Safely get a $_SERVER variable.
 */
function _safeServerVar($key) {
    return isset($_SERVER[$key]) ? (string)$_SERVER[$key] : '-';
}

/**
 * Get current logged-in user ID from session (if session is active).
 */
function _getLoggedInUserId() {
    try {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            return (string)$_SESSION['user_id'];
        }
    } catch (Throwable $e) {}
    return '-';
}
