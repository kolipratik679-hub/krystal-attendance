<?php
/**
 * Employee Master CRUD API
 * Phase 4A — Admin-only employee management + autocomplete search
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
$method = requireMethod(['GET', 'POST', 'PUT', 'DELETE']);

// --- GET: Search/List employees ---
if ($method === 'GET') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $post   = isset($_GET['post'])   ? trim($_GET['post'])   : '';
    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']   : 200;
    if ($limit < 1) $limit = 200;
    if ($limit > 500) $limit = 500;

    $sql = 'SELECT id, employee_id, name, post, status, notes, created_at, updated_at FROM employees';
    $params = [];
    $conditions = [];

    if ($search !== '') {
        $conditions[] = '(name LIKE ? OR employee_id LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($status !== '' && in_array($status, ['active', 'inactive'], true)) {
        $conditions[] = 'status = ?';
        $params[] = $status;
    }

    if ($post !== '' && in_array(strtolower($post), VALID_POSTS, true)) {
        $conditions[] = 'post = ?';
        $params[] = strtolower($post);
    }

    if (count($conditions) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY name ASC LIMIT ' . $limit;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll();
        jsonSuccess(['employees' => $employees]);
    } catch (Exception $e) {
        logException('API_GET_EMPLOYEES_FAILED', $e);
        jsonError('Failed to load employees.', 500);
    }
}

// --- POST: Create employee (admin only) ---
if ($method === 'POST') {
    if ($user['role'] !== 'admin') {
        jsonError('Access denied. Only admin can manage employees.', 403);
    }
    requireCsrf();

    $input = getJsonInput();
    if (!$input) {
        jsonError('Invalid request data.');
    }

    $empId  = isset($input['employee_id']) ? trim($input['employee_id']) : '';
    $name   = isset($input['name'])        ? trim($input['name'])        : '';
    $post   = isset($input['post'])        ? trim(strtolower($input['post'])) : '';
    $notes  = isset($input['notes'])       ? trim($input['notes'])       : '';

    // Validate employee_id: numeric only, max 20 chars
    if ($empId === '' || !ctype_digit($empId)) {
        jsonError('Employee ID must be a numeric value.');
    }
    if (strlen($empId) > 20) {
        jsonError('Employee ID is too long (max 20 digits).');
    }

    // Validate name
    if ($name === '' || mb_strlen($name) > 150) {
        jsonError('Employee name is required (max 150 characters).');
    }

    // Validate post
    if (!in_array($post, VALID_POSTS, true)) {
        jsonError('Invalid post. Allowed: ' . implode(', ', VALID_POSTS) . '.');
    }

    // Check duplicate
    $existing = getEmployeeByBadgeId($db, $empId);
    if ($existing) {
        jsonError('Employee ID ' . $empId . ' is already registered.');
    }

    try {
        $stmt = $db->prepare('INSERT INTO employees (employee_id, name, post, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([$empId, $name, $post, $notes ?: null]);
        $newId = $db->lastInsertId();

        auditLog('EMPLOYEE_ADD', [
            'target_type' => 'employee',
            'target_id'   => (int)$newId,
            'employee_id' => $empId,
            'name'        => $name,
            'post'        => $post,
        ]);

        jsonSuccess(['id' => (int)$newId, 'employee_id' => $empId]);
    } catch (Exception $e) {
        logException('API_CREATE_EMPLOYEE_FAILED', $e);
        jsonError('Failed to create employee. Please try again.', 500);
    }
}

// --- PUT: Update employee (admin only) ---
if ($method === 'PUT') {
    if ($user['role'] !== 'admin') {
        jsonError('Access denied. Only admin can manage employees.', 403);
    }
    requireCsrf();

    $input = getJsonInput();
    if (!$input) {
        jsonError('Invalid request data.');
    }

    $id     = isset($input['id'])          ? (int)$input['id']           : 0;
    $empId  = isset($input['employee_id']) ? trim($input['employee_id']) : '';
    $name   = isset($input['name'])        ? trim($input['name'])        : '';
    $post   = isset($input['post'])        ? trim(strtolower($input['post'])) : '';
    $status = isset($input['status'])      ? trim(strtolower($input['status'])) : '';
    $notes  = isset($input['notes'])       ? trim($input['notes'])       : '';

    if ($id < 1) {
        jsonError('Missing or invalid employee record ID.');
    }

    // Verify record exists
    $stmt = $db->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        jsonError('Employee not found.', 404);
    }

    // Validate employee_id
    if ($empId === '' || !ctype_digit($empId)) {
        jsonError('Employee ID must be a numeric value.');
    }
    if (strlen($empId) > 20) {
        jsonError('Employee ID is too long (max 20 digits).');
    }

    // Check duplicate (different record)
    if ($empId !== $existing['employee_id']) {
        $dup = getEmployeeByBadgeId($db, $empId);
        if ($dup) {
            jsonError('Employee ID ' . $empId . ' is already taken by another employee.');
        }
    }

    // Validate name
    if ($name === '' || mb_strlen($name) > 150) {
        jsonError('Employee name is required (max 150 characters).');
    }

    // Validate post
    if (!in_array($post, VALID_POSTS, true)) {
        jsonError('Invalid post. Allowed: ' . implode(', ', VALID_POSTS) . '.');
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'], true)) {
        jsonError('Invalid status. Allowed: active, inactive.');
    }

    try {
        $stmt = $db->prepare('UPDATE employees SET employee_id = ?, name = ?, post = ?, status = ?, notes = ? WHERE id = ?');
        $stmt->execute([$empId, $name, $post, $status, $notes ?: null, $id]);

        auditLog('EMPLOYEE_EDIT', [
            'target_type' => 'employee',
            'target_id'   => $id,
            'employee_id' => $empId,
            'name'        => $name,
            'post'        => $post,
            'status'      => $status,
        ]);

        jsonSuccess();
    } catch (Exception $e) {
        logException('API_UPDATE_EMPLOYEE_FAILED', $e);
        jsonError('Failed to update employee. Please try again.', 500);
    }
}

// --- DELETE: Deactivate employee (admin only, soft delete) ---
if ($method === 'DELETE') {
    if ($user['role'] !== 'admin') {
        jsonError('Access denied. Only admin can manage employees.', 403);
    }
    requireCsrf();

    $input = getJsonInput();
    if (!$input) {
        jsonError('Invalid request data.');
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id < 1) {
        jsonError('Missing or invalid employee ID.');
    }

    $stmt = $db->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        jsonError('Employee not found.', 404);
    }

    try {
        // Soft delete: set status to inactive
        $stmt = $db->prepare("UPDATE employees SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);

        auditLog('EMPLOYEE_DELETE', [
            'target_type' => 'employee',
            'target_id'   => $id,
            'employee_id' => $existing['employee_id'],
            'name'        => $existing['name'],
        ]);

        jsonSuccess();
    } catch (Exception $e) {
        logException('API_DELETE_EMPLOYEE_FAILED', $e);
        jsonError('Failed to deactivate employee. Please try again.', 500);
    }
}
