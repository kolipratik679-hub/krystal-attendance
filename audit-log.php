<?php
/**
 * Audit Log Viewer - Krystal Attendance System
 * ADMIN ONLY — Main admin access required (role=admin, shift=all)
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = getSessionUser();

// Strict access: only main admin (role=admin) can view audit logs
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$shiftLabel = getShiftLabel($user['shift']);

// --- Filter inputs (safe, validated) ---
$filterDate   = isset($_GET['date'])   ? trim($_GET['date'])   : '';
$filterAction = isset($_GET['action']) ? trim($_GET['action']) : '';
$filterUser   = isset($_GET['user'])   ? trim($_GET['user'])   : '';
$filterShift  = isset($_GET['shift'])  ? trim($_GET['shift'])  : '';
$page         = max(1, isset($_GET['p']) ? (int)$_GET['p'] : 1);
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

// Validate date filter
if ($filterDate && !validateDate($filterDate)) { $filterDate = ''; }
// Validate shift filter
if ($filterShift && !in_array($filterShift, ['morning', 'afternoon', 'night', 'all', 'landside', 'asset', 'cargo'], true)) { $filterShift = ''; }

// --- Query audit logs ---
$db = getDB();
$conditions = [];
$params     = [];

if ($filterDate) {
    $conditions[] = 'DATE(a.created_at) = ?';
    $params[]     = $filterDate;
}
if ($filterAction) {
    $conditions[] = 'a.action_type = ?';
    $params[]     = mb_substr(strtoupper($filterAction), 0, 80);
}
if ($filterUser) {
    $conditions[] = 'a.username LIKE ?';
    $params[]     = '%' . mb_substr($filterUser, 0, 50) . '%';
}
if ($filterShift) {
    $conditions[] = 'a.shift = ?';
    $params[]     = $filterShift;
}

$where = count($conditions) > 0 ? ' WHERE ' . implode(' AND ', $conditions) : '';

try {
    // Total count for pagination
    $countSql  = 'SELECT COUNT(*) FROM audit_log a' . $where;
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    // Fetch page of records
    $sql  = 'SELECT a.* FROM audit_log a' . $where . ' ORDER BY a.created_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    logException('AUDIT_VIEWER_QUERY_FAILED', $e);
    $logs       = [];
    $totalRows  = 0;
    $totalPages = 1;
}

// --- Distinct action types for filter dropdown ---
$actionTypes = [];
try {
    $actStmt = $db->query('SELECT DISTINCT action_type FROM audit_log ORDER BY action_type ASC');
    $actionTypes = $actStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { /* silently ignore */ }

