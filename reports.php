<?php
/**
 * Advanced Reporting Module — Admin Only
 * Phase 4B — Krystal Attendance System
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
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
    <title>Employee History & Reports - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .filter-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
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
                <span class="shift-badge">Advanced Reports</span>
                <a href="dashboard.php" class="btn btn-outline btn-sm" title="Dashboard">
                    <i class="fa-solid fa-gauge-high"></i>
                </a>
                <a href="employees.php" class="btn btn-outline btn-sm" title="Employee Master">
                    <i class="fa-solid fa-users-gear"></i>
                </a>
                <a href="audit-log.php" class="btn btn-outline btn-sm" title="Audit Log">
                    <i class="fa-solid fa-shield-halved"></i>
                </a>
                <a href="logout.php" class="btn btn-outline btn-sm" title="Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Filters Card -->
        <section class="card">
            <header class="card-header">
                <h3>Report Filters</h3>
            </header>
            <div style="padding: 1.5rem;">
                <form id="report-form">
                    <div class="filter-grid">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Employee Search</label>
                            <div class="autocomplete-wrapper">
                                <input type="text" id="report-emp-search" class="form-control" placeholder="Name or ID..." autocomplete="off">
                                <div class="autocomplete-dropdown" id="report-emp-dropdown"></div>
                                <input type="hidden" id="report-emp-id" value="">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="report-start-date" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">End Date</label>
                            <input type="date" id="report-end-date" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Location</label>
                            <select id="report-location" class="form-control">
                                <option value="all">All Locations</option>
                                <option value="landside">Landside</option>
                                <option value="asset">Asset</option>
                                <option value="cargo">Cargo</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Shift</label>
                            <select id="report-shift" class="form-control">
                                <option value="all">All Shifts</option>
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                                <option value="night">Night</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Status</label>
                            <select id="report-status" class="form-control">
                                <option value="all">All Statuses</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="halfday">Half Day</option>
                                <option value="leave">Leave</option>
                                <option value="weekoff">Week Off</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <div style="display:flex; gap: 0.5rem; align-items:center;">
                            <button type="button" id="report-clear-btn" class="btn btn-outline btn-sm">Clear Filters</button>
                            <span id="report-emp-badge" class="badge badge-info" style="display:none; background:#0dcaf0; color:white;">
                                Filtered: <span id="report-emp-badge-text"></span> 
                                <i class="fa-solid fa-xmark" id="report-emp-clear" style="margin-left:5px; cursor:pointer;"></i>
                            </span>
                        </div>
                        <div style="display:flex; gap: 0.5rem;">
                            <button type="button" id="report-export-csv" class="btn btn-outline btn-sm">
                                <i class="fa-solid fa-file-csv"></i> Export CSV
                            </button>
                            <button type="button" id="report-export-pdf" class="btn btn-outline btn-sm">
                                <i class="fa-solid fa-file-pdf"></i> Download PDF
                            </button>
                            <button type="submit" id="report-search-btn" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-magnifying-glass"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Results Table -->
        <section class="card">
            <header class="card-header">
                <h3>Attendance History <span id="report-count-badge" class="badge badge-success" style="margin-left: 0.5rem;">0</span></h3>
            </header>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Shift</th>
                            <th>Emp ID</th>
                            <th>Name</th>
                            <th>Post</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="report-tbody">
                        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">Set filters and click Search to view history.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="js/reports.js"></script>
</body>
</html>
