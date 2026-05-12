<?php
/**
 * Audit Log Table Migration - Krystal Attendance System
 *
 * Creates the `audit_log` table if it does not already exist.
 * Safe to run multiple times (CREATE TABLE IF NOT EXISTS).
 *
 * HOW TO RUN:
 *   Access via browser: http://localhost/krystal/migrate_audit.php?key=krystal_migrate_2026
 *   OR run via CLI:     php migrate_audit.php --key=krystal_migrate_2026
 *
 * SECURITY: Key-gated + locked after first run.
 */

$lockFile = __DIR__ . '/.audit_migrate_lock';
$requiredKey = 'krystal_migrate_2026';

// CLI support
$cliKey = '';
if (PHP_SAPI === 'cli') {
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--key=') === 0) {
            $cliKey = substr($arg, 6);
        }
    }
    $providedKey = $cliKey;
} else {
    $providedKey = isset($_GET['key']) ? $_GET['key'] : '';
}

if ($providedKey !== $requiredKey) {
    http_response_code(403);
    die('<h2 style="color:red;">Access Denied.</h2><p>Missing or invalid key.</p>');
}

if (file_exists($lockFile)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
    }
    die('<h2 style="color:orange;">Migration already applied.</h2><p>Delete <code>.audit_migrate_lock</code> to re-run.</p>');
}

// Minimal DB connection (avoid loading full config to prevent side effects)
$host   = 'localhost';
$dbuser = 'root';
$dbpass = 'hello brother';
$dbname = 'krystal_attendance';

try {
    $pdo = new PDO(
        'mysql:host='.$host.';dbname='.$dbname.';charset=utf8mb4',
        $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
        `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`      INT DEFAULT NULL,
        `username`     VARCHAR(100) DEFAULT NULL,
        `role`         VARCHAR(20) DEFAULT NULL,
        `shift`        VARCHAR(20) DEFAULT NULL,
        `action_type`  VARCHAR(80) NOT NULL,
        `target_type`  VARCHAR(50) DEFAULT NULL,
        `target_id`    INT DEFAULT NULL,
        `details_json` TEXT DEFAULT NULL,
        `ip_address`   VARCHAR(45) DEFAULT NULL,
        `user_agent`   VARCHAR(255) DEFAULT NULL,
        `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_action`     (`action_type`),
        INDEX `idx_user_id`    (`user_id`),
        INDEX `idx_username`   (`username`),
        INDEX `idx_shift`      (`shift`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Write lock file so this can't be re-run accidentally
    file_put_contents($lockFile, date('Y-m-d H:i:s'));

    if (PHP_SAPI === 'cli') {
        echo "✓ audit_log table created successfully.\n";
    } else {
        echo '<h2 style="color:green;">✓ audit_log table created successfully!</h2>';
        echo '<p><a href="index.php">Go to Login</a></p>';
    }

} catch (PDOException $e) {
    if (PHP_SAPI === 'cli') {
        echo "✗ Migration failed: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo '<h2 style="color:red;">Migration Error</h2>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
