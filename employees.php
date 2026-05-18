<?php
/**
 * Employee Master Management — Admin Only
 * Phase 4A — Krystal Attendance System
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();
$user = getSessionUser();

// Admin-only access
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Master - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=4c1">
</head>
<body>
    <?php renderLayoutStart($user, 'employees', 'Employee Master'); ?>

        <!-- Filters -->
        <div class="action-bar">
            <div class="action-bar-left" style="display: flex; gap: 0.75rem; flex-wrap: wrap; flex: 1;">
                <div style="position: relative; flex: 1; max-width: 320px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="emp-search" class="form-control" placeholder="Search by Name or ID..." style="padding-left: 2.5rem; width: 100%;">
                </div>
                <select id="emp-status-filter" class="form-control" style="width: 160px;">
                    <option value="">All Status</option>
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select id="emp-post-filter" class="form-control" style="width: 160px;">
                    <option value="">All Posts</option>
                    <option value="incharge">Incharge</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="bouncer">Bouncer</option>
                    <option value="guard">Guard</option>
                    <option value="driver">Driver</option>
                </select>
            </div>
            <div class="action-bar-right" style="display: flex; gap: 0.75rem;">
                <button id="emp-export-csv" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
                <button id="emp-export-pdf" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button id="emp-add-btn" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Add Employee
                </button>
            </div>
        </div>

        <!-- Add/Edit Form (hidden by default) -->
        <section class="card" id="emp-form-card" style="display: none;">
            <header class="card-header">
                <h3 id="emp-form-title">Add Employee</h3>
                <button type="button" id="emp-form-close" class="btn btn-outline btn-sm" title="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>
            <form id="emp-form" class="form-grid">
                <input type="hidden" id="emp-edit-id" value="0">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="emp-id-input" class="form-label">Employee ID</label>
                    <input type="text" id="emp-id-input" class="form-control" placeholder="e.g. 101" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="emp-name-input" class="form-label">Full Name</label>
                    <input type="text" id="emp-name-input" class="form-control" placeholder="John Doe" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="emp-post-input" class="form-label">Post / Designation</label>
                    <select id="emp-post-input" class="form-control" required>
                        <option value="" disabled selected>Select Post...</option>
                        <option value="incharge">Incharge</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="bouncer">Bouncer</option>
                        <option value="guard">Guard</option>
                        <option value="driver">Driver</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="emp-status-input" class="form-label">Status</label>
                    <select id="emp-status-input" class="form-control" required>
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="emp-notes-input" class="form-label">Notes (optional)</label>
                    <input type="text" id="emp-notes-input" class="form-control" placeholder="Optional notes...">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" id="emp-submit-btn" class="btn btn-primary w-full" style="height: 46px;">
                        <i class="fa-solid fa-plus"></i> Add Employee
                    </button>
                </div>
            </form>
        </section>

        <!-- Employee List -->
        <section class="card">
            <header class="card-header">
                <h3>Employee List <span id="emp-count-badge" class="badge badge-success" style="margin-left: 0.5rem;">0</span></h3>
            </header>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Emp ID</th>
                            <th>Name</th>
                            <th>Post</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th style="text-align: right; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="emp-tbody">
                    </tbody>
                </table>
            </div>
        </section>

    <?php renderLayoutEnd(); ?>

    <script>
        var CSRF_TOKEN = <?php echo json_encode(getCsrfToken()); ?>;
    </script>
    <script src="js/employees.js"></script>
</body>
</html>
