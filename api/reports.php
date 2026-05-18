<?php
/**
 * Reports API - Krystal Attendance System
 * Phase 4B — Advanced Reporting Module
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

startSecureSession();
if (!isLoggedIn()) {
    jsonError('Unauthorized', 401);
}

$user = getSessionUser();
if ($user['role'] !== 'admin') {
    jsonError('Access denied. Only admin can view reports.', 403);
}

$db = getDB();
$method = requireMethod(['GET']);

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : '';
$empId     = isset($_GET['emp_id'])     ? trim($_GET['emp_id'])     : '';
$shift     = isset($_GET['shift'])      ? trim($_GET['shift'])      : '';
$location  = isset($_GET['location'])   ? trim($_GET['location'])   : '';
$status    = isset($_GET['status'])     ? trim($_GET['status'])     : '';
$depLoc    = isset($_GET['deployment_location']) ? trim($_GET['deployment_location']) : '';
$limit     = isset($_GET['limit'])      ? (int)$_GET['limit']       : 1000; // Limit to prevent massive payloads

if ($limit > 5000) $limit = 5000;

// Base query
$sql = 'SELECT 
            r.attendance_date as date,
            r.shift,
            r.location,
            e.employee_id,
            e.employee_name as name,
            e.post,
            e.status,
            e.deployment_location
        FROM attendance_employees e
        INNER JOIN attendance_records r ON e.attendance_record_id = r.id
        WHERE 1=1';

$params = [];

// Apply Filters
if ($startDate !== '' && validateDate($startDate)) {
    $sql .= ' AND r.attendance_date >= ?';
    $params[] = $startDate;
}

if ($endDate !== '' && validateDate($endDate)) {
    $sql .= ' AND r.attendance_date <= ?';
    $params[] = $endDate;
}

if ($empId !== '') {
    $sql .= ' AND e.employee_id = ?';
    $params[] = $empId;
}

if ($shift !== '' && $shift !== 'all') {
    if (validateShift($shift)) {
        $sql .= ' AND r.shift = ?';
        $params[] = $shift;
    }
}

if ($location !== '' && $location !== 'all') {
    if (validateLocation($location)) {
        $sql .= ' AND r.location = ?';
        $params[] = $location;
    }
}

if ($status !== '' && $status !== 'all') {
    if (validateStatus($status)) {
        $sql .= ' AND e.status = ?';
        $params[] = $status;
    }
}

// Phase 5A: Deployment location filter
if ($depLoc !== '' && $depLoc !== 'all') {
    $sql .= ' AND e.deployment_location = ?';
    $params[] = $depLoc;
}

$sql .= ' ORDER BY r.attendance_date DESC, r.shift ASC, e.employee_name ASC LIMIT ' . $limit;

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonSuccess(['records' => $results]);
} catch (PDOException $e) {
    logException('API_REPORTS_FAILED', $e);
    jsonError('Failed to load reports data.', 500);
}
