<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$user = getSessionUser();
if ($user['role'] !== 'admin') {
    header('Location: add-attendance.php?new=1');
    exit;
}

$headerLabel = 'Main Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Location & Shift - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=4c1">
</head>
<body>
    <?php renderLayoutStart($user, 'add-attendance', 'Select Location & Shift'); ?>

        <div style="max-width: 960px; margin: 0 auto;">
            <div style="margin-bottom: 2rem; text-align: center;">
                <h2 style="color: var(--text-main); margin-bottom: 0.5rem;">Select Location & Shift</h2>
                <p style="color: var(--text-muted);">Please choose the location and shift for which you want to add attendance.</p>
            </div>

            <div class="shift-grid">
                <!-- LANDSIDE Column -->
                <div>
                    <h3 style="text-align: center; color: var(--text-main); margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="fa-solid fa-building" style="color: #f97316;"></i> Landside
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <a href="add-attendance.php?new=1&shift=morning&location=landside" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #fff7ed; color: #f97316; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-sun"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Morning Shift</h4>
                            </div>
                        </a>
                        <a href="add-attendance.php?new=1&shift=afternoon&location=landside" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #ecfdf5; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-cloud-sun"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Afternoon Shift</h4>
                            </div>
                        </a>
                        <a href="add-attendance.php?new=1&shift=night&location=landside" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #eff6ff; color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-moon"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Night Shift</h4>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- ASSET Column -->
                <div>
                    <h3 style="text-align: center; color: var(--text-main); margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="fa-solid fa-warehouse" style="color: #10b981;"></i> Asset
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <a href="add-attendance.php?new=1&shift=morning&location=asset" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #fff7ed; color: #f97316; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-sun"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Morning Shift</h4>
                            </div>
                        </a>
                        <a href="add-attendance.php?new=1&shift=afternoon&location=asset" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #ecfdf5; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-cloud-sun"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Afternoon Shift</h4>
                            </div>
                        </a>
                        <a href="add-attendance.php?new=1&shift=night&location=asset" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #eff6ff; color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-moon"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Night Shift</h4>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- CARGO Column -->
                <div>
                    <h3 style="text-align: center; color: var(--text-main); margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="fa-solid fa-boxes-stacked" style="color: #3b82f6;"></i> Cargo
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <a href="add-attendance.php?new=1&shift=morning&location=cargo" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #fff7ed; color: #f97316; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-sun"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Morning Shift</h4>
                            </div>
                        </a>
                        <a href="add-attendance.php?new=1&shift=afternoon&location=cargo" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #ecfdf5; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-cloud-sun"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Afternoon Shift</h4>
                            </div>
                        </a>
                        <a href="add-attendance.php?new=1&shift=night&location=cargo" class="card shift-card" style="text-decoration: none; display: block; transition: transform 0.2s, box-shadow 0.2s; border: 2px solid transparent;">
                            <div style="padding: 1.25rem; text-align: center;">
                                <div style="width: 48px; height: 48px; background: #eff6ff; color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem;">
                                    <i class="fa-solid fa-moon"></i>
                                </div>
                                <h4 style="color: var(--text-main); margin-bottom: 0.25rem; font-size: 0.95rem;">Night Shift</h4>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <div style="margin-top: 2rem; text-align: center;">
                <a href="dashboard.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.875rem;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

    <?php renderLayoutEnd(); ?>

    <style>
        .shift-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color) !important;
        }
        .shift-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }
        @media (max-width: 768px) {
            .shift-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
