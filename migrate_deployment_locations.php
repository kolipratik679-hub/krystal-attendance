<?php
/**
 * Migration: Create deployment_locations master table + add column to attendance_employees
 * Phase 5A — Deployment Location Master System
 *
 * Usage:
 *   Browser: http://localhost/krystal/migrate_deployment_locations.php?key=krystal_deploc_2026
 *   CLI:     php migrate_deployment_locations.php --key=krystal_deploc_2026
 *
 * SECURITY: Key-gated + locked after first run.
 */

$lockFile = __DIR__ . '/.deployment_locations_migrate_lock';
$requiredKey = 'krystal_deploc_2026';

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
    if (PHP_SAPI !== 'cli') { http_response_code(403); }
    die('<h2 style="color:orange;">Migration already applied.</h2><p>Delete <code>.deployment_locations_migrate_lock</code> to re-run.</p>');
}

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

    $steps = [];

    // Step 1: Create deployment_locations master table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deployment_locations (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL,
            status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
            notes       TEXT         DEFAULT NULL,
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY  uk_deploc_name (name),
            INDEX       idx_deploc_name   (name),
            INDEX       idx_deploc_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $steps[] = '✓ Created deployment_locations table with indexes.';

    // Step 2: Verify table structure
    $cols = $pdo->query("SHOW COLUMNS FROM deployment_locations")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'Field');
    $expected = ['id', 'name', 'status', 'notes', 'created_at', 'updated_at'];
    $missing = array_diff($expected, $colNames);
    if (count($missing) > 0) {
        die("ERROR: Missing columns in deployment_locations: " . implode(', ', $missing) . "\n");
    }
    $steps[] = '✓ Verified all 6 columns exist in deployment_locations.';

    // Step 3: Add deployment_location column to attendance_employees (if not exists)
    $cols = $pdo->query("SHOW COLUMNS FROM attendance_employees LIKE 'deployment_location'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE attendance_employees ADD COLUMN deployment_location VARCHAR(100) NOT NULL DEFAULT '' AFTER status");
        $steps[] = '✓ Added deployment_location column to attendance_employees.';
    } else {
        $steps[] = '⊘ attendance_employees.deployment_location column already exists.';
    }

    // Step 4: Add index on deployment_location for query performance
    $indexes = $pdo->query("SHOW INDEX FROM attendance_employees WHERE Key_name = 'idx_deploc'")->fetchAll();
    if (empty($indexes)) {
        $pdo->exec("CREATE INDEX idx_deploc ON attendance_employees (deployment_location)");
        $steps[] = '✓ Created idx_deploc index on attendance_employees.';
    } else {
        $steps[] = '⊘ idx_deploc index already exists.';
    }

    // Write lock file
    file_put_contents($lockFile, date('Y-m-d H:i:s'));
    $steps[] = '✓ Lock file created.';

    // Output
    $output = implode("\n", $steps);
    if (PHP_SAPI === 'cli') {
        echo $output . "\n✓ Deployment Locations migration complete.\n";
    } else {
        echo '<h2 style="color:green;">✓ Migration Complete</h2>';
        echo '<pre>' . htmlspecialchars($output) . '</pre>';
        echo '<p><a href="deployment-locations.php">Go to Deployment Locations</a> | <a href="dashboard.php">Dashboard</a></p>';
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
