<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();
$user = getSessionUser();
if ($user['role'] === 'admin') {
    $headerLabel = 'Main Admin';
} else {
    $headerLabel = getLocationLabel($user['location']) . ' — ' . getShiftLabel($user['shift']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=4c1">
</head>
<body>
    <?php renderLayoutStart($user, 'dashboard', 'Dashboard'); ?>

        <div class="action-bar">
            <div class="action-bar-left" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <div style="position: relative;">
                    <i class="fa-regular fa-calendar" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="date" id="date-filter" class="form-control" style="padding-left: 2.5rem; width: 200px;">
                </div>
                <?php if ($user['role'] === 'admin'): ?>
                <select id="location-filter" class="form-control" style="width: 180px;">
                    <option value="">All Locations</option>
                    <option value="landside">Landside</option>
                    <option value="asset">Asset</option>
                    <option value="cargo">Cargo</option>
                </select>
                <select id="shift-filter" class="form-control" style="width: 180px;">
                    <option value="">All Shifts</option>
                    <option value="morning">Morning Shift</option>
                    <option value="afternoon">Afternoon Shift</option>
                    <option value="night">Night Shift</option>
                </select>
                <?php endif; ?>
            </div>
            <div class="action-bar-right">
                <a href="<?php echo $user['role'] === 'admin' ? 'select-shift.php' : 'add-attendance.php?new=1'; ?>" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add New Attendance
                </a>
            </div>
        </div>

        <section class="card">
            <header class="card-header">
                <h3>Saved Attendance Records</h3>
            </header>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th style="width: 200px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="dashboard-tbody">
                    </tbody>
                </table>
            </div>
        </section>

    <?php renderLayoutEnd(); ?>

    <script>
        var SESSION_USER = <?php echo json_encode(['role' => $user['role'], 'shift' => $user['shift'], 'location' => $user['location']]); ?>;
        var CSRF_TOKEN = <?php echo json_encode(getCsrfToken()); ?>;
    </script>
    <script src="js/app.js"></script>
</body>
</html>
