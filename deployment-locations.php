<?php
/**
 * Deployment Location Master Management — Admin Only
 * Phase 5A — Krystal Attendance System
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
    <title>Deployment Locations - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=5a1">
</head>
<body>
    <?php renderLayoutStart($user, 'deployment-locations', 'Deployment Locations'); ?>

        <!-- Filters -->
        <div class="action-bar">
            <div class="action-bar-left" style="display: flex; gap: 0.75rem; flex-wrap: wrap; flex: 1;">
                <div style="position: relative; flex: 1; max-width: 320px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="deploc-search" class="form-control" placeholder="Search by Location Name..." style="padding-left: 2.5rem; width: 100%;">
                </div>
                <select id="deploc-status-filter" class="form-control" style="width: 160px;">
                    <option value="">All Status</option>
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="action-bar-right" style="display: flex; gap: 0.75rem;">
                <button id="deploc-export-csv" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
                <button id="deploc-export-pdf" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button id="deploc-add-btn" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Add Location
                </button>
            </div>
        </div>

        <!-- Add/Edit Form (hidden by default) -->
        <section class="card" id="deploc-form-card" style="display: none;">
            <header class="card-header">
                <h3 id="deploc-form-title">Add Deployment Location</h3>
                <button type="button" id="deploc-form-close" class="btn btn-outline btn-sm" title="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>
            <form id="deploc-form" class="form-grid">
                <input type="hidden" id="deploc-edit-id" value="0">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="deploc-name-input" class="form-label">Location Name</label>
                    <input type="text" id="deploc-name-input" class="form-control" placeholder="e.g. Terminal 1" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="deploc-status-input" class="form-label">Status</label>
                    <select id="deploc-status-input" class="form-control" required>
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="deploc-notes-input" class="form-label">Notes (optional)</label>
                    <input type="text" id="deploc-notes-input" class="form-control" placeholder="Optional notes...">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" id="deploc-submit-btn" class="btn btn-primary w-full" style="height: 46px;">
                        <i class="fa-solid fa-plus"></i> Add Location
                    </button>
                </div>
            </form>
        </section>

        <!-- Location List -->
        <section class="card">
            <header class="card-header">
                <h3>Location List <span id="deploc-count-badge" class="badge badge-success" style="margin-left: 0.5rem;">0</span></h3>
            </header>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Location Name</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th style="text-align: right; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="deploc-tbody">
                    </tbody>
                </table>
            </div>
        </section>

    <?php renderLayoutEnd(); ?>

    <script>
        var CSRF_TOKEN = <?php echo json_encode(getCsrfToken()); ?>;
    </script>
    <script src="js/deployment-locations.js"></script>
</body>
</html>
