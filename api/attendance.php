<?php
/**
 * Attendance CRUD API
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
$db = getDB();
$method = requireMethod(['GET', 'POST', 'DELETE']);

// --- GET: List records ---
if ($method === 'GET') {
    $dateFilter  = isset($_GET['date'])  ? trim($_GET['date'])  : '';
    $shiftFilter = isset($_GET['shift']) ? trim($_GET['shift']) : '';

    // Validate date filter if provided
    if ($dateFilter && !validateDate($dateFilter)) {
        jsonError('Invalid date format.');
    }

    // Validate shift filter if provided
    if ($shiftFilter && !validateShift($shiftFilter)) {
        jsonError('Invalid shift value.');
    }

    try {
        $records = getFilteredRecords($db, $user, $dateFilter, $shiftFilter);
        $result  = [];
        foreach ($records as $rec) {
            $employees = getRecordEmployees($db, $rec['id']);
            $emps = [];
            foreach ($employees as $emp) {
                $emps[] = [
                    'name'   => $emp['employee_name'],
                    'id'     => $emp['employee_id'],
                    'post'   => $emp['post'],
                    'status' => $emp['status']
                ];
            }
            $result[] = [
                'id'        => (int)$rec['id'],
                'date'      => $rec['attendance_date'],
                'shift'     => $rec['shift'],
                'employees' => $emps
            ];
        }
        jsonSuccess(['records' => $result]);
    } catch (Exception $e) {
        logException('API_GET_RECORDS_FAILED', $e);
        jsonError('Failed to load attendance records.', 500);
    }
}

// --- POST: Save new or update existing ---
if ($method === 'POST') {
    // CSRF check for mutations
    requireCsrf();

    $input = getJsonInput();
    if (!$input) {
        jsonError('Invalid request data.');
    }

    $date = isset($input['date']) ? trim($input['date']) : '';
    $shift = isset($input['shift']) ? trim($input['shift']) : '';
    $employees = isset($input['employees']) && is_array($input['employees']) ? $input['employees'] : [];
    $editId = isset($input['editId']) ? (int)$input['editId'] : 0;

    // Validate date
    if (!validateDate($date)) {
        jsonError('Invalid or missing attendance date.');
    }

    // Validate shift
    if (!validateShift($shift)) {
        jsonError('Invalid shift. Allowed: morning, afternoon, night.');
    }

    // Validate employees array
    if (count($employees) === 0) {
        jsonError('At least one employee is required.');
    }

    // Validate shift access
    if ($user['role'] !== 'admin' && $shift !== $user['shift']) {
        jsonError('You cannot save records for another shift.', 403);
    }

    // Validate each employee and check for duplicates
    $seenIds = [];
    $validatedEmployees = [];
    foreach ($employees as $idx => $emp) {
        $name = isset($emp['name']) ? trim($emp['name']) : '';
        $eid = isset($emp['id']) ? trim($emp['id']) : '';
        $post = isset($emp['post']) ? trim(strtolower($emp['post'])) : '';
        $status = isset($emp['status']) ? trim(strtolower($emp['status'])) : 'present';

        if (!validateEmployeeName($name)) {
            jsonError("Employee #" . ($idx + 1) . ": Name is required (max " . MAX_EMPLOYEE_NAME_LENGTH . " chars).");
        }

        if (!validateEmployeeId($eid)) {
            jsonError("Employee #" . ($idx + 1) . ": ID is required (max " . MAX_EMPLOYEE_ID_LENGTH . " chars).");
        }

        if (!validatePost($post)) {
            jsonError("Employee #" . ($idx + 1) . ": Invalid post. Allowed: " . implode(', ', VALID_POSTS) . ".");
        }

        if (!validateStatus($status)) {
            jsonError("Employee #" . ($idx + 1) . ": Invalid status. Allowed: " . implode(', ', VALID_STATUSES) . ".");
        }

        // Duplicate Staff ID Check
        if (isset($seenIds[$eid])) {
            jsonError("Duplicate Staff ID ($eid) detected in the request.");
        }
        $seenIds[$eid] = true;

        $validatedEmployees[] = [
            'name' => $name,
            'id' => $eid,
            'post' => $post,
            'status' => $status
        ];
    }

    try {
        $db->beginTransaction();

        if ($editId > 0) {
            // Verify record exists and user has access
            $stmt = $db->prepare('SELECT * FROM attendance_records WHERE id = ?');
            $stmt->execute([$editId]);
            $existingRec = $stmt->fetch();
            if (!$existingRec) {
                $db->rollBack();
                jsonError('Record not found.', 404);
            }
            if ($user['role'] !== 'admin' && $existingRec['shift'] !== $user['shift']) {
                $db->rollBack();
                jsonError('Access denied.', 403);
            }

            // Update: delete old employees, update record
            $stmt = $db->prepare('UPDATE attendance_records SET attendance_date = ?, shift = ? WHERE id = ?');
            $stmt->execute([$date, $shift, $editId]);
            $stmt = $db->prepare('DELETE FROM attendance_employees WHERE attendance_record_id = ?');
            $stmt->execute([$editId]);
            $recordId = $editId;
        } else {
            // Check duplicate date+shift
            $stmt = $db->prepare('SELECT id FROM attendance_records WHERE attendance_date = ? AND shift = ?');
            $stmt->execute([$date, $shift]);
            $existing = $stmt->fetch();
            if ($existing) {
                // Overwrite existing
                $recordId = $existing['id'];
                $stmt = $db->prepare('DELETE FROM attendance_employees WHERE attendance_record_id = ?');
                $stmt->execute([$recordId]);
                $stmt = $db->prepare('UPDATE attendance_records SET created_by = ?, created_at = NOW() WHERE id = ?');
                $stmt->execute([$user['id'], $recordId]);
            } else {
                $stmt = $db->prepare('INSERT INTO attendance_records (shift, attendance_date, created_by) VALUES (?, ?, ?)');
                $stmt->execute([$shift, $date, $user['id']]);
                $recordId = $db->lastInsertId();
            }
        }

        // Insert validated employees
        $ins = $db->prepare('INSERT INTO attendance_employees (attendance_record_id, employee_name, employee_id, post, status) VALUES (?, ?, ?, ?, ?)');
        foreach ($validatedEmployees as $emp) {
            $ins->execute([$recordId, $emp['name'], $emp['id'], $emp['post'], $emp['status']]);
        }

        $db->commit();

        // Audit: only log AFTER successful commit
        $auditAction = ($editId > 0) ? 'ATTENDANCE_EDIT' : 'ATTENDANCE_ADD';
        auditLog($auditAction, [
            'target_type' => 'attendance_record',
            'target_id'   => (int)$recordId,
            'date'        => $date,
            'shift'       => $shift,
            'employee_count' => count($validatedEmployees),
        ]);

        jsonSuccess(['recordId' => (int)$recordId]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        logException('API_SAVE_ATTENDANCE_FAILED', $e, [
            'date'    => $date,
            'shift'   => $shift,
            'editId'  => $editId,
            'empCount'=> count($validatedEmployees)
        ]);
        jsonError('Failed to save attendance record. Please try again.', 500);
    }
}

// --- DELETE ---
if ($method === 'DELETE') {
    // CSRF check for mutations
    requireCsrf();

    $input = getJsonInput();
    if (!$input) {
        jsonError('Invalid request data.');
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if (!$id || $id < 1) {
        jsonError('Missing or invalid record ID.');
    }

    // Verify ownership/access
    $stmt = $db->prepare('SELECT * FROM attendance_records WHERE id = ?');
    $stmt->execute([$id]);
    $rec = $stmt->fetch();

    if (!$rec) {
        jsonError('Record not found.', 404);
    }

    if ($user['role'] !== 'admin' && $rec['shift'] !== $user['shift']) {
        jsonError('Access denied.', 403);
    }

    // CASCADE will handle employees
    try {
        $stmt = $db->prepare('DELETE FROM attendance_records WHERE id = ?');
        $stmt->execute([$id]);
        logInfo('RECORD_DELETED', ['record_id' => $id, 'deleted_by_user' => $user['id']]);
        // Audit: log after successful delete
        auditLog('ATTENDANCE_DELETE', [
            'target_type'      => 'attendance_record',
            'target_id'        => (int)$id,
            'record_date'      => $rec['attendance_date'],
            'record_shift'     => $rec['shift'],
        ]);
    } catch (Exception $e) {
        logException('API_DELETE_ATTENDANCE_FAILED', $e, ['record_id' => $id]);
        jsonError('Failed to delete record. Please try again.', 500);
    }

    jsonSuccess();
}
