<?php
/**
 * Migration: Create employees master table
 * Phase 4A — Employee Master System
 *
 * Usage (CLI only):
 *   php migrate_employees.php
 *
 * Browser access is blocked by .htaccess.
 * Uses lock-file pattern to prevent accidental re-execution.
 */

// Lock file guard
$lockFile = __DIR__ . '/.employees_migrate_lock';
if (file_exists($lockFile)) {
    echo "Migration already completed on " . file_get_contents($lockFile) . "\n";
    exit(0);
}

// DB connection (direct — not using config.php to keep migration self-contained)
$host   = 'localhost';
$dbname = 'krystal_attendance';
$dbuser = 'root';
$dbpass = 'hello brother';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage() . "\n");
}

$steps = [];

// Step 1: Create employees master table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS employees (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(20)  NOT NULL,
        name        VARCHAR(150) NOT NULL,
        post        VARCHAR(50)  NOT NULL,
        status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
        notes       TEXT         DEFAULT NULL,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY  uk_employee_id (employee_id),
        INDEX       idx_emp_name   (name),
        INDEX       idx_emp_status (status),
        INDEX       idx_emp_post   (post)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$steps[] = '✓ Created employees table with indexes.';

// Step 2: Verify table structure
$cols = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'Field');
$expected = ['id', 'employee_id', 'name', 'post', 'status', 'notes', 'created_at', 'updated_at'];
$missing = array_diff($expected, $colNames);
if (count($missing) > 0) {
    die("ERROR: Missing columns: " . implode(', ', $missing) . "\n");
}
$steps[] = '✓ Verified all 8 columns exist.';

// Step 3: Verify indexes
$indexes = $pdo->query("SHOW INDEX FROM employees")->fetchAll(PDO::FETCH_ASSOC);
$indexNames = array_unique(array_column($indexes, 'Key_name'));
$expectedIdx = ['PRIMARY', 'uk_employee_id', 'idx_emp_name', 'idx_emp_status', 'idx_emp_post'];
$missingIdx = array_diff($expectedIdx, $indexNames);
if (count($missingIdx) > 0) {
    die("ERROR: Missing indexes: " . implode(', ', $missingIdx) . "\n");
}
$steps[] = '✓ Verified all indexes (PRIMARY, uk_employee_id, idx_emp_name, idx_emp_status, idx_emp_post).';

// Write lock file
file_put_contents($lockFile, date('Y-m-d H:i:s'));
$steps[] = '✓ Lock file created.';

echo "=== EMPLOYEES MIGRATION COMPLETE ===\n";
foreach ($steps as $s) {
    echo "  $s\n";
}
echo "\nTable 'employees' is ready for use.\n";
