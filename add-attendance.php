<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getSessionUser();

// Check if editing existing record
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editData = null;
$activeShift = $user['shift']; // Default to user's shift
$activeLocation = $user['location']; // Default to user's location

if ($user['role'] === 'admin') {
    // For admin, allow shift + location override from URL
    $selectedShift = isset($_GET['shift']) ? $_GET['shift'] : '';
    if (in_array($selectedShift, ['morning', 'afternoon', 'night'])) {
        $activeShift = $selectedShift;
    }
    $selectedLocation = isset($_GET['location']) ? $_GET['location'] : '';
    if (in_array($selectedLocation, ['landside', 'asset', 'cargo'])) {
        $activeLocation = $selectedLocation;
        // Audit: log shift+location selection when admin starts a new session
        if (isset($_GET['new']) && $_GET['new'] === '1') {
            auditLog('SHIFT_SELECTED', [
                'selected_shift'    => $activeShift,
                'selected_location' => $activeLocation,
            ]);
        }
    }
}

if ($editId > 0) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM attendance_records WHERE id = ?');
    $stmt->execute([$editId]);
    $rec = $stmt->fetch();
    if ($rec) {
        if ($user['role'] === 'admin' || ($rec['shift'] === $user['shift'] && $rec['location'] === $user['location'])) {
            $activeShift = $rec['shift'];
            $activeLocation = $rec['location'];
            $emps = getRecordEmployees($db, $rec['id']);
            $editData = [
                'id' => (int)$rec['id'],
                'date' => $rec['attendance_date'],
                'shift' => $rec['shift'],
                'location' => $rec['location'],
                'employees' => array_map(function($e) {
                    return [
                        'name' => $e['employee_name'],
                        'id' => $e['employee_id'],
                        'post' => $e['post'],
                        'status' => $e['status']
                    ];
                }, $emps)
            ];
        }
    }
}

if ($user['role'] === 'admin') {
    $headerLabel = getLocationLabel($activeLocation) . ' — ' . getShiftLabel($activeShift);
} else {
    $headerLabel = getLocationLabel($user['location']) . ' — ' . getShiftLabel($activeShift);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Attendance - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="app-header">
        <div class="container header-content">
            <a href="dashboard.php" class="brand">
                <img src="assets/images/krystal-logo.png" alt="Krystal Logo" class="brand-logo">
                <span class="brand-text">KRYSTAL ATTENDANCE</span>
            </a>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span class="shift-badge"><?php echo esc($headerLabel); ?></span>
                <a href="logout.php" class="btn btn-outline btn-sm" title="Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="action-bar">
            <div class="action-bar-left" style="flex: 1;">
                <div style="position: relative; width: 100%; max-width: 400px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="search-input" class="form-control" placeholder="Search by Name or ID..." style="padding-left: 2.5rem; width: 100%;">
                </div>
                <div style="width: 100%; max-width: 200px;">
                    <select id="status-filter" class="form-control">
                        <option value="">All Status</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="halfday">Half Day</option>
                        <option value="leave">Leave</option>
                        <option value="weekoff">Week Off</option>
                    </select>
                </div>
            </div>
            <div class="action-bar-right">
                <input type="date" class="form-control" id="attendance-date">
            </div>
        </div>

        <section class="card">
            <header class="card-header">
                <h3>Add Staff Member</h3>
            </header>
            <form action="#" method="POST" class="form-grid" id="staff-form">
                <input type="hidden" id="staff-master-validated" value="0">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="staff-name" class="form-label">Full Name</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="staff-name" class="form-control" placeholder="Type employee name..." required autocomplete="off">
                        <div class="autocomplete-dropdown" id="name-dropdown"></div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="staff-id" class="form-label">Staff ID</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="staff-id" class="form-control" placeholder="Type employee ID..." required autocomplete="off">
                        <div class="autocomplete-dropdown" id="id-dropdown"></div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="staff-post" class="form-label">Post</label>
                    <select id="staff-post" class="form-control" required>
                        <option value="" disabled selected>Select Post...</option>
                        <option value="incharge">Incharge</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="bouncer">Bouncer</option>
                        <option value="guard">Guard</option>
                        <option value="driver">Driver</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="staff-status" class="form-label">Status</label>
                    <select id="staff-status" class="form-control" required>
                        <option value="present" selected>Present</option>
                        <option value="absent">Absent</option>
                        <option value="halfday">Half Day</option>
                        <option value="leave">Leave</option>
                        <option value="weekoff">Week Off</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" id="submit-btn" class="btn btn-primary w-full" style="height: 46px;">
                        <i class="fa-solid fa-plus"></i> Add
                    </button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Post</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-tbody">
                    </tbody>
                </table>
            </div>
        </section>

        <div class="action-bar" style="justify-content: center; gap: 1rem; flex-wrap: wrap; background: transparent; box-shadow: none; padding: 0;">
            <button id="preview-btn" class="btn btn-outline">
                <i class="fa-solid fa-eye"></i> Preview
            </button>
            <button id="export-csv-btn" class="btn btn-outline">
                <i class="fa-solid fa-file-csv"></i> Export CSV
            </button>
            <button id="download-pdf-btn" class="btn btn-outline">
                <i class="fa-solid fa-file-pdf"></i> Download PDF
            </button>
            <button id="final-save-btn" class="btn btn-success">
                <i class="fa-solid fa-check-double"></i> Final Save
            </button>
        </div>
    </main>

    <script>
        var SESSION_USER = <?php echo json_encode(['role' => $user['role'], 'shift' => $user['shift'], 'location' => $user['location']]); ?>;
        var ACTIVE_SHIFT = <?php echo json_encode($activeShift); ?>;
        var ACTIVE_LOCATION = <?php echo json_encode($activeLocation); ?>;
        var EDIT_DATA = <?php echo $editData ? json_encode($editData) : 'null'; ?>;
        var CSRF_TOKEN = <?php echo json_encode(getCsrfToken()); ?>;
    </script>
    <script src="js/app.js"></script>
</body>
</html>
