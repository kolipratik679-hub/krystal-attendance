<?php
require_once __DIR__ . '/includes/auth.php';
startSecureSession();
// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Krystal Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=4c1">
</head>

<body>
    <div class="login-wrapper">
        <main class="login-card">
            <div class="brand">
                <img src="assets/images/krystal-logo.png" alt="Krystal Logo" class="brand-logo">
                KRYSTAL
            </div>
            <p class="login-subtitle">Attendance Management System</p>

            <div id="login-error"
                style="display: none; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; text-align: center;">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="login-error-text"></span>
            </div>

            <form id="login-form" action="#" method="POST">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div style="position: relative;">
                        <i class="fa-regular fa-user"
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" id="username" class="form-control" placeholder="landside.morning" required
                            style="padding-left: 2.5rem;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-lock"
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="password" id="password" class="form-control" placeholder="••••••••" required
                            style="padding-left: 2.5rem;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full mt-2">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </button>
            </form>
        </main>
    </div>

    <script>var CSRF_TOKEN = <?php echo json_encode(getCsrfToken()); ?>;</script>
    <script src="js/app.js"></script>
</body>

</html>