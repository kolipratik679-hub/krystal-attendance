<?php
/**
 * Production Error Handler - Krystal Attendance System
 *
 * - Hides raw PHP errors from users in production
 * - Logs all PHP errors/warnings/exceptions internally
 * - APIs always get clean JSON — never raw PHP error output
 * - Pages get a safe generic message — never stack traces
 *
 * Call setupErrorHandling() at the top of config.php, before any other logic.
 */

/**
 * Main setup — call once in config.php.
 */
function setupErrorHandling() {
    // Suppress all error output to the browser
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);

    // Capture all PHP errors into our logger
    set_error_handler('_krystalErrorHandler');

    // Capture uncaught exceptions
    set_exception_handler('_krystalExceptionHandler');

    // Capture fatal errors (parse errors, out-of-memory, etc.)
    register_shutdown_function('_krystalShutdownHandler');
}

/**
 * Handles PHP warnings, notices, etc.
 * Returns true so PHP doesn't execute its internal error handler.
 */
function _krystalErrorHandler($errno, $errstr, $errfile, $errline) {
    // Skip errors that are suppressed with @
    if (!(error_reporting() & $errno)) {
        return true;
    }

    $level = _phpErrnoToLevel($errno);
    $message = "PHP_{$level}: {$errstr}";
    $context = ['file' => $errfile, 'line' => $errline, 'errno' => $errno];

    krystalLog($level, $message, $context);

    // For fatal-like errors, send a safe response and stop
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        _sendSafeErrorResponse();
    }

    return true; // Prevent default PHP error handling
}

/**
 * Handles uncaught exceptions.
 */
function _krystalExceptionHandler(Throwable $e) {
    logException('Uncaught exception', $e);
    _sendSafeErrorResponse();
}

/**
 * Handles fatal errors that bypass set_error_handler (E_PARSE, out of memory, etc.)
 */
function _krystalShutdownHandler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // Attempt to log — logger may not be available if it was a parse error in logger itself
        try {
            $context = ['file' => $error['file'], 'line' => $error['line']];
            krystalLog(LOG_LEVEL_CRITICAL, 'FATAL: ' . $error['message'], $context);
        } catch (Throwable $t) {
            // Cannot log — silently ignore
        }
        _sendSafeErrorResponse();
    }
}

/**
 * Send a safe, user-friendly error response.
 * If the request looks like an API call, returns JSON.
 * Otherwise outputs a minimal HTML message.
 */
function _sendSafeErrorResponse() {
    if (headers_sent()) return;

    http_response_code(500);

    $isApi = _isApiRequest();

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'An unexpected error occurred. Please try again.'
        ]);
    } else {
        // Minimal HTML — no stack trace, no file paths
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body>'
           . '<p style="font-family:sans-serif;color:#555;text-align:center;margin-top:4rem;">'
           . '<strong>Something went wrong.</strong><br>Please <a href="dashboard.php">return to the dashboard</a>.'
           . '</p></body></html>';
    }
    exit;
}

/**
 * Detect if the current request is an API call (expects JSON).
 */
function _isApiRequest() {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, '/api/') !== false) return true;

    $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    if (strpos($accept, 'application/json') !== false) return true;

    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (strpos($contentType, 'application/json') !== false) return true;

    return false;
}

/**
 * Map PHP errno to a log level string.
 */
function _phpErrnoToLevel($errno) {
    switch ($errno) {
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            return LOG_LEVEL_CRITICAL;
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            return LOG_LEVEL_WARNING;
        case E_NOTICE:
        case E_USER_NOTICE:
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            return LOG_LEVEL_DEBUG;
        default:
            return LOG_LEVEL_WARNING;
    }
}