// --- Distinct usernames for filter dropdown ---
$usernames = [];
try {
    $unStmt = $db->query('SELECT DISTINCT username FROM audit_log WHERE username IS NOT NULL ORDER BY username ASC LIMIT 100');
    $usernames = $unStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { /* silently ignore */ }

// Helper: friendly action label + badge class
function actionLabel($type) {
    $map = [
        'LOGIN_SUCCESS'     => ['Login Success',     'badge-success'],
        'LOGIN_FAILURE'     => ['Login Failure',     'badge-danger'],
        'LOGOUT'            => ['Logout',            'badge-secondary'],
        'ATTENDANCE_ADD'    => ['Add Attendance',    'badge-info'],
        'ATTENDANCE_EDIT'   => ['Edit Attendance',   'badge-warning'],
        'ATTENDANCE_DELETE' => ['Delete Attendance', 'badge-danger'],
        'SHIFT_SELECTED'    => ['Shift Selected',    'badge-primary'],
        'LOGIN_RATE_LIMITED' => ['Rate Limited',      'badge-warning'],
    ];
    if (isset($map[$type])) return $map[$type];
    return [htmlspecialchars($type, ENT_QUOTES, 'UTF-8'), 'badge-secondary'];
}

// Helper: decode details_json safely for display
function safeDetails($json) {
    if (!$json) return '-';
    $d = json_decode($json, true);
    if (!is_array($d) || empty($d)) return '-';
    $parts = [];
    foreach ($d as $k => $v) {
        if (is_array($v) || is_object($v)) continue; // skip nested
        $parts[] = '<span style="color:var(--text-muted);">' . esc((string)$k) . ':</span> <strong>' . esc((string)$v) . '</strong>';
    }
    return implode(' &nbsp;|&nbsp; ', $parts) ?: '-';
}

// Build current filter query string (for pagination links)
function buildQs($extra = []) {
    global $filterDate, $filterAction, $filterUser, $filterShift, $page;
    $base = [
        'date'   => $filterDate,
        'action' => $filterAction,
        'user'   => $filterUser,
        'shift'  => $filterShift,
        'p'      => $page,
    ];
    $merged = array_merge($base, $extra);
    $parts  = [];
    foreach ($merged as $k => $v) { if ($v !== '' && $v !== null) $parts[] = urlencode($k) . '=' . urlencode($v); }
    return $parts ? '?' . implode('&', $parts) : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Audit-page specific styles only — no overrides to global design */
        .audit-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-primary   { background: #dbeafe; color: #1d4ed8; }
        .badge-success   { background: #dcfce7; color: #166534; }
        .badge-danger    { background: #fee2e2; color: #991b1b; }
        .badge-warning   { background: #fef9c3; color: #854d0e; }
        .badge-info      { background: #e0f2fe; color: #0369a1; }
        .badge-secondary { background: #f3f4f6; color: #374151; }

        .audit-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
        }
        .audit-filters .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            min-width: 140px;
        }
        .audit-filters label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        .audit-filters select,
        .audit-filters input {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.875rem;
            background: #f9fafb;
            color: var(--text-main);
        }
        .audit-filters select:focus,
        .audit-filters input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: #fff;
        }

        /* Audit table — override min-width for denser layout */
        .audit-table table { min-width: 700px; }
        .audit-table td, .audit-table th { padding: 0.75rem 1rem; font-size: 0.85rem; }
        .audit-table .col-time    { width: 150px; white-space: nowrap; }
        .audit-table .col-action  { width: 160px; }
        .audit-table .col-user    { width: 160px; }
        .audit-table .col-shift   { width: 110px; }
        .audit-table .col-details { }
        .audit-table .col-ip      { width: 120px; font-size: 0.75rem; color: var(--text-muted); }

        .audit-empty {
            text-align: center;
            color: var(--text-muted);
            padding: 3rem 1rem;
            font-size: 0.9rem;
        }
        .pagination {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.5rem;
            border-radius: var(--radius);
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            text-decoration: none;
            transition: var(--transition);
        }
        .pagination a:hover { background: var(--bg-main); }
        .pagination .pg-active { background: var(--primary-color); color: white; border-color: var(--primary-color); }
        .pagination .pg-disabled { opacity: 0.4; pointer-events: none; }
        .result-count { font-size: 0.8rem; color: var(--text-muted); margin-left: auto; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="container header-content">
            <a href="dashboard.php" class="brand">
                <img src="assets/images/krystal-logo.png" alt="Krystal Logo" class="brand-logo">
                <span class="brand-text">KRYSTAL ATTENDANCE</span>
            </a>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span class="shift-badge"><?php echo esc($shiftLabel); ?></span>
                <a href="logout.php" class="btn btn-outline btn-sm" title="Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="container">

        <!-- Page Title + Back link -->
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
            <a href="dashboard.php" style="color:var(--text-muted); font-size:0.875rem; display:flex; align-items:center; gap:0.4rem; text-decoration:none;">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
            <h2 style="margin:0; font-size:1.25rem;"><i class="fa-solid fa-shield-halved" style="color:var(--primary-color); margin-right:0.4rem;"></i>Audit Log</h2>
            <span style="font-size:0.8rem; color:var(--text-muted);"><?php echo number_format($totalRows); ?> total entries</span>
        </div>

        <!-- Filters Card -->
        <section class="card" style="margin-bottom:1.5rem;">
            <div style="padding:1.25rem 1.5rem;">
                <form method="GET" action="audit-log.php">
                    <div class="audit-filters">
                        <div class="filter-group">
                            <label for="af-date">Date</label>
                            <input type="date" id="af-date" name="date" value="<?php echo esc($filterDate); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="af-action">Action Type</label>
                            <select id="af-action" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($actionTypes as $at): ?>
                                    <?php list($label) = actionLabel($at); ?>
                                    <option value="<?php echo esc($at); ?>" <?php echo ($filterAction === $at) ? 'selected' : ''; ?>>
                                        <?php echo esc($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="af-user">Username</label>
                            <select id="af-user" name="user">
                                <option value="">All Users</option>
                                <?php foreach ($usernames as $un): ?>
                                    <option value="<?php echo esc($un); ?>" <?php echo ($filterUser === $un) ? 'selected' : ''; ?>>
                                        <?php echo esc($un); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="af-shift">Shift</label>
                            <select id="af-shift" name="shift">
                                <option value="">All Shifts</option>
                                <option value="morning"   <?php echo ($filterShift==='morning')   ? 'selected' : ''; ?>>Morning</option>
                                <option value="afternoon" <?php echo ($filterShift==='afternoon') ? 'selected' : ''; ?>>Afternoon</option>
                                <option value="night"     <?php echo ($filterShift==='night')     ? 'selected' : ''; ?>>Night</option>
                                <option value="all"       <?php echo ($filterShift==='all')       ? 'selected' : ''; ?>>Main Admin</option>
                            </select>
                        </div>
                        <div class="filter-group" style="flex-direction:row; align-items:flex-end; gap:0.5rem;">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-filter"></i> Filter
                            </button>
                            <a href="audit-log.php" class="btn btn-outline btn-sm">
                                <i class="fa-solid fa-xmark"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Audit Log Table -->
        <section class="card">
            <header class="card-header">
                <h3 style="font-size:1rem;"><i class="fa-solid fa-list-ul"></i> Activity Records</h3>
                <?php if ($totalRows > 0): ?>
                <span style="font-size:0.8rem; color:var(--text-muted);">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </span>
                <?php endif; ?>
            </header>

            <div class="table-responsive audit-table">
                <?php if (empty($logs)): ?>
                    <div class="audit-empty">
                        <i class="fa-solid fa-inbox" style="font-size:2rem; margin-bottom:0.75rem; display:block;"></i>
                        No audit log entries found<?php echo ($filterDate || $filterAction || $filterUser || $filterShift) ? ' for the selected filters' : ''; ?>.
                    </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th class="col-time">Timestamp</th>
                            <th class="col-action">Action</th>
                            <th class="col-user">User</th>
                            <th class="col-shift">Shift</th>
                            <th class="col-details">Details</th>
                            <th class="col-ip">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <?php list($label, $badgeClass) = actionLabel($log['action_type']); ?>
                        <tr>
                            <td class="col-time" style="font-size:0.78rem; color:var(--text-muted);">
                                <?php echo esc(date('d M Y', strtotime($log['created_at']))); ?><br>
                                <strong style="color:var(--text-main);"><?php echo esc(date('H:i:s', strtotime($log['created_at']))); ?></strong>
                            </td>
                            <td class="col-action">
                                <span class="audit-badge <?php echo $badgeClass; ?>">
                                    <?php echo esc($label); ?>
                                </span>
                            </td>
                            <td class="col-user">
                                <?php if ($log['username']): ?>
                                    <strong><?php echo esc($log['username']); ?></strong><br>
                                    <span style="font-size:0.75rem; color:var(--text-muted);"><?php echo esc(ucfirst($log['role'] ?? '')); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-shift">
                                <?php if ($log['shift']): ?>
                                    <span style="font-size:0.8rem;"><?php echo esc(getShiftLabel($log['shift'])); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-details" style="font-size:0.8rem;">
                                <?php echo safeDetails($log['details_json']); ?>
                            </td>
                            <td class="col-ip">
                                <?php echo $log['ip_address'] ? esc($log['ip_address']) : '—'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                $prevPage = $page - 1;
                $nextPage = $page + 1;
                $prevQs   = buildQs(['p' => $prevPage]);
                $nextQs   = buildQs(['p' => $nextPage]);
                ?>
                <a href="audit-log.php<?php echo $prevQs; ?>" class="<?php echo ($page <= 1) ? 'pg-disabled' : ''; ?>">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <?php
                // Show limited page range around current page
                $rangeStart = max(1, $page - 2);
                $rangeEnd   = min($totalPages, $page + 2);
                if ($rangeStart > 1) { echo '<a href="audit-log.php'.buildQs(['p'=>1]).'">1</a>'; if ($rangeStart > 2) echo '<span style="border:none;">…</span>'; }
                for ($i = $rangeStart; $i <= $rangeEnd; $i++):
                    $qs = buildQs(['p' => $i]);
                ?>
                    <a href="audit-log.php<?php echo $qs; ?>" class="<?php echo ($i === $page) ? 'pg-active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor;
                if ($rangeEnd < $totalPages) { if ($rangeEnd < $totalPages - 1) echo '<span style="border:none;">…</span>'; echo '<a href="audit-log.php'.buildQs(['p'=>$totalPages]).'">'.$totalPages.'</a>'; }
                ?>
                <a href="audit-log.php<?php echo $nextQs; ?>" class="<?php echo ($page >= $totalPages) ? 'pg-disabled' : ''; ?>">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
                <span class="result-count"><?php echo number_format($totalRows); ?> total &nbsp;·&nbsp; <?php echo $perPage; ?> per page</span>
            </div>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
