<?php
/**
 * Deployment Location Master CRUD API
 * Phase 5A — Admin CRUD + autocomplete search for all users
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

// --- GET: Search/List deployment locations ---
if ($method === 'GET') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']   : 200;
    if ($limit < 1) $limit = 200;
    if ($limit > 500) $limit = 500;

    $sql = 'SELECT id, name, status, notes, created_at, updated_at FROM deployment_locations';
    $params = [];
    $conditions = [];

    if ($search !== '') {
        $conditions[] = 'name LIKE ?';
        $params[] = '%' . $search . '%';
    }

    if ($status !== '' && in_array($status, ['active', 'inactive'], true)) {
        $conditions[] = 'status = ?';
        $params[] = $status;
    }

    if (count($conditions) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY name ASC LIMIT ' . $limit;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $locations = $stmt->fetchAll();
        jsonSuccess(['locations' => $locations]);
    } catch (Exception $e) {
        logException('API_GET_DEPLOYMENT_LOCATIONS_FAILED', $e);
        jsonError('Failed to load deployment locations.', 500);
    }
}

// --- POST: Create deployment location (admin only) ---
if ($method === 'POST') {
    if ($user['role'] !== 'admin') {
        jsonError('Access denied. Only admin can manage deployment locations.', 403);
    }
    requireCsrf();

    $input = getJsonInput();
    if (!$input) {
        jsonError('Invalid request data.');
    }

    $name  = isset($input['name'])  ? trim($input['name'])  : '';
    $notes = isset($input['notes']) ? trim($input['notes']) : '';

    // Validate name
    if ($name === '' || mb_strlen($name) > 100) {
        jsonError('Location name is required (max 100 characters).');
    }

    // Check duplicate
    $stmt = $db->prepare('SELECT id FROM deployment_locations WHERE name = ?');
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        jsonError('Deployment location "' . $name . '" already exists.');
    }

    try {
        $stmt = $db->prepare('INSERT INTO deployment_locations (name, notes) VALUES (?, ?)');
        $stmt->execute([$name, $notes ?: null]);
        $newId = $db->lastInsertId();

        auditLog('DEPLOYMENT_LOCATION_ADD', [
            'target_type' => 'deployment_location',
            'target_id'   => (int)$newId,
            'name'        => $name,
        ]);

        jsonSuccess(['id' => (int)$newId, 'name' => $name]);
    } catch (Exception $e) {
        logException('API_CREATE_DEPLOYMENT_LOCATION_FAILED', $e);
        jsonError('Failed to create deployment location. Please try again.', 500);
    }
}

// --- PUT: Update deployment location (admin only) ---
if ($method === 'PUT') {
    if ($user['role'] !== 'admin') {
        jsonError('Access denied. Only admin can manage deployment locations.', 403);
    }
    requireCsrf();

    $input = getJsonInput();
    if (!$input) {
        jsonError('Invalid request data.');
    }

    $id     = isset($input['id'])     ? (int)$input['id']     : 0;
    $name   = isset($input['name'])   ? trim($input['name'])  : '';
    $status = isset($input['status']) ? trim(strtolower($input['status'])) : '';
    $notes  = isset($input['notes'])  ? trim($input['notes']) : '';

    if ($id < 1) {
        jsonError('Missing or invalid deployment location ID.');
    }

    // Verify record exists
    $stmt = $db->prepare('SELECT * FROM deployment_locations WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        jsonError('Deployment location not found.', 404);
    }

    // Validate name
    if ($name === '' || mb_strlen($name) > 100) {
        jsonError('Location name is required (max 100 characters).');
    }

    // Check duplicate (different record)
    if ($name !== $existing['name']) {
        $dup = $db->prepare('SELECT id FROM deployment_locations WHERE name = ?');
        $dup->execute([$name]);
        if ($dup->fetch()) {
            jsonError('Deployment location "' . $name . '" is already taken.');
        }
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'], true)) {
        jsonError('Invalid status. Allowed: active, inactive.');
    }

    try {
        $stmt = $db->prepare('UPDATE deployment_locations SET name = ?, status = ?, notes = ? WHERE id = ?');
        $stmt->execute([$name, $status, $notes ?: null, $id]);

        auditLog('DEPLOYMENT_LOCATION_EDIT', [
            'target_type' => 'deployment_location',
            'target_id'   => $id,
            'name'        => $name,
            'status'      => $status,
        ]);

        jsonSuccess();
    } catch (Exception $e) {
        logException('API_UPDATE_DEPLOYMENT_LOCATION_FAILED', $e);
        jsonError('Failed to update deployment location. Please try again.', 500);
    }
}

// --- DELETE: Deactivate deployment location (admin only, soft delete) ---
if ($method === 'DELETE') {
    if ($user['role'] !== 'admin') {
        jsonError('Access denied. Only admin can manage deployment locations.', 403);
    }
    requireCsrf();

    $input = getJsonInput();
    if (!$input) {
        jsonError('Invalid request data.');
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id < 1) {
        jsonError('Missing or invalid deployment location ID.');
    }

    $stmt = $db->prepare('SELECT * FROM deployment_locations WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        jsonError('Deployment location not found.', 404);
    }

    try {
        $stmt = $db->prepare("UPDATE deployment_locations SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);

        auditLog('DEPLOYMENT_LOCATION_DELETE', [
            'target_type' => 'deployment_location',
            'target_id'   => $id,
            'name'        => $existing['name'],
        ]);

        jsonSuccess();
    } catch (Exception $e) {
        logException('API_DELETE_DEPLOYMENT_LOCATION_FAILED', $e);
        jsonError('Failed to deactivate deployment location. Please try again.', 500);
    }
}
