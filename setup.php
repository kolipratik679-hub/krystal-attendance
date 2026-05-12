<?php
/**
 * Database Setup - Run once to create tables and seed users
 * SECURITY: Protected by lock file + secret key
 */

// Lock check - prevent re-execution after initial setup
$lockFile = __DIR__ . '/.setup_lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    die('<h2 style="color:red;">Setup is locked.</h2><p>Database has already been configured. Delete the <code>.setup_lock</code> file to re-run setup.</p>');
}

// Secret key check - prevent unauthorized access
$requiredKey = 'krystal_setup_2026';
if (!isset($_GET['key']) || $_GET['key'] !== $requiredKey) {
    http_response_code(403);
    die('<h2 style="color:red;">Access Denied.</h2><p>Setup requires a valid key parameter.</p>');
}

$host = 'localhost';
$user = 'root';
$pass = 'hello brother';
$dbname = 'krystal_attendance';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('shift','admin') NOT NULL DEFAULT 'shift',
        `shift` VARCHAR(20) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Attendance records table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `shift` VARCHAR(20) NOT NULL,
        `attendance_date` DATE NOT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_shift` (`shift`),
        INDEX `idx_date` (`attendance_date`)
    ) ENGINE=InnoDB");

    // Attendance employees table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance_employees` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `attendance_record_id` INT NOT NULL,
        `employee_name` VARCHAR(150) NOT NULL,
        `employee_id` VARCHAR(50) NOT NULL,
        `post` VARCHAR(50) NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'present',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`attendance_record_id`) REFERENCES `attendance_records`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Seed default users (only if table is empty)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ((int)$stmt->fetchColumn() === 0) {
        $users = [
            ['admin@morning', 'krystal@first', 'shift', 'morning'],
            ['admin@afternoon', 'krystal@second', 'shift', 'afternoon'],
            ['admin@night', 'krystal@third', 'shift', 'night'],
            ['admin@krystal', 'mainadmin@8989', 'admin', 'all'],
        ];
        $ins = $pdo->prepare("INSERT INTO users (username, password, role, shift) VALUES (?, ?, ?, ?)");
        foreach ($users as $u) {
            $ins->execute([$u[0], password_hash($u[1], PASSWORD_DEFAULT), $u[2], $u[3]]);
        }
        echo "<p>Users seeded successfully.</p>";
    } else {
        echo "<p>Users already exist, skipping seed.</p>";
    }

    echo "<h2 style='color:green;'>Database setup complete!</h2>";
    echo "<p><a href='index.php'>Go to Login</a></p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Setup Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
