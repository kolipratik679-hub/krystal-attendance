<?php
/**
 * Audit Trail Helper - Krystal Attendance System
 *
 * Writes structured activity logs to the `audit_log` database table.
 *
 * SAFETY CONTRACT:
 * - NEVER throws or crashes the calling code.
 * - On any failure, silently falls back (optionally writes to file logger).
 * - Never stores: passwords, CSRF tokens, session IDs, raw credentials.
 */

/**
 * Write one entry to the audit log.
 *
 * @param string $actionType   e.g. 'LOGIN_SUCCESS', 'ATTENDANCE_ADD'
 * @param array  $context      Safe extra data (no secrets). Keys: target_type, target_id, details.
 * @param array|null $actorOverride  Provide user info manually (needed for logout, where session is destroyed first).
 */
function auditLog($actionType, $context = [], $actorOverride = null) {
    try {
        // --- Resolve actor (who performed the action) ---
        if ($actorOverride !== null) {
            $userId   = isset($actorOverride['id'])       ? (int)$actorOverride['id']              : null;
            $username = isset($actorOverride['username'])  ? mb_substr($actorOverride['username'], 0, 100) : null;
            $role     = isset($actorOverride['role'])      ? mb_substr($actorOverride['role'],     0, 20)  : null;
            $shift    = isset($actorOverride['shift'])     ? mb_substr($actorOverride['shift'],    0, 20)  : null;
        } else {
            // Try to read from active session
            $userId   = null;
            $username = null;
            $role     = null;
            $shift    = null;
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                $userId   = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id']                          : null;
                $username = isset($_SESSION['username']) ? mb_substr($_SESSION['username'], 0, 100)            : null;
                $role     = isset($_SESSION['role'])     ? mb_substr($_SESSION['role'],     0, 20)             : null;
                $shift    = isset($_SESSION['shift'])    ? mb_substr($_SESSION['shift'],    0, 20)             : null;
            }
        }

        // --- Resolve target info ---
        $targetType = isset($context['target_type']) ? mb_substr((string)$context['target_type'], 0, 50) : null;
        $targetId   = isset($context['target_id'])   ? (int)$context['target_id']                         : null;

        // --- Build safe details JSON (strip known sensitive keys) ---
        $sensitiveKeys = ['password', 'pass', 'secret', 'token', 'csrf', 'session', 'cookie', 'key', 'credential'];
        $details = [];
        foreach ($context as $k => $v) {
            if ($k === 'target_type' || $k === 'target_id') continue; // already stored separately
            $lk = strtolower((string)$k);
            $isSensitive = false;
            foreach ($sensitiveKeys as $s) {
                if (strpos($lk, $s) !== false) { $isSensitive = true; break; }
            }
            if (!$isSensitive) {
                $details[$k] = is_string($v) ? mb_substr($v, 0, 500) : $v;
            }
            // Sensitive keys are simply omitted — not redacted, not stored
        }
        $detailsJson = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

        // --- Network info (safe, no leaking) ---
        $ip        = _auditGetIp();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

        // --- Write to DB ---
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO audit_log
             (user_id, username, role, shift, action_type, target_type, target_id, details_json, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $username,
            $role,
            $shift,
            mb_substr(strtoupper($actionType), 0, 80),
            $targetType,
            $targetId ?: null,
            $detailsJson,
            $ip,
            $userAgent,
        ]);

    } catch (Throwable $e) {
        // NEVER let audit logging crash the application.
        // Silently degrade — write to file log as a fallback if possible.
        if (function_exists('logWarning')) {
            logWarning('AUDIT_LOG_WRITE_FAILED: ' . $e->getMessage(), ['action' => $actionType]);
        }
    }
}

/**
 * Get the real client IP address safely.
 * Respects common proxy headers but sanitizes output.
 */
function _auditGetIp() {
    // Check proxy headers first (common in shared hosting / nginx proxies)
    $candidates = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            // X-Forwarded-For can be a comma-separated list; take first
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return mb_substr($ip, 0, 45); // Max IPv6 length
            }
        }
    }
    return null;
}
