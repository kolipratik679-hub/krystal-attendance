<?php
/**
 * Utility Functions - Krystal Attendance System
 */

function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError($msg, $code = 400) {
    // Log 4xx as warnings, 5xx as errors
    if ($code >= 500) {
        logError('API_ERROR [' . $code . ']: ' . $msg);
    } elseif ($code >= 400) {
        logWarning('API_WARN [' . $code . ']: ' . $msg);
    }
    jsonResponse(['success' => false, 'error' => $msg], $code);
}

function jsonSuccess($data = []) {
    jsonResponse(array_merge(['success' => true], $data));
}

// Get records filtered by session shift + location
function getFilteredRecords($db, $user, $dateFilter = '', $shiftFilter = '', $locationFilter = '') {
    $sql = 'SELECT * FROM attendance_records';
    $params = [];
    $conditions = [];

    if ($user['role'] !== 'admin') {
        // CRITICAL: shift + location isolation for non-admin users
        $conditions[] = 'shift = ?';
        $params[] = $user['shift'];
        $conditions[] = 'location = ?';
        $params[] = $user['location'];
    } else {
        if ($shiftFilter) {
            $conditions[] = 'shift = ?';
            $params[] = $shiftFilter;
        }
        if ($locationFilter) {
            $conditions[] = 'location = ?';
            $params[] = $locationFilter;
        }
    }

    if ($dateFilter) {
        $conditions[] = 'attendance_date = ?';
        $params[] = $dateFilter;
    }

    if (count($conditions) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY attendance_date DESC, created_at DESC';

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        logException('DB_QUERY_FAILED: getFilteredRecords', $e);
        throw $e;
    }
}

// Get employees for a record
function getRecordEmployees($db, $recordId) {
    try {
        $stmt = $db->prepare('SELECT * FROM attendance_employees WHERE attendance_record_id = ? ORDER BY id ASC');
        $stmt->execute([$recordId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        logException('DB_QUERY_FAILED: getRecordEmployees', $e, ['record_id' => $recordId]);
        throw $e;
    }
}

/* ---- Validation Helpers ---- */

define('VALID_SHIFTS', ['morning', 'afternoon', 'night']);
define('VALID_LOCATIONS', ['landside', 'asset', 'cargo']);
define('VALID_POSTS', ['incharge', 'supervisor', 'bouncer', 'guard', 'driver']);
define('VALID_STATUSES', ['present', 'absent', 'halfday', 'leave', 'weekoff']);
define('MAX_EMPLOYEE_NAME_LENGTH', 150);
define('MAX_EMPLOYEE_ID_LENGTH', 50);

function validateShift($shift) {
    return in_array($shift, VALID_SHIFTS, true);
}

function validateLocation($location) {
    return in_array($location, VALID_LOCATIONS, true);
}

function getLocationLabel($location) {
    $labels = [
        'landside' => 'Landside',
        'asset'    => 'Asset',
        'cargo'    => 'Cargo',
        'all'      => 'All Locations',
    ];
    return isset($labels[$location]) ? $labels[$location] : ucfirst($location);
}

function validatePost($post) {
    return in_array(strtolower($post), VALID_POSTS, true);
}

function validateStatus($status) {
    return in_array(strtolower($status), VALID_STATUSES, true);
}

function validateDate($date) {
    if (empty($date)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validateEmployeeName($name) {
    $name = trim($name);
    if (empty($name)) return false;
    if (mb_strlen($name) > MAX_EMPLOYEE_NAME_LENGTH) return false;
    return true;
}

function validateEmployeeId($id) {
    $id = trim($id);
    if (empty($id)) return false;
    if (mb_strlen($id) > MAX_EMPLOYEE_ID_LENGTH) return false;
    return true;
}

function sanitizeInput($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Safely decode JSON from request body.
 * Returns null on failure instead of crashing.
 */
function getJsonInput() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return null;
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $data;
}

/**
 * Require specific HTTP method(s), reject others.
 */
function requireMethod($allowed) {
    if (!is_array($allowed)) $allowed = [$allowed];
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, $allowed, true)) {
        jsonError('Method not allowed.', 405);
    }
    return $method;
}

/* ---- Employee Master Helpers (Phase 4A) ---- */

/**
 * Look up an employee by their badge/company employee_id.
 * Returns the employee row or false if not found.
 */
function getEmployeeByBadgeId($db, $badgeId) {
    try {
        $stmt = $db->prepare('SELECT * FROM employees WHERE employee_id = ? LIMIT 1');
        $stmt->execute([trim($badgeId)]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        logException('DB_QUERY_FAILED: getEmployeeByBadgeId', $e, ['badge_id' => $badgeId]);
        return false;
    }
}

/**
 * Validate that an employee_id exists in the master table and is active.
 * Returns the employee row on success, or false with a reason string by reference.
 */
function validateMasterEmployee($db, $badgeId, &$reason = '') {
    $emp = getEmployeeByBadgeId($db, $badgeId);
    if (!$emp) {
        $reason = 'This employee is not added in company records.';
        return false;
    }
    if ($emp['status'] !== 'active') {
        $reason = 'This employee is currently inactive.';
        return false;
    }
    return $emp;
}
