<?php
/**
 * Multi-Location Migration - Krystal Attendance System
 * Phase: Multi-Location Access Isolation
 *
 * Adds `location` column to users and attendance_records tables.
 * Creates 9 location-shift user accounts + updates admin.
 * Safe to run multiple times (IF NOT EXISTS / IF checks).
 *
 * HOW TO RUN:
 *   Browser: http://localhost/krystal/migrate_locations.php?key=krystal_locations_2026
 *   CLI:     php migrate_locations.php --key=krystal_locations_2026
 *
 * SECURITY: Key-gated + locked after first run.
 */

$lockFile = __DIR__ . '/.locations_migrate_lock';
$requiredKey = 'krystal_locations_2026';

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
    die('<h2 style="color:orange;">Migration already applied.</h2><p>Delete <code>.locations_migrate_lock</code> to re-run.</p>');
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

    // Step 1: Add location column to users (if not exists)
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'location'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN location VARCHAR(20) NOT NULL DEFAULT 'landside' AFTER shift");
        $steps[] = '✓ Added location column to users table.';
    } else {
        $steps[] = '⊘ users.location column already exists.';
    }

    // Step 2: Add location column to attendance_records (if not exists)
    $cols = $pdo->query("SHOW COLUMNS FROM attendance_records LIKE 'location'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE attendance_records ADD COLUMN location VARCHAR(20) NOT NULL DEFAULT 'landside' AFTER shift");
        $steps[] = '✓ Added location column to attendance_records table.';
    } else {
        $steps[] = '⊘ attendance_records.location column already exists.';
    }

    // Step 3: Add indexes for performance
    $indexes = $pdo->query("SHOW INDEX FROM attendance_records WHERE Key_name = 'idx_location'")->fetchAll();
    if (empty($indexes)) {
        $pdo->exec("CREATE INDEX idx_location ON attendance_records (location)");
        $steps[] = '✓ Created idx_location index.';
    }
    $indexes = $pdo->query("SHOW INDEX FROM attendance_records WHERE Key_name = 'idx_loc_shift_date'")->fetchAll();
    if (empty($indexes)) {
        $pdo->exec("CREATE INDEX idx_loc_shift_date ON attendance_records (location, shift, attendance_date)");
        $steps[] = '✓ Created idx_loc_shift_date composite index.';
    }

    // Step 4: Delete old shift users (IDs 1-3) — only if they exist with old usernames
    $oldUsers = $pdo->query("SELECT id FROM users WHERE username IN ('admin@morning','admin@afternoon','admin@night')")->fetchAll();
    if (!empty($oldUsers)) {
        $pdo->exec("DELETE FROM users WHERE username IN ('admin@morning','admin@afternoon','admin@night')");
        $steps[] = '✓ Deleted old shift accounts (admin@morning, admin@afternoon, admin@night).';
    } else {
        $steps[] = '⊘ Old shift accounts already removed.';
    }

    // Step 5: Update admin account
    $admin = $pdo->query("SELECT id, username FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if ($admin) {
        $pdo->prepare("UPDATE users SET username = 'admin.krystal', location = 'all' WHERE id = ?")
            ->execute([$admin['id']]);
        $steps[] = '✓ Updated admin account: username=admin.krystal, location=all.';
    }

    // Step 6: Create 9 new location-shift accounts
    $defaultPass = password_hash('Password@123', PASSWORD_DEFAULT);
    $adminPass = password_hash('KrystalAdmin@123', PASSWORD_DEFAULT);
    $accounts = [
        ['landside.morning',   'shift', 'morning',   'landside'],
        ['landside.afternoon', 'shift', 'afternoon', 'landside'],
        ['landside.night',     'shift', 'night',     'landside'],
        ['asset.morning',      'shift', 'morning',   'asset'],
        ['asset.afternoon',    'shift', 'afternoon', 'asset'],
        ['asset.night',        'shift', 'night',     'asset'],
        ['cargo.morning',      'shift', 'morning',   'cargo'],
        ['cargo.afternoon',    'shift', 'afternoon', 'cargo'],
        ['cargo.night',        'shift', 'night',     'cargo'],
    ];

    $insertStmt = $pdo->prepare(
        "INSERT INTO users (username, password, role, shift, location) VALUES (?, ?, ?, ?, ?)"
    );
    $created = 0;
    foreach ($accounts as $acc) {
        // Skip if username already exists
        $exists = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $exists->execute([$acc[0]]);
        if (!$exists->fetch()) {
            $insertStmt->execute([$acc[0], $defaultPass, $acc[1], $acc[2], $acc[3]]);
            $created++;
        }
    }
    $steps[] = "✓ Created $created new location-shift accounts (password: Password@123).";

    // Step 7: Update admin password
    if ($admin) {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([$adminPass, $admin['id']]);
        $steps[] = '✓ Updated admin password (KrystalAdmin@123).';
    }

    // Write lock file
    file_put_contents($lockFile, date('Y-m-d H:i:s'));

    // Output
    $output = implode("\n", $steps);
    if (PHP_SAPI === 'cli') {
        echo $output . "\n✓ Migration complete.\n";
    } else {
        echo '<h2 style="color:green;">✓ Migration Complete</h2>';
        echo '<pre>' . htmlspecialchars($output) . '</pre>';
        echo '<h3>Account Summary</h3><table border="1" cellpadding="8" style="border-collapse:collapse;">';
        echo '<tr><th>Username</th><th>Role</th><th>Shift</th><th>Location</th><th>Password</th></tr>';
        foreach ($accounts as $a) {
            echo '<tr><td>'.$a[0].'</td><td>'.$a[1].'</td><td>'.$a[2].'</td><td>'.$a[3].'</td><td>Password@123</td></tr>';
        }
        echo '<tr><td>admin.krystal</td><td>admin</td><td>all</td><td>all</td><td>KrystalAdmin@123</td></tr>';
        echo '</table>';
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
