<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getSessionUser();
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$shiftParam = isset($_GET['shift']) ? $_GET['shift'] : '';
$locationParam = isset($_GET['location']) ? $_GET['location'] : '';
$activeShift = $user['shift'];
$activeLocation = $user['location'];
if ($user['role'] === 'admin' && in_array($shiftParam, ['morning', 'afternoon', 'night'])) {
    $activeShift = $shiftParam;
}
if ($user['role'] === 'admin' && in_array($locationParam, ['landside', 'asset', 'cargo'])) {
    $activeLocation = $locationParam;
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
    <title>Preview Attendance - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="app-header print-hide">
        <div class="container header-content">
            <div class="brand">
                <img src="assets/images/krystal-logo.png" alt="Krystal Logo" class="brand-logo">
                <span class="brand-text">KRYSTAL ATTENDANCE</span>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span class="shift-badge"><?php echo esc($headerLabel); ?></span>
                <span class="badge" style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main);"></span>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="action-bar print-hide">
            <div class="action-bar-left">
                <?php
                    // Build back URL preserving shift + location params
                    if ($editId) {
                        $backUrl = 'add-attendance.php?edit=' . $editId;
                        if ($user['role'] === 'admin') {
                            if ($shiftParam) $backUrl .= '&shift=' . $shiftParam;
                            if ($locationParam) $backUrl .= '&location=' . $locationParam;
                        }
                    } else {
                        $backUrl = 'add-attendance.php';
                        $qsParts = [];
                        if ($user['role'] === 'admin' && $shiftParam) $qsParts[] = 'shift=' . $shiftParam;
                        if ($user['role'] === 'admin' && $locationParam) $qsParts[] = 'location=' . $locationParam;
                        if ($qsParts) $backUrl .= '?' . implode('&', $qsParts);
                    }
                ?>
                <a href="<?php echo $backUrl; ?>" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
            </div>
            <div class="action-bar-right">
                <a href="<?php echo $backUrl; ?>" class="btn btn-outline">
                    <i class="fa-solid fa-pen"></i> Edit Attendance
                </a>
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i class="fa-solid fa-print"></i> Print (A4)
                </button>
            </div>
        </div>

        <div class="print-header" style="display: none; text-align: center; margin-bottom: 2rem;">
            <h2>KRYSTAL ATTENDANCE</h2>
            <p>Daily Attendance Report</p>
        </div>

        <!-- Section: Incharges -->
        <section class="card">
            <header class="card-header" style="background: #f8fafc;">
                <h3><i class="fa-solid fa-user-tie"></i> Incharges</h3>
            </header>
            <div style="padding: 1.5rem 1.5rem 0;">
                <div class="preview-stats">
                    <div class="stat-box"><div class="stat-value">0</div><div class="stat-label">Total</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--success-color);"><div class="stat-value" style="color: var(--success-color);">0</div><div class="stat-label">Present</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--danger-color);"><div class="stat-value" style="color: var(--danger-color);">0</div><div class="stat-label">Absent</div></div>
                </div>
            </div>
            <div class="table-responsive"><table><thead><tr><th style="width: 120px;">ID</th><th>Name</th><th style="text-align: right; width: 120px;">Status</th></tr></thead><tbody></tbody></table></div>
        </section>

        <!-- Section: Supervisors -->
        <section class="card">
            <header class="card-header" style="background: #f8fafc;">
                <h3><i class="fa-solid fa-clipboard-user"></i> Supervisors</h3>
            </header>
            <div style="padding: 1.5rem 1.5rem 0;">
                <div class="preview-stats">
                    <div class="stat-box"><div class="stat-value">0</div><div class="stat-label">Total</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--success-color);"><div class="stat-value" style="color: var(--success-color);">0</div><div class="stat-label">Present</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--danger-color);"><div class="stat-value" style="color: var(--danger-color);">0</div><div class="stat-label">Absent</div></div>
                </div>
            </div>
            <div class="table-responsive"><table><thead><tr><th style="width: 120px;">ID</th><th>Name</th><th style="text-align: right; width: 120px;">Status</th></tr></thead><tbody></tbody></table></div>
        </section>

        <!-- Section: Bouncers -->
        <section class="card">
            <header class="card-header" style="background: #f8fafc;">
                <h3><i class="fa-solid fa-user-ninja"></i> Bouncers</h3>
            </header>
            <div style="padding: 1.5rem 1.5rem 0;">
                <div class="preview-stats">
                    <div class="stat-box"><div class="stat-value">0</div><div class="stat-label">Total</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--success-color);"><div class="stat-value" style="color: var(--success-color);">0</div><div class="stat-label">Present</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--danger-color);"><div class="stat-value" style="color: var(--danger-color);">0</div><div class="stat-label">Absent</div></div>
                </div>
            </div>
            <div class="table-responsive"><table><thead><tr><th style="width: 120px;">ID</th><th>Name</th><th style="text-align: right; width: 120px;">Status</th></tr></thead><tbody></tbody></table></div>
        </section>

        <!-- Section: Guards -->
        <section class="card">
            <header class="card-header" style="background: #f8fafc;">
                <h3><i class="fa-solid fa-user-shield"></i> Guards</h3>
            </header>
            <div style="padding: 1.5rem 1.5rem 0;">
                <div class="preview-stats">
                    <div class="stat-box"><div class="stat-value">0</div><div class="stat-label">Total</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--success-color);"><div class="stat-value" style="color: var(--success-color);">0</div><div class="stat-label">Present</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--danger-color);"><div class="stat-value" style="color: var(--danger-color);">0</div><div class="stat-label">Absent</div></div>
                </div>
            </div>
            <div class="table-responsive"><table><thead><tr><th style="width: 120px;">ID</th><th>Name</th><th style="text-align: right; width: 120px;">Status</th></tr></thead><tbody></tbody></table></div>
        </section>

        <!-- Section: Drivers -->
        <section class="card">
            <header class="card-header" style="background: #f8fafc;">
                <h3><i class="fa-solid fa-car"></i> Drivers</h3>
            </header>
            <div style="padding: 1.5rem 1.5rem 0;">
                <div class="preview-stats">
                    <div class="stat-box"><div class="stat-value">0</div><div class="stat-label">Total</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--success-color);"><div class="stat-value" style="color: var(--success-color);">0</div><div class="stat-label">Present</div></div>
                    <div class="stat-box" style="border-bottom: 3px solid var(--danger-color);"><div class="stat-value" style="color: var(--danger-color);">0</div><div class="stat-label">Absent</div></div>
                </div>
            </div>
            <div class="table-responsive"><table><thead><tr><th style="width: 120px;">ID</th><th>Name</th><th style="text-align: right; width: 120px;">Status</th></tr></thead><tbody></tbody></table></div>
        </section>

        <!-- Overall Summary -->
        <div class="summary-card">
            <div class="summary-item"><div class="stat-label">Total Staff</div><div class="stat-value">0</div></div>
            <div class="summary-item"><div class="stat-label">Total Present</div><div class="stat-value" style="color: var(--success-color);">0</div></div>
            <div class="summary-item"><div class="stat-label">Total Absent</div><div class="stat-value" style="color: var(--danger-color);">0</div></div>
        </div>
    </main>

    <script src="js/app.js"></script>
    <style>
        @media print {
            .print-hide { display: none !important; }
            .print-header { display: block !important; }
            body { background: white; }
            .container { max-width: 100%; padding: 0; }
            .card { box-shadow: none; border: 1px solid #ccc; break-inside: avoid; margin-bottom: 1rem; }
            .summary-card { box-shadow: none; border: 2px solid #000; }
        }
    </style>
</body>
</html>
